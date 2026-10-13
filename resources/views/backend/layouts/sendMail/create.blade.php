@extends('backend.app')

@section('title', 'Mail Template Create')

@section('content')
    <style>
        input[type='checkbox'] {
            width: 17px;
            height: 17px;
            cursor: pointer;
        }
    </style>
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Mail Template Create Form</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Mail</a></li>
                <li class="breadcrumb-item active" aria-current="page">Mail</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER END --}}
    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">

                   @verbatim
                    <div class="text-warning">
                        <p>Here Is An Exapmle...</p>
                        <p>Dear *|FNAME|* *|LNAME|*,
                        Your email *|EMAIL|*. </p> 
                    </div>
                    @endverbatim

                    <form method="post" action="{{ route('mail.create') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label>Name</label>
                            <input name="name" class="form-control" value="{{ old('name') }}" required>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Subject</label>
                            <input name="subject" class="form-control" value="{{ old('subject') }}" required>
                            @error('subject')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Body</label>
                            <textarea id="summernote" name="body" class="form-control @error('body') is-invalid @enderror"
                                placeholder=""></textarea>
                            @error('body')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Submit</button>
                            <a href="{{ route('mail.index') }}" class="btn btn-danger me-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
