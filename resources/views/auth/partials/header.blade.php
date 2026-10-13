@php
    $systemSetting = App\Models\SystemSetting::first();
    $logo = $systemSetting && $systemSetting->logo
        ? url(\Illuminate\Support\Facades\Storage::url($systemSetting->logo))
        : null;
@endphp

<div class="col col-login mx-auto text-center">
    <a href="{{ route('welcome') }}">
        <img src="{{ $logo ?? asset('frontend/eVento_logo.png') }}"
            class="header-brand-img" alt="">
    </a>
</div>
