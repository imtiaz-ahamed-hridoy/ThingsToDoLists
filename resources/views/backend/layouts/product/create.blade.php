@extends('backend.app')

@section('title', 'Create Product')

@push('styles')

<!-- Dropify CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropify/dist/css/dropify.min.css">
@include('backend.layouts.product.partials.editorStyles')
    
@endpush

@section('content')
@php
    $productEditorSections = [
        ['id' => 'category', 'label' => 'Category'],
        ['id' => 'barcode', 'label' => 'Barcode'],
        ['id' => 'vendor', 'label' => 'Vendor'],
        ['id' => 'details', 'label' => 'Details'],
        ['id' => 'itinerary', 'label' => 'Itinerary'],
        ['id' => 'attributes', 'label' => 'Attributes'],
        ['id' => 'location', 'label' => 'Location'],
        ['id' => 'pricing', 'label' => 'Pricing'],
        ['id' => 'cancellation', 'label' => 'Cancellation'],
        ['id' => 'questions', 'label' => 'Questions'],
        ['id' => 'keywords', 'label' => 'Keywords'],
        ['id' => 'media', 'label' => 'Media'],
    ];
@endphp

<div class="product-editor-page">
    <div class="product-editor-hero">
        <div>
            <div class="product-editor-kicker">Product Management</div>
            <h1>Create Product</h1>
            <p>Build supplier, Bokun, or GlobalTix product data from one organized admin form.</p>
        </div>
        <a href="{{ route('product.index') }}" class="btn btn-outline-primary">
            <i class="fe fe-arrow-left me-1"></i> Back to products
        </a>
    </div>

    <form method="post" action="{{ route('product.store') }}" enctype="multipart/form-data" class="product-editor-form">
        @csrf

        <div class="product-editor-layout">
            <aside class="product-editor-sidebar">
                <nav class="product-editor-nav" aria-label="Product form sections">
                    <span class="product-editor-nav-title">Form sections</span>
                    @foreach($productEditorSections as $index => $section)
                        <a href="#section-{{ $section['id'] }}">
                            <span class="product-editor-nav-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            {{ $section['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="product-editor-main">
                <section id="section-category" class="product-editor-section">
                    @include('backend.layouts.product.form.categoryForm')
                </section>

                <section id="section-barcode" class="product-editor-section">
                    @include('backend.layouts.product.form.barcodeForm')
                </section>

                <section id="section-vendor" class="product-editor-section">
                    @include('backend.layouts.product.form.vendorForm')
                </section>

                <section id="section-details" class="product-editor-section">
                    @include('backend.layouts.product.form.productForm')
                </section>

                <section id="section-itinerary" class="product-editor-section">
                    @include('backend.layouts.product.form.itineraryForm')
                </section>

                <section id="section-attributes" class="product-editor-section">
                    @include('backend.layouts.product.form.activityAttributesForm')
                </section>

                <section id="section-location" class="product-editor-section">
                    @include('backend.layouts.product.form.locationForm')
                </section>

                <section id="section-pricing" class="product-editor-section">
                    @include('backend.layouts.product.form.pricingForm')
                </section>

                <section id="section-cancellation" class="product-editor-section">
                    @include('backend.layouts.product.form.cancellationPolicyForm')
                </section>

                <section id="section-questions" class="product-editor-section">
                    @include('backend.layouts.product.form.bookingQuestionForm')
                    @include('backend.layouts.product.form.passengerFields')
                    @include('backend.layouts.product.form.hotelContactFields')
                </section>

                <section id="section-keywords" class="product-editor-section">
                    @include('backend.layouts.product.form.keywordsForm')
                </section>

                <section id="section-media" class="product-editor-section">
                    @include('backend.layouts.product.form.image')
                    @include('backend.layouts.product.form.video')
                </section>
            </div>
        </div>

        <div class="product-editor-submitbar">
            <div>
                <strong>Ready to publish this product?</strong>
                <span>Review required fields, pricing, location, and media before saving.</span>
            </div>
            <div class="product-editor-submitbar-actions">
                <a href="{{ route('product.index') }}" class="btn btn-light">
                    <i class="fa fa-times me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fa fa-save me-1"></i> Create Product
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
