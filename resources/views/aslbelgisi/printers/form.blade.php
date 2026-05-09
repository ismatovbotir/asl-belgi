@extends('layouts.app')
@section('title', $printer ? 'Edit Printer' : 'Add Printer')

@section('content')
<div class="page-header">
    <h4 class="mb-0">
        <i class="bi bi-printer"></i>
        {{ $printer ? 'Edit Printer: ' . $printer->name : 'Add Printer' }}
    </h4>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ $printer ? route('asl.printers.update', $printer) : route('asl.printers.store') }}"
                      method="POST">
                    @csrf
                    @if($printer) @method('PUT') @endif

                    {{-- Name --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Printer Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $printer?->name) }}" placeholder="e.g. Warehouse Godex" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Printer Type --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Printer Type <span class="text-danger">*</span></label>
                        <select id="printer_type_id" name="printer_type_id"
                                class="form-select @error('printer_type_id') is-invalid @enderror" required>
                            <option value="">— Select type —</option>
                            @foreach($printerTypes as $type)
                                <option value="{{ $type->id }}"
                                    data-schema="{{ json_encode($type->parameters_schema) }}"
                                    {{ old('printer_type_id', $printer?->printer_type_id) == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                    @if($type->description) — {{ $type->description }} @endif
                                </option>
                            @endforeach
                        </select>
                        @error('printer_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Dynamic parameters --}}
                    <div id="dynamic-fields"></div>

                    {{-- Flags --}}
                    <div class="mb-3 d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                                   {{ old('is_active', $printer ? $printer->is_active : true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="set_default" id="set_default" value="1"
                                   {{ old('set_default', $printer?->is_default) ? 'checked' : '' }}>
                            <label class="form-check-label" for="set_default">Set as default printer</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> {{ $printer ? 'Save Changes' : 'Add Printer' }}
                        </button>
                        <a href="{{ route('asl.printers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Type info sidebar --}}
    <div class="col-lg-5">
        <div id="type-info-card" class="card border-0 shadow-sm d-none">
            <div class="card-header bg-transparent border-bottom-0 pt-3 pb-1">
                <small class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;">About this type</small>
            </div>
            <div class="card-body pt-1">
                <div id="type-info-text" class="text-muted" style="font-size:.875rem;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const savedParams = @json(old('parameters', $printer?->parameters ?? []));

const typeSelect = document.getElementById('printer_type_id');

typeSelect.addEventListener('change', function () {
    renderFields(this);
});

function renderFields(select) {
    const option = select.options[select.selectedIndex];
    const infoCard = document.getElementById('type-info-card');
    const infoText = document.getElementById('type-info-text');

    if (!option || !option.value) {
        document.getElementById('dynamic-fields').innerHTML = '';
        infoCard.classList.add('d-none');
        return;
    }

    // Show description
    const descParts = option.text.split(' — ');
    if (descParts.length > 1) {
        infoText.textContent = descParts.slice(1).join(' — ');
        infoCard.classList.remove('d-none');
    } else {
        infoCard.classList.add('d-none');
    }

    let schema = [];
    try { schema = JSON.parse(option.dataset.schema || '[]'); } catch (e) {}

    if (!schema.length) {
        document.getElementById('dynamic-fields').innerHTML =
            '<p class="text-muted fst-italic mb-3">No additional parameters required for this type.</p>';
        return;
    }

    let html = '<hr class="my-3"><p class="text-muted mb-3" style="font-size:.8rem;">CONNECTION PARAMETERS</p>';

    schema.forEach(field => {
        const savedVal = savedParams[field.key] ?? field.default ?? '';
        const req = field.required ? ' required' : '';
        const star = field.required ? ' <span class="text-danger">*</span>' : '';

        html += `<div class="mb-3">`;
        html += `<label class="form-label fw-semibold">${field.label}${star}</label>`;

        if (field.type === 'select') {
            html += `<select class="form-select" name="parameters[${field.key}]"${req}>`;
            (field.options || []).forEach(opt => {
                const sel = String(savedVal) === String(opt) ? ' selected' : '';
                html += `<option value="${opt}"${sel}>${opt}</option>`;
            });
            html += `</select>`;
        } else {
            const ph = field.placeholder ? ` placeholder="${field.placeholder}"` : '';
            html += `<input type="${field.type}" class="form-control" name="parameters[${field.key}]"`;
            html += ` value="${savedVal}"${req}${ph}>`;
        }

        if (field.hint) {
            html += `<div class="form-text text-muted">${field.hint}</div>`;
        }

        html += `</div>`;
    });

    document.getElementById('dynamic-fields').innerHTML = html;
}

// Trigger on load if type is already selected (create with old() or edit mode)
if (typeSelect.value) renderFields(typeSelect);
</script>
@endpush
