@extends('layouts.app')
@section('title', 'Products')

@push('styles')
<style>
    .loading-spinner-ring {
        width: 72px; height: 72px;
        border: 6px solid #e9ecef;
        border-top-color: #0d6efd;
        border-radius: 50%;
        animation: spin 0.9s linear infinite;
        margin: 0 auto 1.25rem;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .loading-progress {
        height: 4px;
        border-radius: 2px;
        background: linear-gradient(90deg, #0d6efd 0%, #6ea8fe 50%, #0d6efd 100%);
        background-size: 200% 100%;
        animation: shimmer 1.4s ease-in-out infinite;
    }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    .loading-dots::after {
        content: '';
        animation: dots 1.5s steps(4, end) infinite;
    }
    @keyframes dots {
        0%   { content: ''; }
        25%  { content: '.'; }
        50%  { content: '..'; }
        75%  { content: '...'; }
        100% { content: ''; }
    }
</style>
@endpush

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-0"><i class="bi bi-box-seam"></i> Product Registry</h4>
        <small class="text-muted">
            {{ $products->total() }} products locally
            @if($lastSync)
                | Last sync: {{ \Carbon\Carbon::parse($lastSync)->diffForHumans() }}
            @endif
        </small>
    </div>
    <form id="syncForm" method="POST" action="{{ route('asl.products.sync') }}" class="d-flex gap-2 align-items-center">
        @csrf
        <select id="productGroupSelect" name="product_group"
                class="form-select form-select-sm @error('product_group') is-invalid @enderror"
                style="min-width:220px;" required>
            <option value="" disabled>— Select product group —</option>
            @foreach($groups as $group)
                <option value="{{ $group->code }}"
                    data-label="{{ $group->name_ru }}"
                    {{ (old('product_group', 'appliances') === $group->code) ? 'selected' : '' }}>
                    {{ $group->name_ru }}
                </option>
            @endforeach
        </select>
        @error('product_group')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <button type="submit" class="btn btn-primary btn-sm text-nowrap">
            <i class="bi bi-cloud-download"></i> Import from API
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
                <h5 class="mb-1">Syncing from ASL BELGISI<span class="loading-dots"></span></h5>
                <p class="text-muted mb-3" id="loadingGroupLabel">Fetching product catalog</p>
                <div class="alert alert-light border text-start small mb-0">
                    <i class="bi bi-info-circle text-primary"></i>
                    Depending on catalog size this may take 10–30 seconds.
                    Please do not close the tab.
                </div>
            </div>
        </div>
    </div>
</div>

@if($products->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="fs-1 text-muted mb-3"><i class="bi bi-box-seam"></i></div>
            <h5 class="text-muted">No products yet</h5>
            <p class="text-muted">Click "Import from API" to sync your product catalog from ASL BELGISI.</p>
        </div>
    </div>
@else
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>GTIN</th>
                            <th>Product Name</th>
                            <th>Brand</th>
                            <th>Category / Group</th>
                            <th>Synced</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $product)
                        <tr>
                            <td><code class="small">{{ $product->gtin }}</code></td>
                            <td>
                                <div class="fw-semibold">{{ $product->name }}</div>
                                @if($product->external_id)
                                    <small class="text-muted">ID: {{ $product->external_id }}</small>
                                @endif
                            </td>
                            <td>{{ $product->brand ?? '—' }}</td>
                            <td>
                                {{ $product->category ?? '' }}
                                @if($product->product_group)
                                    <span class="badge bg-secondary">{{ $product->product_group }}</span>
                                @endif
                            </td>
                            <td>
                                @if($product->synced_at)
                                    <small class="text-muted" title="{{ $product->synced_at }}">
                                        {{ $product->synced_at->diffForHumans() }}
                                    </small>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if($products->hasPages())
        <div class="card-footer bg-white">
            {{ $products->links() }}
        </div>
        @endif
    </div>
@endif
@endsection

@push('scripts')
<script>
    const syncForm    = document.getElementById('syncForm');
    const groupSelect = document.getElementById('productGroupSelect');
    const modal       = new bootstrap.Modal(document.getElementById('loadingModal'));

    syncForm.addEventListener('submit', function (e) {
        const selected = groupSelect.options[groupSelect.selectedIndex];
        if (!selected || !selected.value) return; // let HTML validation handle it

        const label = selected.dataset.label || selected.text;
        document.getElementById('loadingGroupLabel').textContent = label;

        modal.show();
    });
</script>
@endpush
