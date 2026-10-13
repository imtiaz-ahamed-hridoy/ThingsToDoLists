@extends('backend.app')
@section('title', 'Mail')
@section('content')

    <div class="page-header">
        <div>
            <h1 class="page-title">Send Mail to the Subscribers or Add Subscriber with MailChimp API</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Mail</a></li>
                <li class="breadcrumb-item active" aria-current="page">Send Mail Or Add Subscribers</li>
            </ol>
        </div>
    </div>

    {{-- Success / Error Alerts --}}
    @if ($message = Session::get('t-success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>{{ $message }}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($message = Session::get('t-error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>{{ $message }}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">

        <!-- Subscribe Form -->
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h4 class="card-title text-center mb-4 fw-bold">Subscribe to Our Newsletter</h4>
                    <form action="{{ route('newsletter.subscribe') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <input 
                                type="email" 
                                name="email" 
                                id="email" 
                                class="form-control form-control-lg" 
                                placeholder="Enter your email" 
                                required
                            >
                        </div>
                         <div class="mb-4">
                                <input 
                                    type="text" 
                                    name="first_name" 
                                    id="first_name" 
                                    class="form-control" 
                                    placeholder="Your First Name"
                                >
                                 @error('first_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                            </div>

                             <div class="mb-4">
                                <input 
                                    type="text" 
                                    name="last_name" 
                                    id="last_name" 
                                    class="form-control" 
                                    placeholder="Your Last Name"
                                >
                                 @error('last_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                            </div>
                            <div class="mb-4">
                                <input 
                                    type="text" 
                                    name="number" 
                                    id="number" 
                                    class="form-control" 
                                    placeholder="Your number"
                                >
                                 @error('number')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                            </div>
                            <div class="mb-4">
                                <input 
                                    type="text" 
                                    name="address" 
                                    id="address" 
                                    class="form-control" 
                                    placeholder="Your Address"
                                >
                                 @error('address')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                            </div>
                            <div class="mb-4">
                                <input 
                                    type="date" 
                                    name="dob" 
                                    id="dob" 
                                    class="form-control" 
                                    placeholder="Your DOB"
                                >
                                 @error('dob')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                            </div>
                            <div class="mb-4">
                                <select name="role" id="role" class="form-control">
                                    <option value="User">User</option>
                                    <option value="Company">Company</option>
                                    <option value="Admin">Admin</option>
                                </select>
                                
                                 @error('role')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                            </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-info btn-lg fw-bold">
                                Subscribe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Send Campaign Form -->
        <div class="col-md-7">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h4 class="card-title text-center mb-4 fw-bold">Send Campaign</h4>
                    <form action="{{ route('sendCompaign') }}" method="POST" class="form-horizontal">
                        @csrf
                        <div class="row g-3">

                             <label>Template (optional):</label>
                            <select name="template_id" class="form-select mb-3">
                                <option value="">-- Select Template --</option>
                                @foreach(App\Models\EmailTemplate::all() as $temp)
                                    <option value="{{ $temp->id }}">{{ $temp->name }}</option>
                                @endforeach
                            </select>

                             @error('template_id')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror


                            <!-- Subject -->
                            <div class="col-md-12">
                                <label for="subject" class="form-label fw-semibold">Subject</label>
                                <input 
                                    type="text" 
                                    name="subject" 
                                    id="subject" 
                                    class="form-control" 
                                    placeholder="Your Subject"
                                >
                                 @error('subject')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                            </div>

                            <!-- Message -->
                            <div class="col-md-12">
                                <label for="message" class="form-label fw-semibold">Message</label>
                                <textarea 
                                    name="message" 
                                    id="summernote" 
                                    rows="5" 
                                    class="form-control" 
                                    placeholder="Please enter your message here..."
                                ></textarea>
                                 @error('message')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                            </div>

                            <!-- Scheduled Time -->
                            <div class="col-md-12">
                                <label for="schedule_time" class="form-label fw-semibold">Scheduled Time (optional)</label>
                                <input 
                                    type="datetime-local" 
                                    name="schedule_time" 
                                    id="schedule_time" 
                                    class="form-control"
                                >
                                 @error('schedule_time')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                            </div>

                            <!-- Submit Button -->
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-info btn-lg fw-bold">
                                    Send Campaign
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>


@endsection
