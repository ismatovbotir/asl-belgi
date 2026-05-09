<?php

namespace App\Http\Controllers\AslBelgisi;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\PrinterType;
use Illuminate\Http\Request;

class PrinterController extends Controller
{
    public function index()
    {
        $printers = Printer::with('printerType')->orderByDesc('is_default')->orderBy('name')->get();
        return view('aslbelgisi.printers.index', compact('printers'));
    }

    public function create()
    {
        $printerTypes = PrinterType::orderBy('name')->get();
        $printer      = null;
        return view('aslbelgisi.printers.form', compact('printerTypes', 'printer'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'printer_type_id' => 'required|exists:printer_types,id',
            'parameters'      => 'nullable|array',
            'is_active'       => 'boolean',
        ]);

        $printer = Printer::create([
            'name'            => $validated['name'],
            'printer_type_id' => $validated['printer_type_id'],
            'parameters'      => $request->input('parameters', []),
            'is_active'       => $request->boolean('is_active', true),
            'is_default'      => false,
        ]);

        if ($request->boolean('set_default')) {
            $this->applyDefault($printer);
        }

        return redirect()->route('asl.printers.index')
            ->with('success', "Printer \"{$printer->name}\" added.");
    }

    public function edit(Printer $printer)
    {
        $printerTypes = PrinterType::orderBy('name')->get();
        return view('aslbelgisi.printers.form', compact('printerTypes', 'printer'));
    }

    public function update(Request $request, Printer $printer)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'printer_type_id' => 'required|exists:printer_types,id',
            'parameters'      => 'nullable|array',
            'is_active'       => 'boolean',
        ]);

        $printer->update([
            'name'            => $validated['name'],
            'printer_type_id' => $validated['printer_type_id'],
            'parameters'      => $request->input('parameters', []),
            'is_active'       => $request->boolean('is_active', true),
        ]);

        if ($request->boolean('set_default')) {
            $this->applyDefault($printer);
        }

        return redirect()->route('asl.printers.index')
            ->with('success', "Printer \"{$printer->name}\" updated.");
    }

    public function setDefault(Printer $printer)
    {
        $this->applyDefault($printer);
        return back()->with('success', "\"{$printer->name}\" set as default printer.");
    }

    public function destroy(Printer $printer)
    {
        $name = $printer->name;
        $printer->delete();
        return redirect()->route('asl.printers.index')
            ->with('success', "Printer \"{$name}\" deleted.");
    }

    private function applyDefault(Printer $printer): void
    {
        Printer::where('id', '!=', $printer->id)->update(['is_default' => false]);
        $printer->update(['is_default' => true]);
    }
}
