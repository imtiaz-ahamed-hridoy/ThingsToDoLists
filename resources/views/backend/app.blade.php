<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="author" content="bdCalling IT Ltd.">
    <meta name="keywords" content="pure_water_innovation">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title')</title>

    @include('backend.partials.styles')
    @stack('styles')

    <style>
    /* ── Global Responsive Utilities ── */
    @media (min-width: 576px) {
        .w-sm-auto { width: auto !important; }
    }
    @media (min-width: 768px) {
        .w-md-auto { width: auto !important; }
    }

    /* Responsive table: break long text on all admin tables */
    .table td, .table th {
        word-break: break-word;
    }

    /* Smooth iOS scrolling for responsive tables */
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }

    /* Admin button groups: better spacing on small screens */
    .card-header .btn-group-responsive {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .card-header .btn-group-responsive .btn {
        white-space: normal;
        word-break: normal;
    }

    /* Hide vertical dividers in card headers on mobile */
    @media (max-width: 576px) {
        .card-header .vr {
            display: none;
        }
    }

    /* Pre blocks in admin: don't overflow on mobile */
    pre {
        white-space: pre-wrap;
        word-break: break-word;
    }

    /* Form labels: better spacing on mobile */
    .form-label {
        margin-bottom: 0.25rem;
    }

    /* Credential sections: responsive inner grids */
    .credential-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    @media (min-width: 768px) {
        .credential-grid-2 {
            grid-template-columns: 1fr 1fr;
        }
    }
    </style>

    {{-- ===== GLOBAL ADMIN RESPONSIVE STYLESHEET ===== --}}
    <style>
    /* ─────────────────────────────────────────────────────────────
       1. PAGE HEADER — wrap on mobile
    ───────────────────────────────────────────────────────────── */
    @media (max-width: 767.98px) {
        .page-header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 0.5rem;
        }
        .page-header .ms-auto {
            margin-left: 0 !important;
        }
        .page-header .breadcrumb {
            margin-bottom: 0;
        }
        .page-title {
            font-size: 1.2rem !important;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       2. CARD HEADER — flex-wrap for actions/buttons
    ───────────────────────────────────────────────────────────── */
    .card-header {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    /* Override inline style="display:flex" on card headers across all pages */
    .card-header[style*="flex"] {
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
        align-items: center !important;
    }

    @media (max-width: 575.98px) {
        .card-header,
        .card-header[style*="flex"] {
            flex-direction: column !important;
            align-items: flex-start !important;
        }
        .card-header .d-flex {
            flex-wrap: wrap;
        }
        .card-header .btn,
        .card-header a.btn {
            font-size: 0.75rem;
            padding: 0.3rem 0.6rem;
        }
        /* Buttons in btn-group inside card headers */
        .card-header .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }
        .card-header .btn-group .btn {
            border-radius: 0.375rem !important;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       3. FILTER FORMS — 2-col grid on xs, full on md
    ───────────────────────────────────────────────────────────── */
    .filter-form-row .col-md-2,
    .filter-form-row .col-md-3,
    .filter-form-row .col-md-4 {
        flex: 0 0 auto;
        width: 50%;
    }
    @media (max-width: 575.98px) {
        .filter-form-row .col-md-2,
        .filter-form-row .col-md-3,
        .filter-form-row .col-md-4 {
            width: 100%;
        }
    }
    @media (min-width: 768px) {
        .filter-form-row .col-md-2 { width: 16.6667%; }
        .filter-form-row .col-md-3 { width: 25%; }
        .filter-form-row .col-md-4 { width: 33.3333%; }
    }

    /* ─────────────────────────────────────────────────────────────
       4. STATS / SUMMARY CARDS — 2-col on mobile
    ───────────────────────────────────────────────────────────── */
    @media (max-width: 575.98px) {
        .col-xl-3.col-lg-6.col-md-6.col-sm-12,
        .col-xl-4.col-lg-6.col-md-6.col-sm-12 {
            width: 50% !important;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       5. DATATABLE WRAPPERS — prevent overflow
    ───────────────────────────────────────────────────────────── */
    .dataTables_wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .dataTables_wrapper .row > div {
        flex-wrap: wrap;
    }
    @media (max-width: 575.98px) {
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            width: 100%;
            margin-bottom: 0.5rem;
            text-align: left !important;
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            width: 100%;
            text-align: left !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.2rem 0.4rem;
            font-size: 0.75rem;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       6. BOOKING / PRODUCT DETAIL SHOW PAGES
    ───────────────────────────────────────────────────────────── */
    @media (max-width: 767.98px) {
        .detail-label-col {
            width: 40% !important;
        }
        .detail-value-col {
            width: 60% !important;
        }
        /* Two-column info grids → stack */
        .info-grid-2 {
            grid-template-columns: 1fr !important;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       7. BUTTON BARS / ACTION ROWS — wrap nicely
    ───────────────────────────────────────────────────────────── */
    .btn-toolbar {
        flex-wrap: wrap;
        gap: 0.4rem;
    }
    @media (max-width: 575.98px) {
        .btn-toolbar .btn,
        .action-btns .btn {
            width: 100%;
            margin-bottom: 0.25rem;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       8. SETTINGS / FORM PAGES — two-col → one-col on mobile
    ───────────────────────────────────────────────────────────── */
    @media (max-width: 767.98px) {
        .col-md-6, .col-md-4, .col-md-3, .col-md-8, .col-md-9 {
            flex: 0 0 auto;
        }
        /* Only force-100% for FORM contexts; not stat cards */
        .settings-form .col-md-6,
        .settings-form .col-md-4,
        .settings-form .col-md-8 {
            width: 100% !important;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       9. MODAL DIALOGS — full-width on mobile
    ───────────────────────────────────────────────────────────── */
    @media (max-width: 575.98px) {
        .modal-dialog {
            margin: 0.5rem;
        }
        .modal-footer {
            flex-direction: column;
            gap: 0.5rem;
        }
        .modal-footer .btn {
            width: 100%;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       10. SIDEBAR + APP LAYOUT — ensure no horizontal bleed
    ───────────────────────────────────────────────────────────── */
    .app-content,
    .main-content,
    .side-app,
    .main-container {
        min-width: 0;
        max-width: 100%;
        overflow-x: hidden;
    }
    .main-container.container-fluid {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    @media (max-width: 575.98px) {
        .main-container.container-fluid {
            padding-left: 0.625rem;
            padding-right: 0.625rem;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       11. BOOKING SHOW — info table rows
    ───────────────────────────────────────────────────────────── */
    @media (max-width: 575.98px) {
        table.table-sm td,
        table.table-sm th {
            font-size: 0.8rem;
            padding: 0.25rem 0.4rem;
        }
        /* Stack td/th in detail tables */
        .table-detail tr {
            display: flex;
            flex-wrap: wrap;
        }
        .table-detail td,
        .table-detail th {
            width: 100%;
        }
    }

    /* ─────────────────────────────────────────────────────────────
       12. GENERAL UTILITY — prevent any wide element from bleeding
    ───────────────────────────────────────────────────────────── */
    img {
        max-width: 100%;
        height: auto;
    }
    iframe {
        max-width: 100%;
    }
    pre {
        white-space: pre-wrap;
        word-break: break-word;
    }
    </style>
</head>

<body class="ltr app sidebar-mini">

    {{-- LOADER --}}
    <div id="global-loader">
        <img src="{{ asset('backend/images/loader.svg') }}" class="loader-img" alt="Loader">
    </div>
    {{-- LOADER --}}


    {{-- PAGE --}}
    <div class="page">
        <div class="page-main">
            {{-- Header --}}
            @include('backend.partials.header')
            {{-- Header --}}

            {{-- SIDEBAR --}}
            @include('backend.partials.sidebar')
            {{-- SIDEBAR --}}


            <div class="app-content main-content mt-0">
                <div class="side-app">
                    <div class="main-container container-fluid">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- page --}}


    {{-- BACK-TO-TOP --}}
    <a href="#top" id="back-to-top"><i class="fa fa-long-arrow-up"></i></a>

    @include('backend.partials.scripts')
</body>

</html>
