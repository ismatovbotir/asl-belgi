# Label Designer — Technical Reference

A browser-based label template designer with live preview, drag-to-reposition, resize handles, overlap detection, and server-side PDF generation via DomPDF + DNS2D/DNS1D barcodes.

---

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 10 (PHP 8.1+) |
| PDF | `barryvdh/laravel-dompdf` |
| Barcodes (server) | `milon/barcode` (DNS2D, DNS1D facades) |
| Barcodes (browser) | `bwip-js` v4 (CDN) |
| CSS framework | Bootstrap 5 + Bootstrap Icons |

---

## Database

### `label_templates`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | varchar(100) | template display name |
| `width_mm` | float | label width in mm |
| `height_mm` | float | label height in mm |
| `elements` | json | serialized element definitions |
| `created_at` / `updated_at` | timestamps | |

### `elements` JSON structure

```json
{
  "datamatrix": { "visible": true, "x": 1, "y": 7.5, "size": 25 },
  "name":        { "visible": true, "x1": 27, "y1": 1,  "x2": 59, "y2": 16, "font_size": 7.5, "bold": true },
  "ean13":       { "visible": true, "x1": 27, "y1": 19, "x2": 59, "y2": 37, "font_size": 4 },
  "batch":       { "visible": true, "x1": 27, "y1": 16, "x2": 59, "y2": 19, "font_size": 5 },
  "page_number": { "visible": true, "x1": 27, "y1": 37, "x2": 42, "y2": 39, "font_size": 5 }
}
```

DataMatrix uses `(x, y, size)` — top-left corner + square size, all in mm.  
All other elements use `(x1, y1, x2, y2)` — bounding box corners in mm.

---

## Files

```
app/
  Models/LabelTemplate.php                         ← el(), normalizeEl(), defaults()
  Http/Controllers/AslBelgisi/
    LabelTemplateController.php                    ← CRUD + buildElements()
    LabelController.php                            ← generatePdf(), setTemplate()

resources/views/aslbelgisi/labels/
  templates/
    index.blade.php                                ← list with mini-preview
    form.blade.php                                 ← designer (preview + settings)
  template_pdf.blade.php                           ← DomPDF output

routes/aslbelgisi.php                              ← label-templates resource + pdf routes
database/migrations/
  2026_05_07_180001_create_label_templates_table.php
```

---

## Model: LabelTemplate

Key methods:

```php
// Returns normalized element array by key, handles old (x,y,w,h) → new (x1,y1,x2,y2) migration
public function el(string $key): array

// Default values for all 5 elements
public static function defaults(): array
```

`normalizeEl()` provides backward-compatibility for templates saved in earlier data formats — it converts `x/y/width/height` fields to `x1/y1/x2/y2` on the fly.

---

## Preview: coordinate system

```
1 mm = currentScale px   (scale chosen to fit the preview panel)

currentScale = min(
    (panelWidth - 80) / widthMm,
    340 / heightMm
)
```

All element positions are stored in mm, converted to px for CSS/canvas using `value * currentScale`.

---

## JavaScript features

### Drag to reposition
- Pointer Events API (`pointerdown` + `pointermove` + `pointerup`)
- `setPointerCapture` ensures the move fires even if cursor leaves the element
- Snap to 0.5 mm: `Math.round(v * 2) / 2`
- Clamp within label: `Math.min(Math.max(v, 0), labelDim - elementSize)`
- DataMatrix: updates `x`, `y`
- Box elements: updates `x1`, `y1`, `x2`, `y2` (preserving width/height during drag)

### Resize from bottom-right corner
- Separate `div.lp-resize-handle` per element, positioned at `(x2, y2)` of each element
- `e.stopPropagation()` prevents triggering the drag handler
- DataMatrix: adjusts `size` (average of X+Y delta, stays square)
- Box elements: adjusts `x2` and `y2` independently (minimum 3 mm)

### Overlap detection
```js
// AABB intersection test
const ix1 = Math.max(a.x1, b.x1), iy1 = Math.max(a.y1, b.y1);
const ix2 = Math.min(a.x2, b.x2), iy2 = Math.min(a.y2, b.y2);
if (ix1 < ix2 && iy1 < iy2) { /* draw red overlay */ }
```
Red semi-transparent `div.lp-intersect` overlays rendered after every move.

### Click-to-activate form block
- `wasDragged` flag: set to `true` in `pointermove`, checked in `pointerup`
- If `!wasDragged` → call `activateBlock(blockId)` which adds `el-active` CSS class and smooth-scrolls the matching settings block into view
- Resize handle pointerdown immediately calls `activateBlock`

### DataMatrix rendering (bwip-js)
```js
// Render at high scale to temp canvas, then draw crisp to display canvas
const tmp = document.createElement('canvas');
bwipjs.toCanvas(tmp, { bcid: 'gs1datamatrix', text: dmText, parse: true, scale: 4 });
ctx.imageSmoothingEnabled = false;
ctx.drawImage(tmp, 0, 0, displayWidth, displayHeight);
```

### EAN-13 rendering (bwip-js)
```js
bwipjs.toCanvas(tmp, { bcid: 'ean13', text: ean13, scale: 3, includetext: true, guardwhitespace: true });
// Aspect-ratio-preserving draw (letterbox within bounding box)
const ratio = Math.min(w / tmp.width, h / tmp.height);
ctx.drawImage(tmp, dx, dy, dw, dh);
```

---

## GS1 DataMatrix string (UZ ПКМ №148)

```php
$dmString = chr(232)          // FNC1 — GS1 DataMatrix start (displays as 'h' on some scanners)
          . '01' . $gtin       // AI 01: GTIN-14 (fixed 14 digits, no separator needed)
          . '21' . $sn  . chr(29)   // AI 21: Serial number (20 chars) + GS separator
          . '91' . $key . chr(29)   // AI 91: Verification key (4 chars) + GS separator
          . '92' . $code;           // AI 92: Verification code (44 chars) — last, no separator
```

| AI | Name | Length | Notes |
|---|---|---|---|
| 01 | GTIN | 14 | Fixed, no GS separator after |
| 21 | Serial number | 20 | Variable per GS1 spec → GS separator after |
| 91 | Verification key | 4 | Crypto tail, generated by operator → GS separator after |
| 92 | Verification code | 44 | Crypto tail, generated by operator → last, no separator |

AI 91 + AI 92 = **крипто-хвост (КХ)**.

For bwip-js browser preview, use bracketed notation with `parse: true`:
```
[01]GTIN[21]SN[91]KEY[92]CODE
```
bwip-js inserts FNC1 and GS separators automatically.

---

## PDF generation (DomPDF)

- Page size: `@page { margin: 0; size: {width}mm {height}mm; }`
- Each label: `div.label { position: relative; width: Xmm; height: Ymm; page-break-after: always; }`
- All elements: `position: absolute; left: Xmm; top: Ymm;`
- DataMatrix: `DNS2D::getBarcodePNG($dmString, 'DATAMATRIX', 3, 3)` → base64 PNG `<img>`
- EAN-13: `DNS1D::getBarcodePNG($ean13, 'EAN13', 1, 40, [0,0,0], false)` (no built-in text) + separate `<div>` for digits with custom `font-size`
- Page number: PHP counter (`$labelNum++` inside `@foreach`) — DomPDF `page_text()` only executes on page 1

---

## CSS classes (preview panel)

| Class | Purpose |
|---|---|
| `.prev-section` | Sticky container (`position: sticky; top: 0`) |
| `#label-preview` | White label canvas with blue border |
| `.lp-el` | Draggable text element |
| `.lp-resize-handle` | 10×10 px blue square at bottom-right of each element |
| `.lp-bbox` | Dashed bounding box overlay (pointer-events: none) |
| `.lp-intersect` | Red semi-transparent overlap area (pointer-events: none) |
| `.el-block.el-active` | Green-bordered active settings block |
