<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ASL BELGISI') — Marking System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6f9; }
        .sidebar {
            min-height: 100vh;
            background: #1a2332;
            width: 220px;
            position: fixed;
            top: 0; left: 0;
            padding-top: 1rem;
            z-index: 100;
        }
        .sidebar .brand {
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 0.75rem 1.25rem 1.5rem;
            border-bottom: 1px solid #2d3f55;
            display: block;
        }
        .sidebar .brand small { display: block; font-size: 0.7rem; color: #8fa3b1; font-weight: 400; margin-top: 2px; }
        .sidebar .nav-link {
            color: #b8c8d8;
            padding: 0.6rem 1.25rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #2d3f55;
            color: #fff;
        }
        .sidebar .nav-section {
            color: #5a7a96;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 1rem 1.25rem 0.25rem;
        }
        .main-content {
            margin-left: 220px;
            padding: 1.5rem;
        }
        .page-header {
            background: #fff;
            border-bottom: 1px solid #e0e6ed;
            padding: 1rem 1.5rem;
            margin: -1.5rem -1.5rem 1.5rem;
        }
        .status-badge { font-size: 0.75rem; font-weight: 600; }

        /* km_orders statuses (uppercase) */
        .badge-PENDING    { background: #ffc107; color: #000; }
        .badge-READY      { background: #0dcaf0; color: #000; }
        .badge-DOWNLOADED { background: #198754; color: #fff; }
        .badge-DONE       { background: #0d6efd; color: #fff; }
        .badge-CLOSED     { background: #6c757d; color: #fff; }
        .badge-DEPLETED   { background: #fd7e14; color: #fff; }

        /* km_codes lifecycle statuses (lowercase) */
        .badge-available      { background: #198754; color: #fff; }
        .badge-printed        { background: #6c757d; color: #fff; }
        .badge-applied        { background: #0d6efd; color: #fff; }
        .badge-aggregated     { background: #fd7e14; color: #fff; }
        .badge-in_circulation { background: #0dcaf0; color: #000; }
        .badge-spoiled        { background: #dc3545; color: #fff; }
        .badge-withdrawn      { background: #343a40; color: #fff; }
    </style>
    @stack('styles')
</head>
<body>

<nav class="sidebar">
    <a class="brand" href="{{ route('asl.settings') }}">
        ASL BELGISI
        <small>Marking Management</small>
    </a>

    @php
        $appConfigured = (bool) \App\Models\Setting::get('aslbelgisi_api_key')
                      && (bool) \App\Models\Setting::get('aslbelgisi_tin');
    @endphp

    <div class="nav-section">System</div>
    <a class="nav-link {{ request()->routeIs('asl.settings') ? 'active' : '' }}" href="{{ route('asl.settings') }}">
        <i class="bi bi-gear"></i> Settings / Auth
    </a>

    @if($appConfigured)
        <div class="nav-section">Catalog</div>
        <a class="nav-link {{ request()->routeIs('asl.products*') ? 'active' : '' }}" href="{{ route('asl.products.index') }}">
            <i class="bi bi-box-seam"></i> Products
        </a>

        <div class="nav-section">Marking Codes</div>
        <a class="nav-link {{ request()->routeIs('asl.orders*') ? 'active' : '' }}" href="{{ route('asl.orders.index') }}">
            <i class="bi bi-list-ol"></i> Orders
        </a>

        <div class="nav-section">Output</div>
        <a class="nav-link {{ request()->routeIs('asl.labels*') && !request()->routeIs('asl.label-templates*') ? 'active' : '' }}" href="{{ route('asl.labels.index') }}">
            <i class="bi bi-printer"></i> Label Design
        </a>
        <a class="nav-link {{ request()->routeIs('asl.label-templates*') ? 'active' : '' }}" href="{{ route('asl.label-templates.index') }}">
            <i class="bi bi-layout-text-window-reverse"></i> Label Templates
        </a>
    @else
        <div class="nav-section">Catalog</div>
        <span class="nav-link text-secondary" style="opacity:.45;cursor:default;">
            <i class="bi bi-lock"></i> Products
        </span>

        <div class="nav-section">Marking Codes</div>
        <span class="nav-link text-secondary" style="opacity:.45;cursor:default;">
            <i class="bi bi-lock"></i> Orders
        </span>

        <div class="nav-section">Output</div>
        <span class="nav-link text-secondary" style="opacity:.45;cursor:default;">
            <i class="bi bi-lock"></i> Label Design
        </span>
    @endif

    <div class="mt-4 px-3">
        <small class="text-secondary" style="font-size:0.68rem;">
            Stage: xtrace.stage.aslbelgisi.uz<br>
            v1.26.0 | 2026-05-06
        </small>
    </div>
</nav>

<div class="main-content">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle"></i> {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle"></i> {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
