<?php

namespace App\Http\Controllers\AslBelgisi;

use App\Http\Controllers\Controller;
use App\Jobs\PollOrderStatusJob;
use App\Models\KmCode;
use App\Models\KmOrder;
use App\Models\KmOrderItem;
use App\Models\LabelTemplate;
use App\Models\Printer;
use App\Models\Product;
use App\Services\AslBelgisi\Orders\OrderService;

class OrderController extends Controller
{
    public function __construct(private OrderService $service) {}

    public function index()
    {
        $orders = KmOrder::with('items')->latest()->paginate(30);
        return view('aslbelgisi.orders.index', compact('orders'));
    }

    public function import(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/octet-stream|max:20480',
        ]);

        try {
            $count = $this->service->importFromCsv($request->file('csv_file'));
            return back()->with('success', "Imported {$count} codes from CSV.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function show(KmOrder $order)
    {
        $order->load(['items', 'labelTemplate']);
        $codes = KmCode::where('km_order_id', $order->id)->paginate(100);

        $templates = LabelTemplate::orderBy('name')->get();

        $printers = Printer::with('printerType')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $availableCount = KmCode::where('km_order_id', $order->id)
            ->where('status', 'available')
            ->count();

        $firstCode = KmCode::where('km_order_id', $order->id)
            ->where('status', 'available')
            ->first();

        $firstProduct = null;
        if ($firstCode?->gtin) {
            $firstProduct = Product::where('gtin', $firstCode->gtin)->first();
        }

        return view('aslbelgisi.orders.show', compact('order', 'codes', 'templates', 'printers', 'availableCount', 'firstCode', 'firstProduct'));
    }

    public function refreshStatus(KmOrder $order)
    {
        if ($order->isDone()) {
            return back()->with('info', 'Order is already complete. No refresh needed.');
        }

        try {
            $this->service->refreshOrderStatus($order);
            return back()->with('success', 'Order status refreshed.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Refresh failed: ' . $e->getMessage());
        }
    }

    public function downloadBuffer(KmOrder $order, KmOrderItem $item)
    {
        if ($item->km_order_id !== $order->id) {
            abort(404);
        }

        if ($item->status === 'DOWNLOADED') {
            return back()->with('info', "Buffer {$item->buffer_id} already downloaded ({$item->codes_downloaded} codes).");
        }

        if (! in_array($item->status, ['READY', 'AVAILABLE'])) {
            return back()->with('warning', "Buffer status is {$item->status}. Can only download READY buffers.");
        }

        try {
            $count = $this->service->downloadCodes($order, $item);
            return back()->with('success', "Downloaded {$count} codes for buffer {$item->buffer_id}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Download failed: ' . $e->getMessage());
        }
    }

    public function pollStatus(KmOrder $order)
    {
        if ($order->isDone()) {
            return back()->with('info', 'Order complete. Polling not needed.');
        }

        PollOrderStatusJob::dispatch($order->id);
        return back()->with('success', 'Status polling job queued. Refresh in 30 seconds.');
    }
}
