<?php

namespace App\Http\Controllers\AslBelgisi;

use App\Http\Controllers\Controller;
use App\Models\KmCode;
use App\Models\KmOrder;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LabelController extends Controller
{
    public function index()
    {
        $orders = KmOrder::whereIn('status', ['DOWNLOADED', 'DONE', 'READY'])
            ->withCount(['codes as available_codes' => fn ($q) => $q->where('status', 'available')])
            ->withCount(['codes as printed_codes' => fn ($q) => $q->where('label_printed', true)])
            ->latest()
            ->get();

        return view('aslbelgisi.labels.index', compact('orders'));
    }

    public function designer(Request $request, KmOrder $order)
    {
        $order->load('items');

        // Get distinct GTINs from this order's items
        $gtins    = $order->items->pluck('gtin')->filter()->unique()->values();
        $products = Product::whereIn('gtin', $gtins)->get()->keyBy('gtin');

        $codes = KmCode::where('km_order_id', $order->id)
            ->where('status', 'available')
            ->paginate(200);

        $template = $request->query('template', 'default');

        return view('aslbelgisi.labels.designer', compact('order', 'codes', 'products', 'gtins', 'template'));
    }

    public function print(Request $request, KmOrder $order)
    {
        $itemId = $request->query('item_id');
        $limit  = (int) $request->query('limit', 0);

        $query = KmCode::where('km_order_id', $order->id)->where('status', 'available');

        if ($itemId) {
            $query->where('km_order_item_id', $itemId);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $codes = $query->get();

        // Resolve product info per GTIN via the order items
        $gtins    = $order->items->pluck('gtin')->filter()->unique()->values();
        $products = Product::whereIn('gtin', $gtins)->get()->keyBy('gtin');

        $labelSize = $request->query('size', '58x40');

        return view('aslbelgisi.labels.print', compact('order', 'codes', 'products', 'labelSize'));
    }

    public function setTemplate(Request $request, KmOrder $order)
    {
        $validated = $request->validate([
            'label_template_id' => 'nullable|exists:label_templates,id',
        ]);

        $order->update(['label_template_id' => $validated['label_template_id'] ?: null]);

        return response()->json(['success' => true]);
    }

    public function generatePdf(Request $request, KmOrder $order)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $order->load(['items', 'labelTemplate']);

        $itemId = $request->input('item_id');
        $limit  = (int) $request->input('limit', 0);

        $query = KmCode::where('km_order_id', $order->id)->where('status', 'available');
        if ($itemId) {
            $query->where('km_order_item_id', $itemId);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }
        $codes = $query->get();

        if ($codes->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No available codes found.'], 422);
        }

        $gtins    = $order->items->pluck('gtin')->filter()->unique()->values();
        $products = Product::whereIn('gtin', $gtins)->get()->keyBy('gtin');

        $tpl = $order->labelTemplate;

        if ($tpl) {
            $wPt  = round($tpl->width_mm  * 2.8346, 2);
            $hPt  = round($tpl->height_mm * 2.8346, 2);
            $view = 'aslbelgisi.labels.template_pdf';
            $data = compact('order', 'codes', 'products', 'tpl');
        } else {
            $wPt  = 170.08;
            $hPt  = 113.39;
            $view = 'aslbelgisi.labels.pdf';
            $data = compact('order', 'codes', 'products');
        }

        $pdf = Pdf::loadView($view, $data)
            ->setPaper([0, 0, $wPt, $hPt], 'portrait')
            ->setOptions([
                'defaultFont'             => 'DejaVu Sans',
                'isPhpEnabled'            => true,
                'isFontSubsettingEnabled' => true,
                'dpi'                     => 96,
            ]);

        $dir      = 'orders/pdfs';
        $filename = $dir . '/' . $order->id . '_' . now()->format('Ymd_His') . '.pdf';

        Storage::disk('public')->makeDirectory($dir);
        Storage::disk('public')->put($filename, $pdf->output());

        $order->update(['pdf_path' => $filename]);

        $downloadUrl = route('asl.labels.downloadPdf', [
            'order' => $order->id,
            'file'  => basename($filename),
        ]);

        return response()->json([
            'success'  => true,
            'url'      => $downloadUrl,
            'filename' => basename($filename),
            'count'    => $codes->count(),
        ]);
    }

    public function downloadPdf(KmOrder $order, string $file)
    {
        // Ensure the file belongs to this order
        abort_if(!str_starts_with($file, $order->id . '_'), 403);

        $path = Storage::disk('public')->path('orders/pdfs/' . $file);

        abort_if(!file_exists($path), 404);

        return response()->download($path, $file, ['Content-Type' => 'application/pdf']);
    }

    public function markPrinted(Request $request, KmOrder $order)
    {
        $ids = $request->input('code_ids', []);

        if (empty($ids)) {
            // Mark all available codes for this order as printed
            $count = KmCode::where('km_order_id', $order->id)
                ->where('label_printed', false)
                ->update(['label_printed' => true, 'printed_at' => now(), 'status' => 'printed']);
        } else {
            $count = KmCode::whereIn('id', $ids)
                ->where('km_order_id', $order->id)
                ->update(['label_printed' => true, 'printed_at' => now(), 'status' => 'printed']);
        }

        // If all codes printed → mark order DONE
        $remaining = KmCode::where('km_order_id', $order->id)->where('status', 'available')->count();
        if ($remaining === 0) {
            $order->update(['status' => 'DONE']);
        }

        return back()->with('success', "Marked {$count} codes as printed.");
    }
}
