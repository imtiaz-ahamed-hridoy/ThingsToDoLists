@extends('backend.app')

@section('title', 'GlobalTix Health Check')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">GlobalTix Health Check</h1>
    </div>
    <div class="ms-auto pageheader-btn">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0);">System</a></li>
            <li class="breadcrumb-item active" aria-current="page">GlobalTix Health</li>
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
                    <h4 class="mb-2">GlobalTix API Connection Successful</h4>
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
                    <h4 class="mb-2">GlobalTix API Connection Failed</h4>
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
                                    <th style="width: 200px;" class="text-muted">Mode</th>
                                    <td><code>{{ $result['mode'] }}</code></td>
                                </tr>
                            @endif
                            @if(isset($result['base_url']))
                                <tr>
                                    <th class="text-muted">Active URL</th>
                                    <td><code>{{ $result['base_url'] }}</code></td>
                                </tr>
                            @endif
                            @if(isset($result['api_agent']))
                                <tr>
                                    <th class="text-muted">API Agent</th>
                                    <td><code>{{ $result['api_agent'] }}</code></td>
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
                            @if(isset($result['token_preview']))
                                <tr>
                                    <th class="text-muted">Token Preview</th>
                                    <td><code>{{ $result['token_preview'] }}</code></td>
                                </tr>
                            @endif
                            @if(isset($result['expires_in']))
                                <tr>
                                    <th class="text-muted">Expires In</th>
                                    <td>{{ $result['expires_in'] }} seconds</td>
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
                    GlobalTix Credentials
                </h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.globaltix.saveCredentials') }}" method="POST">
                    @csrf

                    {{-- Mode --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold">Environment Mode</label>
                        <div class="mt-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mode" id="modeProd" value="prod"
                                    {{ old('mode', $credentials['mode'] ?? 'prod') === 'prod' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modeProd">Production</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="mode" id="modeTest" value="test"
                                    {{ old('mode', $credentials['mode'] ?? 'prod') === 'test' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modeTest">Test / Sandbox</label>
                            </div>
                        </div>
                        @error('mode')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    {{-- Production Credentials --}}
                    <div class="border p-3 mb-3 bg-light">
                        <h5 class="mb-3 text-uppercase fs-12 fw-bold text-muted">Production Credentials</h5>

                        <div class="mb-3">
                            <label class="form-label">Base URL</label>
                            <input type="url" class="form-control" name="prod_base_url"
                                value="{{ old('prod_base_url', $credentials['prod_base_url'] ?? '') }}" required>
                            @error('prod_base_url') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">API User</label>
                            <input type="text" class="form-control" name="prod_api_user"
                                value="{{ old('prod_api_user', $credentials['prod_api_user'] ?? '') }}" required>
                            @error('prod_api_user') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="prod_api_key" id="prod_api_key"
                                    value="{{ old('prod_api_key', $credentials['prod_api_key'] ?? '') }}" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleField('prod_api_key')">
                                    <i class="fe fe-eye"></i>
                                </button>
                            </div>
                            @error('prod_api_key') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label">API Agent</label>
                            <input type="text" class="form-control" name="prod_api_agent"
                                value="{{ old('prod_api_agent', $credentials['prod_api_agent'] ?? '') }}" required>
                            @error('prod_api_agent') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>

                    {{-- Test Credentials --}}
                    <div class="border p-3 mb-3 bg-light">
                        <h5 class="mb-3 text-uppercase fs-12 fw-bold text-muted">Test / Sandbox Credentials</h5>

                        <div class="mb-3">
                            <label class="form-label">Base URL</label>
                            <input type="url" class="form-control" name="test_base_url"
                                value="{{ old('test_base_url', $credentials['test_base_url'] ?? '') }}" required>
                            @error('test_base_url') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">API User</label>
                            <input type="text" class="form-control" name="test_api_user"
                                value="{{ old('test_api_user', $credentials['test_api_user'] ?? '') }}" required>
                            @error('test_api_user') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="test_api_key" id="test_api_key"
                                    value="{{ old('test_api_key', $credentials['test_api_key'] ?? '') }}" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleField('test_api_key')">
                                    <i class="fe fe-eye"></i>
                                </button>
                            </div>
                            @error('test_api_key') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label">API Agent</label>
                            <input type="text" class="form-control" name="test_api_agent"
                                value="{{ old('test_api_agent', $credentials['test_api_agent'] ?? '') }}" required>
                            @error('test_api_agent') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>

                    {{-- Additional Settings --}}
                    <div class="border p-3 mb-3 bg-light">
                        <h5 class="mb-3 text-uppercase fs-12 fw-bold text-muted">Additional Settings</h5>

                        <div class="mb-3">
                            <label class="form-label">Image Base URL</label>
                            <input type="url" class="form-control" name="image_url"
                                value="{{ old('image_url', $credentials['image_url'] ?? '') }}">
                            @error('image_url') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Webhook Secret</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="webhook_secret" id="webhook_secret"
                                    value="{{ old('webhook_secret', $credentials['webhook_secret'] ?? '') }}">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleField('webhook_secret')">
                                    <i class="fe fe-eye"></i>
                                </button>
                            </div>
                            @error('webhook_secret') <small class="text-danger">{{ $message }}</small> @enderror
                            <small class="text-muted">Used to verify incoming GlobalTix webhook signatures.</small>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Credentials
                        </button>
                        <a href="{{ route('admin.globaltix.health') }}" class="btn btn-outline-secondary">
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
