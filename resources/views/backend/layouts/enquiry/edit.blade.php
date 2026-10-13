@extends('backend.app')

@section('title', 'Enquiry Edit')

@section('content')
 <style>
        /* custom form style */
        .form-control {
            border-radius: 6px;
            color: #333;
            transition: 0.3s;
        }

        .form-control:focus {
            background-color: #ffffff;
        }
    </style>
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Enquiry Form</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Table</a></li>
                <li class="breadcrumb-item active" aria-current="page">Enquiry</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}


    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">
                    <form method="post" action="{{ route('enquiry.update', ['id' => $data->id]) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('put')

                        <div class="form-group">
                            <label for="name" class="form-label">Name:</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                name="name" id="name" value="{{ old('name', $data->name) }}">
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>


                        <div class="form-group">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email"
                                id="email" value="{{ old('email', $data->email) }}">
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">Phone:</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone"
                                id="phone" value="{{ old('phone', $data->phone ?? '') }}" readonly>
                            @error('phone')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="subject" class="form-label">Subject:</label>
                            <input type="text" class="form-control @error('subject') is-invalid @enderror" name="subject"
                                id="subject" value="{{ old('subject', $data->subject ?? '') }}" readonly>
                            @error('subject')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="reason" class="form-label">Reason:</label>
                            <input type="text" class="form-control @error('reason') is-invalid @enderror" name="reason"
                                id="reason" value="{{ old('reason', $data->reason ?? '') }}" readonly>
                            @error('reason')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="description" class="form-label">Description:</label>
                            <input type="text" class="form-control @error('description') is-invalid @enderror" name="description"
                                id="description" value="{{ old('description', $data->description ?? '') }}">
                            @error('description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                         <div class="form-group">
                            <label for="answer" class="form-label">Your answer or reply:</label>
                            <textarea name="answer" id="answer" class="form-control @error('answer') is-invalid @enderror">{{ old('answer', $data->answer ?? '') }}</textarea>
                            @error('answer')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="Answered">Answered</option>
                                <option value="Closed">Closed</option>
                            </select>
                            @error('status')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>



                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Submit</button>
                            <a href="{{ route('enquiry.index') }}" class="btn btn-danger me-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
