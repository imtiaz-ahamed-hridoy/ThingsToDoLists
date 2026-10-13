@extends('backend.app')

@section('title', 'Edit Product')

@push('styles')
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
            <h1>Edit Product</h1>
            <p>{{ \Illuminate\Support\Str::limit($product->title ?? 'Update product details', 110) }}</p>
            <span class="product-editor-status">{{ ucfirst($product->status ?? 'draft') }}</span>
        </div>
        <a href="{{ route('product.index') }}" class="btn btn-outline-primary">
            <i class="fe fe-arrow-left me-1"></i> Back to products
        </a>
    </div>

    <form method="POST"
          action="{{ route('product.update', $product) }}"
          enctype="multipart/form-data"
          class="product-editor-form">

        @csrf
        @method('PUT')

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
                    @include('backend.layouts.product.updateForm.categoryForm')
                </section>

                <section id="section-barcode" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.barcodeForm')
                </section>

                <section id="section-vendor" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.vendorForm')
                </section>

                <section id="section-details" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.productForm')
                </section>

                <section id="section-itinerary" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.itineraryForm')
                </section>

                <section id="section-attributes" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.activityAttributesForm')
                </section>

                <section id="section-location" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.locationForm')
                </section>

                <section id="section-pricing" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.pricingForm')
                </section>

                <section id="section-cancellation" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.cancellationPolicyForm')
                </section>

                <section id="section-questions" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.bookingQuestionForm')
                    @include('backend.layouts.product.updateForm.passengerFields')
                    @include('backend.layouts.product.updateForm.hotelContactFields')
                </section>

                <section id="section-keywords" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.keywordsForm')
                    @include('backend.layouts.product.updateForm.hotelKeywords')
                </section>

                <section id="section-media" class="product-editor-section">
                    @include('backend.layouts.product.updateForm.image')
                    @include('backend.layouts.product.updateForm.video')
                </section>
            </div>
        </div>

        <div class="product-editor-submitbar">
            <div>
                <strong>Save product changes?</strong>
                <span>Updates apply to product details, pricing, availability content, and media.</span>
            </div>
            <div class="product-editor-submitbar-actions">
                <a href="{{ route('product.index') }}" class="btn btn-light">
                    <i class="fa fa-times me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fa fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
