<style>
    .product-editor-page {
        --editor-border: #e6ebf3;
        --editor-muted: #68758a;
        --editor-surface: #ffffff;
        --editor-soft: #f7f9fc;
        padding-bottom: 96px;
    }

    .product-editor-hero {
        align-items: center;
        background: var(--editor-surface);
        border: 1px solid var(--editor-border);
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        display: flex;
        gap: 18px;
        justify-content: space-between;
        margin-bottom: 18px;
        padding: 20px 22px;
    }

    .product-editor-kicker {
        color: #3366d6;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .product-editor-hero h1 {
        color: #101828;
        font-size: 24px;
        font-weight: 700;
        line-height: 1.25;
        margin: 0;
    }

    .product-editor-hero p {
        color: var(--editor-muted);
        font-size: 14px;
        margin: 8px 0 0;
        max-width: 760px;
    }

    .product-editor-status {
        background: #eef4ff;
        border: 1px solid #d6e4ff;
        border-radius: 999px;
        color: #2454c6;
        display: inline-flex;
        font-size: 12px;
        font-weight: 700;
        margin-top: 10px;
        padding: 6px 10px;
    }

    .product-editor-layout {
        align-items: start;
        display: grid;
        gap: 18px;
        grid-template-columns: 230px minmax(0, 1fr);
    }

    .product-editor-sidebar {
        position: sticky;
        top: 92px;
    }

    .product-editor-nav {
        background: var(--editor-surface);
        border: 1px solid var(--editor-border);
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        padding: 12px;
    }

    .product-editor-nav-title {
        color: #101828;
        display: block;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 10px;
        padding: 0 4px;
    }

    .product-editor-nav a {
        align-items: center;
        border-radius: 7px;
        color: #48566d;
        display: flex;
        font-size: 13px;
        font-weight: 600;
        gap: 9px;
        padding: 9px 10px;
        text-decoration: none;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .product-editor-nav a:hover {
        background: #f0f5ff;
        color: #2454c6;
    }

    .product-editor-nav-number {
        align-items: center;
        background: #eef2f7;
        border-radius: 999px;
        color: #5d6b82;
        display: inline-flex;
        flex: 0 0 24px;
        font-size: 11px;
        height: 24px;
        justify-content: center;
        width: 24px;
    }

    .product-editor-section {
        scroll-margin-top: 96px;
    }

    .product-editor-main {
        min-width: 0;
    }

    .product-editor-page .card {
        border: 1px solid var(--editor-border);
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        margin-bottom: 16px !important;
        overflow: hidden;
    }

    .product-editor-page .card .card {
        background: var(--editor-soft);
        box-shadow: none;
    }

    .product-editor-page .card-header {
        background: var(--editor-surface);
        border-bottom: 1px solid var(--editor-border);
        padding: 15px 18px;
    }

    .product-editor-page .card-header h4 {
        color: #1d2939 !important;
        font-size: 16px;
        line-height: 1.35;
    }

    .product-editor-page .card-header small,
    .product-editor-page .text-muted {
        color: var(--editor-muted) !important;
    }

    .product-editor-page .card-body {
        padding: 18px;
    }

    .product-editor-page .form-label {
        color: #344054;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .product-editor-page .form-control,
    .product-editor-page .form-select {
        border-color: #d7dee9;
        border-radius: 7px;
        color: #182230;
        font-size: 13px;
        min-height: 40px;
    }

    .product-editor-page textarea.form-control {
        min-height: 96px;
    }

    .product-editor-page .form-control:focus,
    .product-editor-page .form-select:focus {
        border-color: #5b8def;
        box-shadow: 0 0 0 3px rgba(91, 141, 239, 0.12);
    }

    .product-editor-page .input-group .btn {
        min-height: 40px;
    }

    .product-editor-page .btn {
        border-radius: 7px;
        font-size: 13px;
        font-weight: 700;
    }

    .product-editor-page .btn-primary {
        box-shadow: 0 8px 18px rgba(51, 102, 214, 0.2);
    }

    .product-editor-submitbar {
        align-items: center;
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid var(--editor-border);
        border-radius: 10px;
        bottom: 18px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.14);
        display: flex;
        gap: 14px;
        justify-content: space-between;
        margin-top: 18px;
        padding: 12px 14px;
        position: sticky;
        z-index: 20;
    }

    .product-editor-submitbar strong {
        color: #101828;
        display: block;
        font-size: 14px;
    }

    .product-editor-submitbar span {
        color: var(--editor-muted);
        display: block;
        font-size: 12px;
        margin-top: 2px;
    }

    .product-editor-submitbar-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }

    @media (max-width: 991.98px) {
        .product-editor-layout {
            grid-template-columns: minmax(0, 1fr);
            max-width: 100%;
        }

        .product-editor-sidebar {
            position: static;
            min-width: 0;
            width: 100%;
        }

        .product-editor-nav {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px; /* Space for scrollbar */
        }

        .product-editor-nav-title {
            align-items: center;
            display: inline-flex;
            flex: 0 0 auto;
            margin: 0 4px 0 0;
        }

        .product-editor-nav a {
            flex: 0 0 auto;
            white-space: nowrap;
        }
    }

    @media (max-width: 575.98px) {
        .product-editor-hero,
        .product-editor-submitbar {
            align-items: stretch;
            flex-direction: column;
        }

        .product-editor-submitbar-actions,
        .product-editor-submitbar-actions .btn {
            width: 100%;
        }
    }
</style>
