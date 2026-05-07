@extends('layouts.app')
@section('title', 'KM Orders')

@push('styles')
<style>
    .loading-spinner-ring {
        width: 64px; height: 64px;
        border: 5px solid #e9ecef;
        border-top-color: #0d6efd;
        border-radius: 50%;
        animation: spin 0.9s linear infinite;
        margin: 0 auto 1.25rem;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-progress {
        height: 4px; border-radius: 2px;
        background: linear-gradient(90deg, #0d6efd 0%, #6ea8fe 50%, #0d6efd 100%);
        background-size: 200% 100%;
        animation: shimmer 1.4s ease-in-out infinite;
    }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    .loading-dots::after {
        content: '';
        animation: dots 1.5s steps(4, end) infinite;
    }
    @keyframes dots { 0%{content:''} 25%{content:'.'} 50%{content:'..'} 75%{content:'...'} 100%{content:''} }
</style>
@endpush

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-0"><i class="bi bi-list-ol"></i> KM Orders</h4>
        <small class="text-muted">{{ $orders->total() }} orders total</small>
    </div>
    <form id="importForm" method="POST" action="{{ route('asl.orders.import') }}"
          enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
        @csrf
        <input type="file" id="csvFileInput" name="csv_file"
               class="form-control form-control-sm @error('csv_file') is-invalid @enderror"
               accept=".csv,.txt" required style="max-width:280px;">
        @error('csv_file')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <button class="btn btn-primary btn-sm text-nowrap">
            <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
        </button>
    </form>
</div>

{{-- Loading Modal --}}
<div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="loading-progress"></div>
            <div class="modal-body text-center py-5 px-4">
                <div class="loading-spinner-ring"></div>
                <h5 class="mb-1">Importing marking codes<span class="loading-dots"></span></h5>
                <p class="text-muted mb-1" id="loadingFileName">Reading file</p>
                <small class="text-muted">Parsing GS1 DataMatrix codes and saving to database</small>
                <div class="alert alert-light border text-start small mb-0 mt-3">
                    <i class="bi bi-info-circle text-primary"></i>
                    Large files may take a few seconds. Please do not close the tab.
                </div>
            </div>
        </div>
    </div>
</div>

@if($orders->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="fs-1 text-muted mb-3"><i class="bi bi-file-earmark-arrow-up"></i></div>
            <h5 class="text-muted">No orders yet</h5>
            <p class="text-muted">Upload a CSV file with marking codes to create an order.</p>
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order Name</th>
                            <th>GTINs</th>
                            <th>Codes</th>
                            <th>Status</th>
                            <th>PDF</th>
                            <th>Imported</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('asl.orders.show', $order) }}" class="fw-semibold text-decoration-none">
                                    {{ $order->name ?? $order->external_order_id ?? '#'.$order->id }}
                                </a>
                                @if($order->external_order_id)
                                    <br><small class="text-muted font-monospace">{{ $order->external_order_id }}</small>
                                @endif
                            </td>
                            <td>
                                @foreach($order->items as $item)
                                    <code class="small d-block">{{ $item->gtin ?? '—' }}</code>
                                @endforeach
                            </td>
                            <td>
                                <span class="fw-semibold">{{ number_format($order->total_codes_downloaded) }}</span>
                                <span class="text-muted small">/ {{ number_format($order->total_codes_requested) }}</span>
                                @if($order->total_codes_requested > 0)
                                    @php $pct = round($order->total_codes_downloaded / $order->total_codes_requested * 100) @endphp
                                    <div class="progress mt-1" style="height:3px;">
                                        <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge status-badge badge-{{ $order->status }}">
                                    {{ $order->status }}
                                </span>
                            </td>
                            <td>
                                @if($order->pdf_path)
                                    <a href="{{ Storage::url($order->pdf_path) }}" target="_blank"
                                       class="btn btn-sm btn-outline-danger" title="Download PDF">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted" title="{{ $order->created_at }}">
                                    {{ $order->created_at?->diffForHumans() }}
                                </small>
                            </td>
                            <td class="text-end">
                                @if(! $order->isDone() && $order->external_order_id)
                                    <form method="POST" action="{{ route('asl.orders.refresh', $order) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" title="Refresh status from API">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('asl.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->hasPages())
        <div class="card-footer bg-white">
            {{ $orders->links() }}
        </div>
        @endif
    </div>
@endif
@endsection

@push('scripts')
<script>
    const importForm    = document.getElementById('importForm');
    const csvFileInput  = document.getElementById('csvFileInput');
    const loadingModal  = new bootstrap.Modal(document.getElementById('loadingModal'));

    importForm.addEventListener('submit', function () {
        const file = csvFileInput.files[0];
        if (file) {
            document.getElementById('loadingFileName').textContent = file.name;
            loadingModal.show();
        }
    });
</script>
@endpush
