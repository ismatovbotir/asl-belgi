@extends('layouts.app')
@section('title', $order->name ?? $order->external_order_id ?? 'Order #'.$order->id)

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <a href="{{ route('asl.orders.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Orders
        </a>
        <h4 class="mb-0 mt-1">
            {{ $order->name ?? $order->external_order_id ?? '#'.$order->id }}
            <span class="badge status-badge badge-{{ $order->status }} ms-2">{{ $order->status }}</span>
        </h4>
        <small class="text-muted">
            @if($order->external_order_id)
                <code class="small">{{ $order->external_order_id }}</code> &nbsp;|&nbsp;
            @endif
            <i class="bi bi-upc-scan"></i>
            <strong>{{ number_format($order->total_codes_downloaded) }}</strong>
            / {{ number_format($order->total_codes_requested) }} codes downloaded
            &nbsp;|&nbsp;
            <i class="bi bi-calendar3"></i> {{ $order->created_at?->format('d M Y, H:i') }}
        </small>
    </div>
    <div class="d-flex gap-2">
        {{-- API-only controls: only show when order was synced from API --}}
        @if(! $order->isDone() && $order->external_order_id)
            <form method="POST" action="{{ route('asl.orders.refresh', $order) }}">
                @csrf
                <button class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-repeat"></i> Refresh Status
                </button>
            </form>
            <form method="POST" action="{{ route('asl.orders.poll', $order) }}">
                @csrf
                <button class="btn btn-outline-info btn-sm">
                    <i class="bi bi-clock"></i> Auto-Poll
                </button>
            </form>
        @endif
        @if($order->total_codes_downloaded > 0)
            <a href="{{ route('asl.labels.designer', $order) }}" class="btn btn-success btn-sm">
                <i class="bi bi-printer"></i> Design Labels
            </a>
        @endif
        @if($order->pdf_path)
            <a href="{{ Storage::url($order->pdf_path) }}" target="_blank" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> Download PDF
            </a>
        @endif
    </div>
</div>

{{-- GTIN Items --}}
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-layers"></i> Items — {{ $order->items->count() }} GTIN group(s)</span>
        <small class="text-muted">Each row is one product (GTIN) in this order</small>
    </div>
    <div class="card-body p-0">
        @if($order->items->isEmpty())
            <div class="p-4 text-center text-muted">No items found.</div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>GTIN</th>
                        <th>Quantity</th>
                        <th>Downloaded</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td><code class="small">{{ $item->gtin ?? '—' }}</code></td>
                        <td>{{ number_format($item->quantity) }}</td>
                        <td>
                            {{ number_format($item->codes_downloaded) }}
                            @if($item->quantity > 0)
                                @php $pct = round($item->codes_downloaded / $item->quantity * 100) @endphp
                                <div class="progress mt-1" style="height:3px; width:80px;">
                                    <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge status-badge badge-{{ $item->status }}">{{ $item->status }}</span>
                        </td>
                        <td class="text-end">
                            @if(in_array($item->status, ['READY', 'AVAILABLE']))
                                <form method="POST" action="{{ route('asl.orders.download', [$order, $item]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success">
                                        <i class="bi bi-download"></i> Download
                                    </button>
                                </form>
                            @elseif($item->status === 'DOWNLOADED')
                                <span class="text-success small">
                                    <i class="bi bi-check-circle-fill"></i> {{ number_format($item->codes_downloaded) }} saved
                                </span>
                            @elseif($item->status === 'PENDING')
                                <span class="text-muted small"><i class="bi bi-hourglass-split"></i> Waiting…</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Marking Codes --}}
@if($codes->total() > 0)
<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-upc-scan"></i> Marking Codes ({{ number_format($codes->total()) }})</span>
        <a href="{{ route('asl.labels.designer', $order) }}" class="btn btn-success btn-sm">
            <i class="bi bi-printer"></i> Design & Print Labels
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="font-size:0.8rem;">
                <thead class="table-light">
                    <tr>
                        <th class="text-muted fw-normal">#</th>
                        <th>GTIN</th>
                        <th>Serial Number</th>
                        <th>Expiry</th>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Printed</th>
                        <th>Applied</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($codes as $code)
                    <tr>
                        <td class="text-muted">{{ $code->id }}</td>
                        <td><code class="small">{{ $code->gtin ?? '—' }}</code></td>
                        <td>
                            <code class="small" title="{{ $code->cis }}">{{ $code->serial_number ?? '—' }}</code>
                        </td>
                        <td>
                            @if($code->expiry_date)
                                <span class="{{ $code->expiry_date->isPast() ? 'text-danger' : 'text-dark' }}">
                                    {{ $code->expiry_date->format('d.m.Y') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $code->batch ?? '—' }}</td>
                        <td>
                            <span class="badge status-badge badge-{{ $code->status }}">
                                {{ $code->status }}
                            </span>
                        </td>
                        <td>
                            @if($code->printed_at)
                                <small class="text-muted">{{ $code->printed_at->format('d/m H:i') }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($code->applied_at)
                                <small class="text-muted">{{ $code->applied_at->format('d/m H:i') }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($codes->hasPages())
        <div class="card-footer bg-white">
            {{ $codes->links() }}
        </div>
        @endif
    </div>
</div>
@endif
@endsection
