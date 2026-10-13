@extends('backend.app')

@section('title', 'Import Products (Bokun & GlobalTix)')

@push('styles')
<style>
/* Responsive width utilities for modals */
@media (min-width: 576px) {
    .w-sm-auto { width: auto !important; }
}
@media (min-width: 768px) {
    .w-md-auto { width: auto !important; }
}

/* Responsive table: smooth iOS scrolling */
.table-responsive {
    -webkit-overflow-scrolling: touch;
}

/* Break long words in table cells on mobile */
#datatable td {
    word-break: break-word;
}

/* Vertical divider hide on very small screens */
@media (max-width: 576px) {
    .card-header .vr {
        display: none;
    }
}

/* ── Fix scrollable modal header/footer ── */
/* The admin theme overrides Bootstrap's modal-dialog-scrollable behavior.
   We restore the flexbox layout that keeps header/footer fixed and body scrolling. */
.modal-dialog-scrollable .modal-content {
    display: flex !important;
    flex-direction: column !important;
    max-height: 100% !important;
}
.modal-dialog-scrollable .modal-header,
.modal-dialog-scrollable .modal-footer {
    flex-shrink: 0 !important;
    background: #fff;
}
.modal-dialog-scrollable .modal-body {
    flex: 1 1 auto !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
}
</style>
@endpush

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Import Products (Bokun & GlobalTix)</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Import Products</a></li>
                <li class="breadcrumb-item active" aria-current="page">List</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}

    <div class="row row-sm">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-bottom d-flex justify-content-end gap-2 flex-wrap">
                    <button class="btn btn-success" id="bulkImport">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Bulk Import
                    </button>
                    <button class="btn btn-danger" id="bulkDelete">
                        <i class="bi bi-trash me-1"></i> Bulk Delete
                    </button>

                    <div class="vr mx-1"></div>

                    <button class="btn btn-primary" onclick="openBokunSearchModal()">
                        <i class="bi bi-cloud-arrow-down me-1"></i> Import by Bokun Search
                    </button>
                    <button class="btn border border-danger text-danger bg-white" onclick="syncActiveBokunActivities()" id="bokunActiveBtn">
                        <i class="bi bi-activity me-1"></i> Bokun Active Activities
                    </button>

                    <div class="vr mx-1"></div>

                    <button class="btn btn-info text-white" onclick="openGlobalTixSearchModal()">
                        <i class="bi bi-globe me-1"></i> Import by GlobalTix
                    </button>
                    <button class="btn btn-outline-info" onclick="openSyncGlobalTixModal()">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync GlobalTix new products
                    </button>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered text-nowrap border-bottom w-100" id="datatable">
                            <thead>
                                <tr>
                                    <th class="wd-15p border-bottom-0"><input type="checkbox" id="selectAll" aria-label="Select all rows"></th>
                                    <th class="wd-15p border-bottom-0">#</th>
                                    <th class="wd-15p border-bottom-0">API ID</th>
                                    <th class="wd-15p border-bottom-0">Title</th>
                                    <th class="wd-15p border-bottom-0">Price</th>
                                    <th class="wd-15p border-bottom-0">City</th>
                                    <th class="wd-15p border-bottom-0">Provider</th>
                                    <th class="wd-15p border-bottom-0">Thumbnail</th>
                                    <th class="wd-15p border-bottom-0">Import Result</th>
                                    <th class="wd-15p border-bottom-0">Actions</th>
                                </tr>
                            </thead>
                            <tbody>                                    {{-- dynamic data will be inserted here by DataTables --}}
                                </tbody>
                            </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modals --}}
    @include('backend.layouts.tempProduct.partials.bokun_search_modal')
    @include('backend.layouts.tempProduct.partials.globaltix_search_modal')
    @include('backend.layouts.tempProduct.partials.globaltix_raw_modal')
    @include('backend.layouts.tempProduct.partials.view_modal')
    @include('backend.layouts.tempProduct.partials.sync_globaltix_modal')

@endsection

@push('scripts')
    @include('backend.layouts.tempProduct.partials.scripts')
@endpush
