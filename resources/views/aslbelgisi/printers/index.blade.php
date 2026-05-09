@extends('layouts.app')
@section('title', 'Printers')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-0"><i class="bi bi-printer"></i> Printers</h4>
        <small class="text-muted">Manage label printers and their connection settings</small>
    </div>
    <a href="{{ route('asl.printers.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Add Printer
    </a>
</div>

@if($printers->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-printer" style="font-size:2.5rem;"></i>
            <p class="mt-3 mb-1">No printers configured yet.</p>
            <a href="{{ route('asl.printers.create') }}" class="btn btn-primary btn-sm mt-2">
                <i class="bi bi-plus-lg"></i> Add First Printer
            </a>
        </div>
    </div>
@else
    <div class="card border-0 shadow-sm">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:36px;"></th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Connection</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($printers as $printer)
                <tr>
                    <td class="text-center">
                        @if($printer->is_default)
                            <i class="bi bi-star-fill text-warning" title="Default printer"></i>
                        @else
                            <i class="bi bi-star text-secondary"></i>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $printer->name }}</strong>
                        @if($printer->is_default)
                            <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">DEFAULT</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-secondary">{{ $printer->printerType->name }}</span>
                    </td>
                    <td>
                        @php $params = $printer->parameters ?? []; @endphp
                        @if(isset($params['host']))
                            <code>{{ $params['host'] }}:{{ $params['port'] ?? 9100 }}</code>
                            @if(isset($params['language']))
                                <span class="badge bg-light text-dark ms-1">{{ $params['language'] }}</span>
                            @endif
                        @elseif(isset($params['printer_name']))
                            <code>{{ $params['printer_name'] }}</code>
                            @if(isset($params['language']))
                                <span class="badge bg-light text-dark ms-1">{{ $params['language'] }}</span>
                            @endif
                        @elseif(isset($params['unc_path']))
                            <code>{{ $params['unc_path'] }}</code>
                        @elseif(isset($params['output_path']))
                            <code>{{ $params['output_path'] }}</code>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($printer->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @unless($printer->is_default)
                            <form action="{{ route('asl.printers.default', $printer) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Set as default">
                                    <i class="bi bi-star"></i>
                                </button>
                            </form>
                        @endunless
                        <a href="{{ route('asl.printers.edit', $printer) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('asl.printers.destroy', $printer) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete printer \'{{ addslashes($printer->name) }}\'?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
