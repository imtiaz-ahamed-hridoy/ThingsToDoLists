@extends('backend.app')

@section('title', 'Product Details — ' . $product->title)

@section('content')
<div class="page-header">
    <div><h1 class="page-title">Product Details</h1></div>
    <div class="ms-auto pageheader-btn">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('product.index') }}">Products</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($product->title, 40) }}</li>
        </ol>
    </div>
</div>

{{-- Action Buttons --}}
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('product.edit', $product) }}" class="btn btn-primary">
        <i class="fe fe-edit me-1"></i> Edit Product
    </a>
    <a href="{{ route('product.index') }}" class="btn btn-secondary">
        <i class="fe fe-arrow-left me-1"></i> Back to List
    </a>
    @if($product->product_api_id)
        @php
            $showPlatform = strtolower((string) ($product->provider ?? $product->platform ?? 'globaltix'));
            $isGt = ($showPlatform === 'globaltix');
            $btnTitle = $isGt ? 'Live GlobalTix API (Raw Activity Data, Timeslots & Prices)' : 'Live Raw Activity Data';
        @endphp
        <button type="button" class="btn btn-teal text-white fs-14" style="background-color: #008080;" onclick="viewLiveProductRaw({{ $product->id }}, '{{ e($product->product_api_id) }}', '{{ e($showPlatform) }}', event)" title="{{ $btnTitle }}">
            <i class="fe fe-activity me-1"></i> Live GlobalTix API
        </button>
    @endif
</div>

{{-- ============================================================ --}}
{{-- HEADER CARD --}}
{{-- ============================================================ --}}
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 text-center">
                @if($product->thumbnail)
                    <img src="{{ filter_var($product->thumbnail, FILTER_VALIDATE_URL) ? $product->thumbnail : asset('storage/' . $product->thumbnail) }}"
                         alt="{{ $product->title }}"
                         class="img-fluid shadow-sm"
                         style="max-height: 200px; object-fit: cover;">
                @else
                    <div class="bg-light d-flex align-items-center justify-content-center " style="height:200px;">
                        <i class="fe fe-image text-muted" style="font-size: 3rem;"></i>
                    </div>
                @endif
            </div>
            <div class="col-md-9">
                <h2 class="mb-2">{{ $product->title }}</h2>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-warning text-dark fs-12 fw-bold">DB ID: #{{ $product->id }}</span>
                    <span class="badge bg-{{ $product->status === 'active' ? 'success' : ($product->status === 'draft' ? 'warning' : 'secondary') }} fs-12">
                        {{ strtoupper($product->status ?? 'N/A') }}
                    </span>
                    <span class="badge bg-primary fs-12">{{ ucfirst($product->provider ?? $product->platform ?? 'Manual') }}</span>
                    @if($product->product_api_id)
                        <span class="badge bg-dark fs-12">API ID: {{ $product->product_api_id }}</span>
                    @endif
                    @if($product->bookingType)
                        <span class="badge bg-info fs-12">{{ $product->bookingType }}</span>
                    @endif
                    @if($product->activityType)
                        <span class="badge bg-purple fs-12">{{ $product->activityType }}</span>
                    @endif
                </div>

                <table class="table table-sm table-borderless mb-0" style="max-width: 500px;">
                    <tr><td class="fw-bold text-muted" style="width:160px;">Category</td><td>{{ $product->category->name ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold text-muted">Vendor</td><td>{{ $product->vendor->name ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold text-muted">Barcode Type</td><td>{{ $product->barcodeType->name ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold text-muted">Slug</td><td><code>{{ $product->slug }}</code></td></tr>
                    <tr><td class="fw-bold text-muted">Published</td><td>{!! $product->published ? '<i class="fe fe-check-circle text-success"></i> Yes' : '<i class="fe fe-x-circle text-danger"></i> No' !!}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- CORE DETAILS --}}
{{-- ============================================================ --}}
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h4 class="card-title mb-0">Description</h4></div>
            <div class="card-body">
                {!! $product->description ?? '<span class="text-muted">No description available.</span>' !!}
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h4 class="card-title mb-0">Configuration</h4></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><td class="fw-bold">Duration</td><td>{{ $product->duration ?: 'N/A' }} {{ $product->durationType }}</td></tr>
                    @if($product->durationMinutes)<tr><td class="fw-bold">Duration (min)</td><td>{{ $product->durationMinutes }} minutes</td></tr>@endif
                    @if($product->durationHours)<tr><td class="fw-bold">Duration (hrs)</td><td>{{ $product->durationHours }} hours</td></tr>@endif
                    @if($product->durationText)<tr><td class="fw-bold">Duration Text</td><td>{{ $product->durationText }}</td></tr>@endif
                    <tr><td class="fw-bold">Difficulty</td><td>{{ $product->difficulty ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold">Activity Type</td><td>{{ $product->activityType ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold">Schedule Type</td><td>{{ $product->scheduleType ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold">Booking Type</td><td>{{ $product->bookingType ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold">Meeting Type</td><td>{{ $product->meetingType ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold">Capacity Type</td><td>{{ $product->capacityType ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold">Pass Capacity</td><td>{{ $product->passCapacity ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold">Passes Available</td><td>{{ $product->passesAvailable ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold">Time Zone</td><td>{{ $product->time_zone ?? 'N/A' }}</td></tr>
                    <tr><td class="fw-bold">Review Rating</td><td>{{ $product->reviewRating ?? 'N/A' }}</td></tr>
                    @if($product->reviewCount)<tr><td class="fw-bold">Review Count</td><td>{{ $product->reviewCount }}</td></tr>@endif
                    @if($product->minAge)<tr><td class="fw-bold">Min Age</td><td>{{ $product->minAge }}</td></tr>@endif
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- CUTOFF & BOOKING LIMITS --}}
{{-- ============================================================ --}}
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Booking Cutoff & Limits</h4></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tr><td class="fw-bold" style="width:250px;">Cutoff Type</td><td>{{ $product->cutoffType ?? 'N/A' }}</td></tr>
            <tr><td class="fw-bold">Cutoff Reference Hour</td><td>{{ $product->cutoffReferenceHour ?? 'N/A' }}</td></tr>
            <tr><td class="fw-bold">Cutoff Reference Minute</td><td>{{ $product->cutoffReferenceMinute ?? 'N/A' }}</td></tr>
            @if($product->bookingCutoffMinutes)<tr><td class="fw-bold">Booking Cutoff (minutes)</td><td>{{ $product->bookingCutoffMinutes }}</td></tr>@endif
            @if($product->bookingCutoffHours)<tr><td class="fw-bold">Booking Cutoff (hours)</td><td>{{ $product->bookingCutoffHours }}</td></tr>@endif
            @if($product->bookingCutoffDays)<tr><td class="fw-bold">Booking Cutoff (days)</td><td>{{ $product->bookingCutoffDays }}</td></tr>@endif
            @if($product->bookingCutoffWeeks)<tr><td class="fw-bold">Booking Cutoff (weeks)</td><td>{{ $product->bookingCutoffWeeks }}</td></tr>@endif
        </table>
    </div>
</div>

{{-- ============================================================ --}}
{{-- FLAGS & REQUIREMENTS --}}
{{-- ============================================================ --}}
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Flags & Requirements</h4></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tr><td class="fw-bold" style="width:250px;">Passport Required</td><td>{!! $product->passportRequired ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td></tr>
            <tr><td class="fw-bold">Allow Customized Bookings</td><td>{!! $product->allowCustomizedBookings ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td></tr>
            <tr><td class="fw-bold">Private Activity</td><td>{!! $product->privateActivity ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td></tr>
            <tr><td class="fw-bold">Pickup Service</td><td>{{ $product->pickup_service ?: ($product->pickupService ?: 'N/A') }}</td></tr>
            <tr><td class="fw-bold">Dropoff Service</td><td>{{ $product->dropoffService ?: 'N/A' }}</td></tr>
            <tr><td class="fw-bold">Private Tour</td><td>{{ $product->private_tour ?: 'N/A' }}</td></tr>
            @if($product->dressCode)<tr><td class="fw-bold">Dress Code</td><td>{{ $product->dressCode }}</td></tr>@endif
        </table>
    </div>
</div>

{{-- ============================================================ --}}
{{-- LOCATION --}}
{{-- ============================================================ --}}
@if($product->location)
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Location</h4></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tr><td class="fw-bold" style="width:250px;">City</td><td>{{ $product->location->city ?? 'N/A' }}</td></tr>
            <tr><td class="fw-bold">Country</td><td>{{ $product->location->country ?? 'N/A' }}</td></tr>
            <tr><td class="fw-bold">Country Code</td><td>{{ $product->location->countryCode ?? 'N/A' }}</td></tr>
            @if($product->location->coordinates)
                <tr><td class="fw-bold">Coordinates</td><td>
                    Lat: {{ $product->location->coordinates['latitude'] ?? 'N/A' }},
                    Lng: {{ $product->location->coordinates['longitude'] ?? 'N/A' }}
                </td></tr>
            @endif
        </table>
    </div>
</div>
@endif

{{-- ============================================================ --}}
{{-- PRICING --}}
{{-- ============================================================ --}}
@if($product->price)
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Pricing</h4></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tr><td class="fw-bold" style="width:250px;">Amount</td><td>{{ number_format($product->price->amount, 2) }} {{ $product->price->defaultCurrency }}</td></tr>
            <tr><td class="fw-bold">From</td><td>{{ $product->price->from ?? 'N/A' }}</td></tr>
            <tr><td class="fw-bold">To</td><td>{{ $product->price->to ?? 'N/A' }}</td></tr>
            <tr><td class="fw-bold">Price Converted</td><td>{{ $product->price->isPriceConverted ? 'Yes' : 'No' }}</td></tr>
        </table>
    </div>
</div>
@endif

{{-- ============================================================ --}}
{{-- INCLUSIONS / EXCLUSIONS --}}
{{-- ============================================================ --}}
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h4 class="card-title mb-0">Inclusions</h4></div>
            <div class="card-body">
                @if(!empty($product->inclusions))
                    <ul class="mb-0">
                        @foreach($product->inclusions as $item)
                            <li>{{ is_array($item) ? json_encode($item) : $item }}</li>
                        @endforeach
                    </ul>
                @elseif($product->included)
                    {!! is_array($product->included) ? implode('<br>', $product->included) : $product->included !!}
                @else
                    <span class="text-muted">None</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h4 class="card-title mb-0">Exclusions</h4></div>
            <div class="card-body">
                @if(!empty($product->exclusions))
                    <ul class="mb-0">
                        @foreach($product->exclusions as $item)
                            <li>{{ is_array($item) ? json_encode($item) : $item }}</li>
                        @endforeach
                    </ul>
                @elseif($product->excluded)
                    {!! is_array($product->excluded) ? implode('<br>', $product->excluded) : $product->excluded !!}
                @else
                    <span class="text-muted">None</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- MEDIA: IMAGES --}}
{{-- ============================================================ --}}
@if($product->images && $product->images->isNotEmpty())
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Images ({{ $product->images->count() }})</h4></div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($product->images as $image)
                <div class="col-md-3 col-sm-4 col-6">
                    @php
                        $imgUrl = $image->original ?? $image->large ?? $image->preview;
                        $imgSrc = filter_var($imgUrl, FILTER_VALIDATE_URL) ? $imgUrl : ($imgUrl ? asset('storage/' . $imgUrl) : null);
                    @endphp
                    @if($imgSrc)
                        <img src="{{ $imgSrc }}" alt="Product Image" class="img-fluid shadow-sm" style="height:150px; width:100%; object-fit:cover;">
                    @else
                        <div class="bg-light d-flex align-items-center justify-content-center " style="height:150px;">
                            <i class="fe fe-image text-muted"></i>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- ============================================================ --}}
{{-- MEDIA: VIDEOS --}}
{{-- ============================================================ --}}
@if($product->videos && $product->videos->isNotEmpty())
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Videos ({{ $product->videos->count() }})</h4></div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($product->videos as $video)
                @php
                    $videoUrl      = $video['sourceUrl'] ?? null;
                    $embedHtml     = $video['html'] ?? null;
                    $isLocalVideo  = $videoUrl && !filter_var($videoUrl, FILTER_VALIDATE_URL) || str_contains($videoUrl ?? '', '/storage/');
                    $videoTitle    = $video['title'] ?? null;
                    $thumbnailUrl  = $video['thumbnailUrl'] ?? null;
                    $providerName  = $video['providerName'] ?? 'Watch Video';
                @endphp
                <div class="col-md-4">
                    <div class="border p-2">
                        @if($embedHtml)
                            {!! $embedHtml !!}
                        @elseif($videoUrl && $isLocalVideo)
                            <video controls style="width:100%;max-height:200px;border-radius:4px;">
                                <source src="{{ $videoUrl }}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        @elseif($videoUrl)
                            <a href="{{ $videoUrl }}" target="_blank" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fe fe-play me-1"></i> {{ $providerName }}
                            </a>
                        @endif

                        @if($videoTitle)
                            <small class="text-muted d-block mt-1">{{ $videoTitle }}</small>
                        @endif

                        @if($thumbnailUrl && !$embedHtml && !$isLocalVideo)
                            <img src="{{ $thumbnailUrl }}" alt="Thumbnail" class="img-fluid mt-2" style="max-height:100px;">
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif


{{-- ============================================================ --}}
{{-- CANCELLATION POLICY --}}
{{-- ============================================================ --}}
@if($product->cancellationPolicies && $product->cancellationPolicies->isNotEmpty())
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Cancellation Policies</h4></div>
    <div class="card-body">
        @foreach($product->cancellationPolicies as $policy)
            <div class="mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                <p class="mb-1"><strong>Policy:</strong> {{ $policy->policy }}</p>
                @if($policy->policyType)<p class="mb-1"><strong>Type:</strong> {{ $policy->policyType }}</p>@endif
                @if($policy->tax)<p class="mb-1"><strong>Tax:</strong> {{ $policy->tax }}</p>@endif

                @if($policy->details && $policy->details->isNotEmpty())
                    <table class="table table-sm table-bordered mt-2 mb-0">
                        <thead><tr><th>Cutoff Hours</th><th>Charge</th><th>Charge Type</th><th>Percentage</th></tr></thead>
                        <tbody>
                            @foreach($policy->details as $detail)
                                <tr>
                                    <td>{{ $detail->cutoffHours ?? 'N/A' }}</td>
                                    <td>{{ $detail->charge ?? 'N/A' }}</td>
                                    <td>{{ $detail->chargeType ?? 'N/A' }}</td>
                                    <td>{{ $detail->percentage ?? 'N/A' }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ============================================================ --}}
{{-- BOOKING QUESTIONS --}}
{{-- ============================================================ --}}
{{-- @if($product->bookingQuestions && $product->bookingQuestions->isNotEmpty())
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Booking Questions ({{ $product->bookingQuestions->count() }})</h4></div>
    <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Code</th>
                    <th>Data Type</th>
                    <th>Required</th>
                    <th>Personal Data</th>
                </tr>
            </thead>
            <tbody>
                @foreach($product->bookingQuestions as $q)
                    <tr>
                        <td>{{ $q->label ?? 'N/A' }}</td>
                        <td><code>{{ $q->questionCode ?? 'N/A' }}</code></td>
                        <td>{{ $q->dataType ?? 'N/A' }}</td>
                        <td>{!! $q->required ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                        <td>{!! $q->personalData ? '<span class="badge bg-warning">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif --}}

{{-- ============================================================ --}}
{{-- PASSENGER FIELDS --}}
{{-- ============================================================ --}}
{{-- @if($product->passengerFields && $product->passengerFields->isNotEmpty())
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Passenger Fields ({{ $product->passengerFields->count() }})</h4></div>
    <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0">
            <thead><tr><th>Field</th><th>Required</th><th>Required Before Departure</th></tr></thead>
            <tbody>
                @foreach($product->passengerFields as $field)
                    <tr>
                        <td>{{ $field->field }}</td>
                        <td>{!! $field->required ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                        <td>{!! $field->requiredBeforeDeparture ? '<span class="badge bg-warning">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif --}}

{{-- ============================================================ --}}
{{-- KEYWORDS --}}
{{-- ============================================================ --}}
@if($product->keywords && $product->keywords->isNotEmpty())
<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Keywords</h4></div>
    <div class="card-body">
        @foreach($product->keywords as $kw)
            <span class="badge bg-primary me-1 mb-1">{{ $kw->name }}</span>
        @endforeach
    </div>
</div>
@endif

{{-- ============================================================ --}}
{{-- BOKUN JSON ARRAYS --}}
{{-- ============================================================ --}}
@php
    $jsonSections = [
        'Activity Categories' => $product->activityCategories,
        'Activity Attributes' => $product->activityAttributes,
        'Highlights' => $product->highlights,
        'Itinerary' => $product->itinerary,
        'Guidance Types' => $product->guidanceTypes,
        'Start Times' => $product->startTimes,
        'Agenda Items' => $product->agendaItems,
        'Start Points' => $product->startPoints,
        'Rates' => $product->rates,
        'Pricing Categories' => $product->pricingCategories,
        'Bookable Extras' => $product->bookableExtras,
        'Know Before You Go' => $product->know_before_you_go,
    ];
@endphp

@foreach($jsonSections as $sectionTitle => $sectionData)
    @if(!empty($sectionData))
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#section-{{ Str::slug($sectionTitle) }}">
                <h4 class="card-title mb-0">{{ $sectionTitle }}</h4>
                <i class="fe fe-chevron-down"></i>
            </div>
            <div class="collapse" id="section-{{ Str::slug($sectionTitle) }}">
                <div class="card-body">
                    @if(is_array($sectionData) && !empty($sectionData))
                        @if(array_is_list($sectionData) && !is_array($sectionData[0] ?? null))
                            {{-- Simple list --}}
                            <ul class="mb-0">
                                @foreach($sectionData as $item)
                                    <li>{{ is_string($item) ? $item : json_encode($item) }}</li>
                                @endforeach
                            </ul>
                        @else
                            {{-- Complex array / objects beautifully formatted --}}
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                @php
                                    if (!function_exists('renderArrayAsTable')) {
                                        function renderArrayAsTable($array) {
                                            if (!is_array($array)) {
                                                if (is_bool($array)) return $array ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>';
                                                return htmlspecialchars((string)$array);
                                            }
                                            if (empty($array)) return '<span class="text-muted">Empty</span>';
                                            
                                            $html = '<table class="table table-sm table-bordered mb-0" style="font-size: 13px;">';
                                            $isList = array_is_list($array);
                                            
                                            foreach ($array as $key => $value) {
                                                $html .= '<tr>';
                                                if (!$isList) {
                                                    $titleCaseKey = ucwords(str_replace(['_', '-'], ' ', preg_replace('/(?<!^)([A-Z])/', ' $1', $key)));
                                                    $html .= '<th class="bg-light" style="width: 200px; word-break: break-all;">' . htmlspecialchars($titleCaseKey) . '</th>';
                                                }
                                                $html .= '<td>';
                                                if (is_array($value)) {
                                                    $html .= renderArrayAsTable($value);
                                                } else {
                                                    if (is_bool($value)) {
                                                        $html .= $value ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>';
                                                    } else {
                                                        $html .= htmlspecialchars((string) $value);
                                                    }
                                                }
                                                $html .= '</td></tr>';
                                            }
                                            $html .= '</table>';
                                            return $html;
                                        }
                                    }
                                @endphp
                                {!! renderArrayAsTable($sectionData) !!}
                            </div>
                        @endif
                    @else
                        <span class="text-muted">No data</span>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endforeach

{{-- ============================================================ --}}
{{-- RAW API RESPONSE --}}
{{-- ============================================================ --}}
@if($product->rawResponse)
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center cursor-pointer" data-bs-toggle="collapse" data-bs-target="#rawResponseSection">
        <h4 class="card-title mb-0">
            <i class="fe fe-code me-1"></i> Raw API Response
            <span class="badge bg-dark ms-2">{{ $product->rawResponse->provider }}</span>
            <span class="badge bg-secondary ms-1">API ID: {{ $product->rawResponse->product_api_id }}</span>
        </h4>
        <i class="fe fe-chevron-down"></i>
    </div>
    <div class="collapse" id="rawResponseSection">
        <div class="card-body p-0">
            @php
                $rawData = $product->rawResponse->raw_data ?? [];
                if (is_string($rawData)) {
                    $decoded = json_decode($rawData, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $rawData = $decoded;
                    }
                }
            @endphp
            <div class="position-relative">
                <button onclick="navigator.clipboard.writeText(document.getElementById('rawJsonData').textContent).then(() => { this.innerHTML = '<i class=\'fe fe-check ms-1\'></i> Copied'; setTimeout(() => this.innerHTML = '<i class=\'fe fe-copy ms-1\'></i> Copy', 2000); })" class="btn btn-sm btn-outline-light position-absolute" style="top: 10px; right: 10px; z-index: 10;">
                    <i class="fe fe-copy ms-1"></i> Copy
                </button>
                <pre id="rawJsonData" class="bg-dark text-light p-3 mb-0" style="max-height: 600px; overflow-y: auto; font-size: 12px; border-radius: 0 0 5px 5px;">{{ json_encode($rawData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Timestamps --}}
<div class="card">
    <div class="card-body py-2">
        <small class="text-muted">
            Created: {{ $product->created_at?->format('M d, Y H:i') ?? 'N/A' }} |
            Updated: {{ $product->updated_at?->format('M d, Y H:i') ?? 'N/A' }}
        </small>
    </div>
</div>

{{-- Live Raw Activity & Timeslots Modal --}}
@include('backend.layouts.tempProduct.partials.globaltix_raw_modal')

@endsection

@push('styles')
<style>
    .cursor-pointer { cursor: pointer; }
    .card-header[data-bs-toggle="collapse"] .fe-chevron-down {
        transition: transform 0.2s;
    }
    .card-header[data-bs-toggle="collapse"].collapsed .fe-chevron-down,
    .card-header[data-bs-toggle="collapse"][aria-expanded="false"] .fe-chevron-down {
        transform: rotate(-90deg);
    }
    /* Fix scrollable modal header/footer */
    .modal-dialog-scrollable .modal-content {
        display: flex !important;
        flex-direction: column !important;
        max-height: 100% !important;
    }
    .modal-dialog-scrollable .modal-header,
    .modal-dialog-scrollable .modal-footer {
        flex-shrink: 0 !important;
    }
    .modal-dialog-scrollable .modal-body {
        flex: 1 1 auto !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
    }
</style>
@endpush

@push('scripts')
    @include('backend.layouts.product.partials.globaltix_raw_scripts')
@endpush
