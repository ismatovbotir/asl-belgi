<?php

namespace App\Http\Controllers\AslBelgisi;

use App\Http\Controllers\Controller;
use App\Models\KmCode;
use App\Models\LabelTemplate;
use Illuminate\Http\Request;

class LabelTemplateController extends Controller
{
    public function index()
    {
        $templates = LabelTemplate::orderBy('name')->get();
        return view('aslbelgisi.labels.templates.index', compact('templates'));
    }

    public function create()
    {
        $defaults   = LabelTemplate::defaults();
        $template   = null;
        $sampleCode = KmCode::whereNotNull('gtin')->first();
        return view('aslbelgisi.labels.templates.form', compact('defaults', 'template', 'sampleCode'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'width_mm'  => 'required|numeric|min:10|max:300',
            'height_mm' => 'required|numeric|min:10|max:300',
        ]);

        $elements = $this->buildElements($request);

        LabelTemplate::create([
            'name'      => $validated['name'],
            'width_mm'  => $validated['width_mm'],
            'height_mm' => $validated['height_mm'],
            'elements'  => $elements,
        ]);

        return redirect()->route('asl.label-templates.index')
            ->with('success', 'Template "' . $validated['name'] . '" created.');
    }

    public function edit(LabelTemplate $labelTemplate)
    {
        $defaults   = LabelTemplate::defaults();
        $template   = $labelTemplate;
        $sampleCode = KmCode::whereNotNull('gtin')->first();
        return view('aslbelgisi.labels.templates.form', compact('defaults', 'template', 'sampleCode'));
    }

    public function update(Request $request, LabelTemplate $labelTemplate)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'width_mm'  => 'required|numeric|min:10|max:300',
            'height_mm' => 'required|numeric|min:10|max:300',
        ]);

        $elements = $this->buildElements($request);

        $labelTemplate->update([
            'name'      => $validated['name'],
            'width_mm'  => $validated['width_mm'],
            'height_mm' => $validated['height_mm'],
            'elements'  => $elements,
        ]);

        return redirect()->route('asl.label-templates.index')
            ->with('success', 'Template "' . $validated['name'] . '" updated.');
    }

    public function destroy(LabelTemplate $labelTemplate)
    {
        $name = $labelTemplate->name;
        $labelTemplate->delete();
        return redirect()->route('asl.label-templates.index')
            ->with('success', "Template \"{$name}\" deleted.");
    }

    private function buildElements(Request $request): array
    {
        $raw = $request->input('elements', []);

        return [
            'datamatrix' => [
                'visible' => true,
                'x'       => (float) ($raw['datamatrix']['x']    ?? 1),
                'y'       => (float) ($raw['datamatrix']['y']    ?? 7.5),
                'size'    => (float) ($raw['datamatrix']['size'] ?? 25),
            ],
            'name' => [
                'visible'   => isset($raw['name']['visible']),
                'x1'        => (float) ($raw['name']['x1']        ?? 27),
                'y1'        => (float) ($raw['name']['y1']        ?? 1),
                'x2'        => (float) ($raw['name']['x2']        ?? 59),
                'y2'        => (float) ($raw['name']['y2']        ?? 16),
                'font_size' => (float) ($raw['name']['font_size'] ?? 7.5),
                'bold'      => isset($raw['name']['bold']),
            ],
            'ean13' => [
                'visible'   => isset($raw['ean13']['visible']),
                'x1'        => (float) ($raw['ean13']['x1']        ?? 27),
                'y1'        => (float) ($raw['ean13']['y1']        ?? 19),
                'x2'        => (float) ($raw['ean13']['x2']        ?? 59),
                'y2'        => (float) ($raw['ean13']['y2']        ?? 37),
                'font_size' => (float) ($raw['ean13']['font_size'] ?? 4),
            ],
            'batch' => [
                'visible'   => isset($raw['batch']['visible']),
                'x1'        => (float) ($raw['batch']['x1']        ?? 27),
                'y1'        => (float) ($raw['batch']['y1']        ?? 16),
                'x2'        => (float) ($raw['batch']['x2']        ?? 59),
                'y2'        => (float) ($raw['batch']['y2']        ?? 19),
                'font_size' => (float) ($raw['batch']['font_size'] ?? 5),
            ],
            'page_number' => [
                'visible'   => isset($raw['page_number']['visible']),
                'x1'        => (float) ($raw['page_number']['x1']        ?? 27),
                'y1'        => (float) ($raw['page_number']['y1']        ?? 37),
                'x2'        => (float) ($raw['page_number']['x2']        ?? 42),
                'y2'        => (float) ($raw['page_number']['y2']        ?? 39),
                'font_size' => (float) ($raw['page_number']['font_size'] ?? 5),
            ],
        ];
    }
}
