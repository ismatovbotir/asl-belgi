@extends('layouts.app')
@section('title', 'Settings & Authorization')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-0"><i class="bi bi-gear"></i> Settings & Authorization</h4>
        <small class="text-muted">
            Credentials stored in:
            @if($source === 'database')
            <span class="badge bg-success">Database <i class="bi bi-database-fill"></i></span>
            @else
            <span class="badge bg-warning text-dark">.env file</span>
            — click Edit to move them to the database
            @endif
        </small>
    </div>
</div>

<div class="row g-4">

    {{-- ── Left column ─────────────────────────────────────────── --}}
    <div class="col-md-7">

        {{-- Workflow --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-rocket"></i> Workflow
            </div>
            <div class="card-body p-2">
                <div class="d-flex gap-2 flex-wrap justify-content-center">
                    {{-- Step 1 always visible --}}
                    <a href="{{ route('asl.settings') }}" class="text-decoration-none text-center" style="min-width:80px;">
                        <div class="rounded p-2 border border-primary bg-primary bg-opacity-10">
                            <i class="bi bi-gear text-primary fs-5"></i>
                            <div style="font-size:0.7rem;" class="mt-1 text-primary fw-semibold">1. Auth</div>
                        </div>
                    </a>
                    {{-- Steps 2-4 only when credentials are configured --}}
                    @if($configured)
                        @foreach([
                            ['box-seam', 'info',    '2. Products', route('asl.products.index')],
                            ['list-ol',  'warning', '3. Orders',   route('asl.orders.index')],
                            ['printer',  'success', '4. Labels',   route('asl.labels.index')],
                        ] as [$icon, $color, $label, $url])
                        <a href="{{ $url }}" class="text-decoration-none text-center" style="min-width:80px;">
                            <div class="rounded p-2 border border-{{ $color }} bg-{{ $color }} bg-opacity-10">
                                <i class="bi bi-{{ $icon }} text-{{ $color }} fs-5"></i>
                                <div style="font-size:0.7rem;" class="mt-1 text-{{ $color }} fw-semibold">{{ $label }}</div>
                            </div>
                        </a>
                        @endforeach
                    @else
                        @foreach([
                            ['2. Products', 'info'],
                            ['3. Orders',   'warning'],
                            ['4. Labels',   'success'],
                        ] as [$label, $color])
                        <div class="text-center" style="min-width:80px;">
                            <div class="rounded p-2 border border-secondary bg-secondary bg-opacity-10">
                                <i class="bi bi-lock text-secondary fs-5"></i>
                                <div style="font-size:0.7rem;" class="mt-1 text-secondary fw-semibold">{{ $label }}</div>
                            </div>
                        </div>
                        @endforeach
                        <div class="w-100 text-center mt-2">
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Save TIN and API Key to unlock the remaining steps.</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Credentials (read-only) --}}
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-database-lock"></i> API Credentials</span>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCredentialsModal">
                    <i class="bi bi-pencil"></i> Edit
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="ps-3 text-muted" style="width:40%">Source</th>
                        <td>
                            @if($source === 'database')
                                <span class="text-success fw-semibold">Database</span>
                            @else
                                <span class="text-warning fw-semibold">.env</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-3 text-muted">Base URL</th>
                        <td><code style="font-size:0.72rem;">{{ $current['base_url'] ?: '—' }}</code></td>
                    </tr>
                    <tr>
                        <th class="ps-3 text-muted">TIN / INN / PINFL</th>
                        <td>{{ $current['tin'] ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th class="ps-3 text-muted">API Key</th>
                        <td>
                            @if($current['api_key'])
                                <code>{{ substr($current['api_key'], 0, 8) }}••••</code>
                                <span class="badge bg-success ms-1" style="font-size:0.65rem;">Encrypted</span>
                            @else
                                <span class="badge bg-danger">Not set</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="ps-3 text-muted">Timeout</th>
                        <td>{{ $current['timeout'] ?? 30 }}s</td>
                    </tr>
                </table>
            </div>
        </div>

    </div>

    {{-- ── Connection Tests ─────────────────────────────────────── --}}
    <div class="col-md-5">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-plug"></i> Test Connection
            </div>
            <div class="card-body d-flex flex-column gap-3">

                <div class="border rounded p-3">
                    <div class="fw-semibold small mb-1">
                        <i class="bi bi-key text-primary"></i> Business API Key
                    </div>
                    <p class="text-muted small mb-2">Verifies TIN + API key against ASL BELGISI.</p>
                    <form method="POST" action="{{ route('asl.auth.check') }}">
                        @csrf
                        <button class="btn btn-sm btn-primary">
                            <i class="bi bi-check-circle"></i> Test API Key
                        </button>
                    </form>
                </div>

                <div class="border rounded p-3 border-warning">
                    <div class="fw-semibold small mb-1">
                        <i class="bi bi-arrow-clockwise text-warning"></i> Refresh API Key
                    </div>
                    <p class="text-muted small mb-2">Generates a new API key via ASL BELGISI and <strong>automatically saves it to the database</strong>.</p>
                    <form method="POST" action="{{ route('asl.auth.refresh') }}">
                        @csrf
                        <button class="btn btn-sm btn-warning"
                            onclick="return confirm('This invalidates the current API key and saves the new one to DB. Proceed?')">
                            <i class="bi bi-key"></i> Refresh & Save
                        </button>
                    </form>
                </div>

            </div>
        </div>

    </div>
</div>

{{-- ── Edit Credentials Modal ───────────────────────────────── --}}
<div class="modal fade" id="editCredentialsModal" tabindex="-1" aria-labelledby="editCredentialsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="credentialsForm" method="POST" action="{{ route('asl.settings.save') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="editCredentialsModalLabel">
                        <i class="bi bi-database-lock"></i> Edit API Credentials
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    {{-- Test result banner --}}
                    <div id="testResult" class="mb-3" style="display:none;"></div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Base URL</label>
                        <input type="url" name="base_url" id="f_base_url"
                               class="form-control @error('base_url') is-invalid @enderror"
                               value="{{ old('base_url', $current['base_url']) }}" required>
                        @error('base_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Always use the stage URL. Never production.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">TIN / INN / PINFL</label>
                        <input type="text" name="tin" id="f_tin"
                               class="form-control @error('tin') is-invalid @enderror"
                               value="{{ old('tin', $current['tin']) }}"
                               placeholder="e.g. 303414502" required>
                        @error('tin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            API Key
                            @if($current['api_key'])
                                <span class="badge bg-success ms-1">Set</span>
                            @else
                                <span class="badge bg-danger ms-1">Not set</span>
                            @endif
                        </label>
                        <div class="input-group">
                            <input type="text" name="api_key" id="f_api_key"
                                   class="form-control font-monospace"
                                   value="{{ $current['api_key'] ?? '' }}"
                                   placeholder="{{ $current['api_key'] ? '' : 'Enter API key UUID' }}">
                            
                        </div>
                        <div class="form-text">Encrypted with AES-256 before storage. Never logged.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Request Timeout (seconds)</label>
                        <input type="number" name="timeout" id="f_timeout"
                               class="form-control" style="max-width:120px;"
                               value="{{ old('timeout', $current['timeout'] ?? 30) }}"
                               min="5" max="120">
                    </div>

                </div>

                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary" id="testCredsBtn">
                            <i class="bi bi-plug"></i> Test Connection
                        </button>
                        <button type="submit" class="btn btn-success" id="saveCredsBtn" disabled>
                            <i class="bi bi-floppy"></i> Save to Database
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CREDS_FIELDS = ['f_base_url', 'f_tin', 'f_api_key', 'f_timeout'];

function setFieldsLocked(locked) {
    CREDS_FIELDS.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.readOnly = locked;
    });
}

function showTestResult(success, message) {
    var result = document.getElementById('testResult');
    var cls = success ? 'alert-success' : 'alert-danger';
    var icon = success ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
    result.innerHTML = '<div class="alert ' + cls + ' py-2 mb-0"><i class="bi ' + icon + ' me-1"></i>' + message + '</div>';
    result.style.display = '';
}

function resetModal() {
    setFieldsLocked(false);
    document.getElementById('saveCredsBtn').disabled = true;
    var result = document.getElementById('testResult');
    result.style.display = 'none';
    result.innerHTML = '';
    var btn = document.getElementById('testCredsBtn');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-plug"></i> Test Connection';
}

document.getElementById('testCredsBtn').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Testing...';

    document.getElementById('testResult').style.display = 'none';

    var formData = new FormData(document.getElementById('credentialsForm'));

    fetch('{{ route("asl.auth.test") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData,
    })
    .then(function (resp) {
        return resp.text().then(function (text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Server error (HTTP ' + resp.status + ')');
            }
        });
    })
    .then(function (data) {
        showTestResult(data.success, data.message);
        if (data.success) {
            setFieldsLocked(true);
            document.getElementById('saveCredsBtn').disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Tested';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plug"></i> Test Connection';
        }
    })
    .catch(function (e) {
        showTestResult(false, e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plug"></i> Test Connection';
    });
});

document.getElementById('editCredentialsModal').addEventListener('show.bs.modal', resetModal);

@if($errors->any())
    new bootstrap.Modal(document.getElementById('editCredentialsModal')).show();
@endif
</script>
@endpush
