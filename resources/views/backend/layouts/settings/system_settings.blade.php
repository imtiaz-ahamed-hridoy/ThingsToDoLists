@extends('backend.app')

@section('title', 'System Settings')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">System Settings</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Settings</a></li>
                <li class="breadcrumb-item active" aria-current="page">System Settings</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}


    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">
                    <form method="post" action="{{ route('system.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="title" class="form-label">Title:</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror"
                                        name="title" placeholder="title" id="title"
                                        value="{{ old('title', $setting->title ?? '') }}">
                                    @error('title')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="systemName" class="form-label">System Name:</label>
                                    <input type="text" class="form-control @error('system_name') is-invalid @enderror"
                                        name="system_name" placeholder="System Name" id="systemName"
                                        value="{{ old('system_name', $setting->system_name ?? '') }}">
                                    @error('system_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="number" class="form-label">Phone Number:</label>
                                    <input type="text" class="form-control @error('number') is-invalid @enderror"
                                           name="number" placeholder="Enter Phone Number" id="number"
                                           value="{{ old('number', $setting->number ?? '') }}">
                                    @error('number')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="address" class="form-label">Address:</label>
                                    <input type="text" class="form-control @error('address') is-invalid @enderror"
                                           name="address" placeholder="Enter Address" id="address"
                                           value="{{ old('address', $setting->address ?? '') }}">
                                    @error('address')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email:</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                                           name="email" placeholder="Enter Email here" id="email"
                                           value="{{ old('email', $setting->email ?? '') }}">
                                    @error('email')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="copyRights" class="form-label">Copy Rights Text:</label>
                                    <input type="text" class="form-control @error('copyright_text') is-invalid @enderror"
                                           name="copyright_text" placeholder="Copy Rights Text" id="copyRights"
                                           value="{{ old('copyright_text', $setting->copyright_text ?? '') }}">
                                    @error('copyright_text')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="summernote" class="form-label">About System:</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="summernote" name="description"
                                placeholder="About System">{{ old('description', $setting->description ?? '') }}</textarea>
                            @error('description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="logo" class="form-label">Logo:</label>
                                    <input type="hidden" name="remove_logo" value="0">
                                    <input type="file" class="dropify @error('logo') is-invalid @enderror" name="logo"
                                        id="logo"
                                        data-default-file="@isset($setting){{ url(\Illuminate\Support\Facades\Storage::url($setting->logo)) }}@endisset">
                                    @error('logo')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="logo" class="form-label">Favicon:</label>
                                    <input type="hidden" name="remove_favicon" value="0">
                                    <input type="file" class="dropify @error('favicon') is-invalid @enderror"
                                        name="favicon" id="favicon"
                                        data-default-file="@isset($setting){{ url(\Illuminate\Support\Facades\Storage::url($setting->favicon)) }}@endisset">
                                    @error('favicon')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="facebook" class="form-label">Facebook:</label>
                                    <input type="facebook" class="form-control @error('facebook') is-invalid @enderror"
                                           name="facebook" placeholder="Enter facebook here" id="facebook"
                                           value="{{ old('facebook', $setting->facebook ?? '') }}">
                                    @error('facebook')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="twitter" class="form-label">Twitter:</label>
                                    <input type="text" class="form-control @error('twitter') is-invalid @enderror"
                                           name="twitter" placeholder="twitter" id="twitter"
                                           value="{{ old('twitter', $setting->twitter ?? '') }}">
                                    @error('twitter')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                             <div class="col-md-6">
                                <div class="form-group">
                                    <label for="linkedin" class="form-label">Linked In:</label>
                                    <input type="text" class="form-control @error('linkedin') is-invalid @enderror"
                                           name="linkedin" placeholder="linkedin" id="linkedin"
                                           value="{{ old('linkedin', $setting->linkedin ?? '') }}">
                                    @error('linkedin')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="instagram" class="form-label">Instagram:</label>
                                    <input type="text" class="form-control @error('instagram') is-invalid @enderror"
                                           name="instagram" placeholder="Enter instagram url" id="instagram"
                                           value="{{ old('instagram', $setting->instagram ?? '') }}">
                                    @error('instagram')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="whatsapp" class="form-label">WhatsApp:</label>
                                    <input type="text" class="form-control @error('whatsapp') is-invalid @enderror"
                                           name="whatsapp" placeholder="Enter whatsapp url" id="whatsapp"
                                           value="{{ old('whatsapp', $setting->whatsapp ?? '') }}">
                                    @error('whatsapp')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="telegram" class="form-label">Telegram:</label>
                                    <input type="text" class="form-control @error('telegram') is-invalid @enderror"
                                           name="telegram" placeholder="Enter telegram url" id="telegram"
                                           value="{{ old('telegram', $setting->telegram ?? '') }}">
                                    @error('telegram')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <h4 class="mb-1">Supplier Guide</h4>
                            <p class="text-muted mb-0">This content is used by the supplier dashboard home API.</p>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supplierGuideTitle" class="form-label">Guide Title:</label>
                                    <input type="text" class="form-control @error('supplier_guide_title') is-invalid @enderror"
                                           name="supplier_guide_title" placeholder="Enter supplier guide title" id="supplierGuideTitle"
                                           value="{{ old('supplier_guide_title', $setting->supplier_guide_title ?? '') }}">
                                    @error('supplier_guide_title')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supplierGuideMedia" class="form-label">Guide Image or Video:</label>
                                    <input type="file" class="form-control @error('supplier_guide_media') is-invalid @enderror"
                                           name="supplier_guide_media" id="supplierGuideMedia" accept="image/*,video/*">
                                    @error('supplier_guide_media')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                    @if(!empty($setting?->getRawOriginal('supplier_guide_media')))
                                        <small class="d-block mt-2">
                                            Current media:
                                            <a href="{{ url(\Illuminate\Support\Facades\Storage::url($setting->getRawOriginal('supplier_guide_media'))) }}" target="_blank">
                                                View uploaded file
                                            </a>
                                        </small>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="supplierGuideDescription" class="form-label">Guide Description:</label>
                            <textarea class="form-control @error('supplier_guide_description') is-invalid @enderror" rows="4"
                                      name="supplier_guide_description" id="supplierGuideDescription"
                                      placeholder="Enter supplier guide description">{{ old('supplier_guide_description', $setting->supplier_guide_description ?? '') }}</textarea>
                            @error('supplier_guide_description')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="supplierGuideLinkOrIframe" class="form-label">Guide Link or Iframe:</label>
                            <textarea class="form-control @error('supplier_guide_link_or_iframe') is-invalid @enderror" rows="4"
                                      name="supplier_guide_link_or_iframe" id="supplierGuideLinkOrIframe"
                                      placeholder="Paste a URL or iframe embed code">{{ old('supplier_guide_link_or_iframe', $setting->supplier_guide_link_or_iframe ?? '') }}</textarea>
                            @error('supplier_guide_link_or_iframe')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
