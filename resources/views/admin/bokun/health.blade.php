@extends('backend.app')

@section('title', 'Bokun Health Check')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Bokun Health Check</h1>
    </div>
    <div class="ms-auto pageheader-btn">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0);">System</a></li>
            <li class="breadcrumb-item active" aria-current="page">Bokun Health</li>
        </ol>
    </div>
</div>

{{-- Connection Status --}}
<div class="row">
    <div class="col-12">
        @if($result['success'] ?? false)
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <span class="badge bg-success" style="font-size: 1.2rem; padding: 0.6rem 1.5rem;">
                            <i class="fa fa-check-circle me-2"></i>Connected
                        </span>
                    </div>
                    <h4 class="mb-2">Bokun API Connection Successful</h4>
                    <p class="text-muted">{{ $result['message'] }}</p>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <span class="badge bg-danger" style="font-size: 1.2rem; padding: 0.6rem 1.5rem;">
                            <i class="fa fa-exclamation-circle me-2"></i>Disconnected
                        </span>
                    </div>
                    <h4 class="mb-2">Bokun API Connection Failed</h4>
                    <p class="text-muted">{{ $result['message'] ?? 'Unknown error' }}</p>
                    <p class="text-muted small mt-2">Update your credentials below and re-test the connection.</p>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Connection Details --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    Connection Details
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            @if(isset($result['mode']))
                                <tr>
                                    <th style="width: 200px;" class="text-muted">API Base URL</th>
                                    <td><code>{{ $result['mode'] }}</code></td>
                                </tr>
                            @endif
                            @if(isset($result['base_url']))
                                <tr>
                                    <th class="text-muted">Active URL</th>
                                    <td><code>{{ $result['base_url'] }}</code></td>
                                </tr>
                            @endif
                            @if(isset($result['suppliers']))
                                <tr>
                                    <th class="text-muted">Suppliers</th>
                                    <td>{{ $result['suppliers'] }}</td>
                                </tr>
                            @endif
                            @if(isset($result['active_ids']))
                                <tr>
                                    <th class="text-muted">Active IDs</th>
                                    <td>{{ number_format($result['active_ids']) }}</td>
                                </tr>
                            @endif
                            @if(isset($result['http_status']))
                                <tr>
                                    <th class="text-muted">HTTP Status</th>
                                    <td>
                                        <span class="badge bg-{{ ($result['success'] ?? false) ? 'success' : 'warning' }}">
                                            {{ $result['http_status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Credentials Settings Form --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title mb-0">
                    <i class="bi bi-gear me-2 text-warning"></i>
                    Bokun Credentials
                </h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.bokun.saveCredentials') }}" method="POST">
                    @csrf

                    <div class="border p-3 mb-3 bg-light">
                        <h5 class="mb-3 text-uppercase fs-12 fw-bold text-muted">API Credentials</h5>

                        <div class="mb-3">
                            <label class="form-label">Access Key</label>
                            <input type="text" class="form-control" name="access_key"
                                value="{{ old('access_key', $credentials['access_key'] ?? '') }}" required>
                            @error('access_key') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Secret Key</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="secret_key" id="secret_key"
                                    value="{{ old('secret_key', $credentials['secret_key'] ?? '') }}" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleField('secret_key')">
                                    <i class="fe fe-eye"></i>
                                </button>
                            </div>
                            @error('secret_key') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label">API Base URL</label>
                            <input type="url" class="form-control" name="base_url"
                                value="{{ old('base_url', $credentials['base_url'] ?? '') }}" required
                                placeholder="https://api.bokun.io">
                            @error('base_url') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Credentials
                        </button>
                        <a href="{{ route('admin.bokun.health') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-repeat me-1"></i>Re-test Connection
                        </a>
                    </div>

                    <div class="mt-3 text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Credentials are stored in the <code>.env</code> file. After saving, the config cache is cleared and new values take effect immediately.
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleField(fieldId) {
    var input = document.getElementById(fieldId);
    if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
    }
}
</script>
@endpush
