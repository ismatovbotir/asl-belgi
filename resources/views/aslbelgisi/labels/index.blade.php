@extends('layouts.app')
@section('title', 'Label Design')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-0"><i class="bi bi-printer"></i> Label Design</h4>
        <small class="text-muted">Design and print labels for downloaded marking codes</small>
    </div>
</div>

@if($orders->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="fs-1 text-muted mb-3"><i class="bi bi-printer"></i></div>
            <h5 class="text-muted">No orders ready for printing</h5>
            <p class="text-muted">Go to <a href="{{ route('asl.orders.index') }}">Orders</a> and import a CSV file with marking codes first.</p>
        </div>
    </div>
@else
    <div class="row g-3">
        @foreach($orders as $order)
        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h6 class="mb-0 fw-semibold">{{ $order->name ?? $order->external_order_id ?? '#'.$order->id }}</h6>
                        <span class="badge status-badge badge-{{ $order->status }}">{{ $order->status }}</span>
                    </div>
                    @if($order->external_order_id)
                        <small class="text-muted font-monospace">{{ $order->external_order_id }}</small>
                    @endif
                    <div class="text-muted small mt-2 mb-3">
                        <i class="bi bi-calendar3"></i> {{ $order->created_at?->format('d M Y') }}
                    </div>

                    <div class="row g-2 text-center mb-3">
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="fs-4 fw-bold text-success">{{ number_format($order->available_codes) }}</div>
                                <div class="text-muted" style="font-size:0.7rem;">Available</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="fs-4 fw-bold text-secondary">{{ number_format($order->printed_codes) }}</div>
                                <div class="text-muted" style="font-size:0.7rem;">Printed</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <a href="{{ route('asl.labels.designer', $order) }}" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-layout-text-window"></i> Designer
                    </a>
                    @if($order->available_codes > 0)
                        <a href="{{ route('asl.labels.print', $order) }}" class="btn btn-outline-success btn-sm flex-grow-1" target="_blank">
                            <i class="bi bi-printer"></i> Print All
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif
@endsection
