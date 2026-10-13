@extends('backend.app')

@section('title', 'Dashboard')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Dashboard</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}


    <div class="row">

        <div class="col-lg-6 col-sm-12 col-md-6 col-xl-3">
            <a href="/admin/user" class="card-link">
                <div class="card overflow-hidden">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h3 class="mb-2 fw-semibold">{{ $admins }}</h3>
                                <p class="text-muted fs-13 mb-0">Total Admins</p>
                            </div>
                            <div class="col col-auto top-icn dash">
                                <div class="counter-icon bg-primary dash ms-auto box-shadow-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
                                        <g data-name="28-Agency">
                                            <path style="fill:#ffee6e" d="M47 11h10v2H47z" />
                                            <path transform="rotate(-29.745 50.5 6)" style="fill:#ffee6e"
                                                d="M46.469 5h8.062v2h-8.062z" />
                                            <path transform="rotate(-60.255 50.5 18)" style="fill:#ffee6e"
                                                d="M49.5 13.969h2v8.062h-2z" />
                                            <path style="fill:#ffee6e" d="M7 11h10v2H7z" />
                                            <path transform="rotate(-60.255 13.5 6)" style="fill:#ffee6e"
                                                d="M12.5 1.969h2v8.062h-2z" />
                                            <path transform="rotate(-29.745 13.499 18)" style="fill:#ffee6e"
                                                d="M9.469 17h8.062v2H9.469z" />
                                            <path
                                                d="m36.707 26.293-1.414 1.414L37.586 30H33v-6h-2v6h-4.586l2.293-2.293-1.414-1.414-4 4A.974.974 0 0 0 23.3 31.7l-.006.006 4 4 1.414-1.414L26.414 32h11.172l-2.293 2.293 1.414 1.414 4-4-.007-.007a.974.974 0 0 0 .006-1.408z"
                                                style="fill:#afb4c8" />
                                            <path style="fill:#b2876d" d="M26 13h4v8h-4z" />
                                            <path style="fill:#966857" d="M28 13h2v8h-2zM27 1v2.73l-4 2.18V1h4z" />
                                            <path style="fill:#b2876d" d="M25 1h-2v4.91l2-1.09V1z" />
                                            <path style="fill:#a3d4ff" d="M33 13h5v4h-5z" />
                                            <path style="fill:#65b1fc" d="M33 15h5v2h-5z" />
                                            <path d="M38 13h-5v4h5zm3-3.09V21H30v-8h-4v8h-3V9.91L32 5z"
                                                style="fill:#f7f7f7" />
                                            <path style="fill:#cfcfcf" d="m32 5-9 4.91v2L32 7l9 4.91v-2L32 5z" />
                                            <path style="fill:#ff936b"
                                                d="M27 3.73 32 1l11 6v4l-2-1.09L32 5l-9 4.91L21 11V7l2-1.09 4-2.18z" />
                                            <path style="fill:#ff7045"
                                                d="m32 3-9 4.91L21 9v2l2-1.09L32 5l9 4.91L43 11V9l-2-1.09L32 3z" />
                                            <path d="M43 31v2h3c7 0 7-3 7-3a2.938 2.938 0 0 0 3 3h3v-2a8 8 0 0 0-16 0z"
                                                style="fill:#966857" />
                                            <path
                                                d="M53 28a2.938 2.938 0 0 0 3 3h3v2h-3a2.938 2.938 0 0 1-3-3s0 3-7 3h-3v-2h3c7 0 7-3 7-3z"
                                                style="fill:#8d5c4d" />
                                            <path style="fill:#e9edf5" d="M39 47v16h24V47l-10-2-2 2-2-2-10 2z" />
                                            <path style="fill:#cdd2e1"
                                                d="m49 45 2 2 2-2 10 2v3l-10-2-2 2-2-2-10 2v-3l10-2z" />
                                            <path
                                                d="M49 42.59V45l2 2 2-2v-2.41a5.083 5.083 0 0 1-4 0zM56 33v5h1a2.006 2.006 0 0 0 2-2v-3zM43 33v3a2.006 2.006 0 0 0 2 2h1v-5z"
                                                style="fill:#faa68e" />
                                            <path
                                                d="M49 42.59a5 5 0 0 0 5.54-1.05A5.022 5.022 0 0 0 56 38v-5a2.938 2.938 0 0 1-3-3s0 3-7 3v5a5.029 5.029 0 0 0 3 4.59z"
                                                style="fill:#ffcdbe" />
                                            <path
                                                d="M53 30a2.938 2.938 0 0 0 3 3v2a2.938 2.938 0 0 1-3-3s0 3-7 3v-2c7 0 7-3 7-3z"
                                                style="fill:#ffbeaa" />
                                            <path style="fill:#a3d4ff" d="m50 52-1 6 2 2 2-2-1-6h-2z" />
                                            <path style="fill:#65b1fc" d="m48 50 2 2h2l2-2-3-3-3 3z" />
                                            <path style="fill:#afb4c8"
                                                d="M46.22 45.56 48 50l3-3-2-2-2.78.56zM51 47l3 3 1.78-4.44L53 45l-2 2z" />
                                            <path d="M5 31v2h3c7 0 7-3 7-3a2.938 2.938 0 0 0 3 3h3v-2a8 8 0 0 0-16 0z"
                                                style="fill:#966857" />
                                            <path
                                                d="M15 28a2.938 2.938 0 0 0 3 3h3v2h-3a2.938 2.938 0 0 1-3-3s0 3-7 3H5v-2h3c7 0 7-3 7-3z"
                                                style="fill:#8d5c4d" />
                                            <path style="fill:#9c9c9c" d="M1 47v16h24V47l-10-2-2 2-2-2-10 2z" />
                                            <path style="fill:#cfcfcf"
                                                d="m11 45 2 2 2-2 10 2v3l-10-2-2 2-2-2-10 2v-3l10-2z" />
                                            <path
                                                d="M11 42.59V45l2 2 2-2v-2.41a5.083 5.083 0 0 1-4 0zM18 33v5h1a2.006 2.006 0 0 0 2-2v-3zM5 33v3a2.006 2.006 0 0 0 2 2h1v-5z"
                                                style="fill:#faa68e" />
                                            <path
                                                d="M11 42.59a5 5 0 0 0 5.54-1.05A5.022 5.022 0 0 0 18 38v-5a2.938 2.938 0 0 1-3-3s0 3-7 3v5a5.029 5.029 0 0 0 3 4.59z"
                                                style="fill:#ffcdbe" />
                                            <path
                                                d="M15 30a2.938 2.938 0 0 0 3 3v2a2.938 2.938 0 0 1-3-3s0 3-7 3v-2c7 0 7-3 7-3z"
                                                style="fill:#ffbeaa" />
                                            <path
                                                d="M25.2 46.02 16 44.18v-.992A6.007 6.007 0 0 0 18.91 39H19a3 3 0 0 0 3-3v-5a9 9 0 0 0-18 0v5a3 3 0 0 0 3 3h.09A6.007 6.007 0 0 0 10 43.188v.992L.8 46.02A1 1 0 0 0 0 47v16a1 1 0 0 0 1 1h24a1 1 0 0 0 1-1V47a1 1 0 0 0-.8-.98zM19 37v-3h1v2a1 1 0 0 1-1 1zM6 36v-2h1v3a1 1 0 0 1-1-1zm2-4H6v-1a7 7 0 0 1 14 0v1h-2a1.883 1.883 0 0 1-2-2 1.019 1.019 0 0 0-1-1.023.979.979 0 0 0-1 .966v.015c-.017.11-.414 2.042-6 2.042zm1 1.979c2.99-.129 4.7-.84 5.687-1.628A3.963 3.963 0 0 0 17 33.874V38a4 4 0 0 1-8 0zM13 44a6 6 0 0 0 1-.09v.676l-1 1-1-1v-.676a6 6 0 0 0 1 .09zM2 47.819l8.671-1.734L12 47.414V62H2zM24 62H14V47.414l1.329-1.329L24 47.819zM63.2 46.02 54 44.18v-.992A6.007 6.007 0 0 0 56.91 39H57a3 3 0 0 0 3-3v-5a9 9 0 0 0-18 0v5a3 3 0 0 0 3 3h.09A6.007 6.007 0 0 0 48 43.188v.992l-9.2 1.84a1 1 0 0 0-.8.98v16a1 1 0 0 0 1 1h24a1 1 0 0 0 1-1V47a1 1 0 0 0-.8-.98zm-14.525.065.915.915-1.221 1.221L47.6 46.3zm3.258 11.572-.933.929-.929-.929.776-4.657h.306zM51.586 51h-1.172l-1-1L51 48.414 52.586 50zm.828-4 .915-.915 1.071.215-.768 1.921zM57 37v-3h1v2a1 1 0 0 1-1 1zm-13-1v-2h1v3a1 1 0 0 1-1-1zm2-4h-2v-1a7 7 0 0 1 14 0v1h-2a1.883 1.883 0 0 1-2-2 1.019 1.019 0 0 0-1-1.023.979.979 0 0 0-1 .966v.015c-.017.11-.414 2.042-6 2.042zm1 1.979c2.99-.129 4.7-.84 5.687-1.628A3.963 3.963 0 0 0 55 33.874V38a4 4 0 0 1-8 0zM51 44a6 6 0 0 0 1-.09v.676l-1 1-1-1v-.676a6 6 0 0 0 1 .09zm-11 3.819 5.6-1.12 1.469 3.672a.978.978 0 0 0 .227.331l-.005.005 1.636 1.636-.915 5.493a1 1 0 0 0 .279.871L50 60.414V62H40zM62 62H52v-1.586l1.707-1.707a1 1 0 0 0 .279-.871l-.915-5.493 1.636-1.636-.007-.007a.978.978 0 0 0 .227-.331L56.4 46.7l5.6 1.12zM20.489 11.86a1 1 0 0 0 .99.018l.521-.285V21a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1v-9.407l.521.285A1 1 0 0 0 44 11V7a1 1 0 0 0-.521-.878l-11-6a1 1 0 0 0-.958 0L28 2.043V1a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v4.316l-1.479.806A1 1 0 0 0 20 7v4a1 1 0 0 0 .489.86zM27 20v-6h2v6zm13 0h-9v-7a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v7h-1v-9.5l8-4.364 8 4.364zM24 2h2v1.134l-2 1.091zm-2 5.594 10-5.455 10 5.455v1.721l-9.521-5.193a1 1 0 0 0-.958 0L22 9.315z" />
                                            <path
                                                d="M32 17a1 1 0 0 0 1 1h5a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-5a1 1 0 0 0-1 1zm2-3h3v2h-3zM47 11h10v2H47zM46.504 7.132l7-4 .992 1.737-7 4zM46.504 16.869l.993-1.737 7 4-.993 1.736zM7 11h10v2H7zM9.504 4.868l.993-1.737 7 4-.993 1.737zM9.504 19.131l7-4 .992 1.737-7 4zM40.7 31.7a.974.974 0 0 0 .006-1.408l-4-4-1.414 1.414L37.586 30H33v-6h-2v6h-4.586l2.293-2.293-1.414-1.414-4 4A.974.974 0 0 0 23.3 31.7l-.006.006 4 4 1.414-1.414L26.414 32h11.172l-2.293 2.293 1.414 1.414 4-4z" />
                                        </g>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-6 col-sm-12 col-md-6 col-xl-3">
            <div class="card overflow-hidden">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h3 class="mb-2 fw-semibold">{{ $users }}</h3>
                            <p class="text-muted fs-13 mb-0">Total Users</p>
                        </div>
                        <div class="col col-auto top-icn dash">
                            <div class="counter-icon bg-secondary dash ms-auto box-shadow-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                    <path
                                        d="M160 64C142.3 64 128 78.3 128 96C128 113.7 142.3 128 160 128L160 139C160 181.4 176.9 222.1 206.9 252.1L274.8 320L206.9 387.9C176.9 417.9 160 458.6 160 501L160 512C142.3 512 128 526.3 128 544C128 561.7 142.3 576 160 576L480 576C497.7 576 512 561.7 512 544C512 526.3 497.7 512 480 512L480 501C480 458.6 463.1 417.9 433.1 387.9L365.2 320L433.1 252.1C463.1 222.1 480 181.4 480 139L480 128C497.7 128 512 113.7 512 96C512 78.3 497.7 64 480 64L160 64zM224 139L224 128L416 128L416 139C416 158 410.4 176.4 400 192L240 192C229.7 176.4 224 158 224 139zM240 448C243.5 442.7 247.6 437.7 252.1 433.1L320 365.2L387.9 433.1C392.5 437.7 396.5 442.7 400.1 448L240 448z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-sm-12 col-md-6 col-xl-3">
            <a href="/admin/review" class="card-link">
                <div class="card overflow-hidden">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h3 class="mb-2 fw-semibold">{{ $review ?? '0' }}</h3>
                                <p class="text-muted fs-13 mb-0">Total Reviews</p>
                            </div>
                            <div class="col col-auto top-icn dash">
                                <div class="counter-icon bg-primary dash ms-auto box-shadow-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640"><path d="M341.5 45.1C337.4 37.1 329.1 32 320.1 32C311.1 32 302.8 37.1 298.7 45.1L225.1 189.3L65.2 214.7C56.3 216.1 48.9 222.4 46.1 231C43.3 239.6 45.6 249 51.9 255.4L166.3 369.9L141.1 529.8C139.7 538.7 143.4 547.7 150.7 553C158 558.3 167.6 559.1 175.7 555L320.1 481.6L464.4 555C472.4 559.1 482.1 558.3 489.4 553C496.7 547.7 500.4 538.8 499 529.8L473.7 369.9L588.1 255.4C594.5 249 596.7 239.6 593.9 231C591.1 222.4 583.8 216.1 574.8 214.7L415 189.3L341.5 45.1z"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

         <div class="col-lg-6 col-sm-12 col-md-6 col-xl-3">
            <a href="/admin/product" class="card-link">
                <div class="card overflow-hidden">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h3 class="mb-2 fw-semibold">{{ $product ?? '0' }}</h3>
                                <p class="text-muted fs-13 mb-0">Total Products</p>
                            </div>
                            <div class="col col-auto top-icn dash">
                                <div class="counter-icon bg-primary dash ms-auto box-shadow-info">
                                    <svg fill="#816d6d" height="800px" width="800px" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" 
	 viewBox="0 0 372.372 372.372" xml:space="preserve">
<g>
	<path d="M368.712,219.925c-5.042-8.951-14.563-14.511-24.848-14.511c-4.858,0-9.682,1.27-13.948,3.672l-83.024,46.756
		c-1.084,0.61-1.866,1.642-2.163,2.85c-1.448,5.911-4.857,14.164-12.865,19.911c-8.864,6.361-20.855,7.686-35.466,3.939
		c-0.088-0.022-0.175-0.047-0.252-0.071L148.252,267.6c-2.896-0.899-4.52-3.987-3.621-6.882c0.72-2.316,2.83-3.872,5.251-3.872
		c0.55,0,1.101,0.084,1.634,0.249l47.645,14.794c0.076,0.023,0.154,0.045,0.232,0.065c11.236,2.836,20.011,2.047,26.056-2.288
		c7.637-5.48,8.982-15.113,9.141-16.528c0.006-0.045,0.011-0.09,0.014-0.136c0.003-0.023,0.004-0.036,0.005-0.039
		c0.001-0.015,0.002-0.03,0.003-0.044c0.001-0.01,0.001-0.019,0.002-0.029c0.909-11.878-6.756-22.846-18.24-26.089l-0.211-0.064
		c-0.35-0.114-35.596-11.626-58.053-18.034c-2.495-0.711-9.37-2.366-19.313-2.366c-13.906,0-34.651,3.295-54.549,19.025
		L1.67,292.159c-1.889,1.527-2.224,4.278-0.758,6.215l44.712,59.06c0.725,0.956,1.801,1.584,2.99,1.744
		c0.199,0.027,0.398,0.04,0.598,0.04c0.987,0,1.954-0.325,2.745-0.935l57.592-44.345c1.294-0.995,3.029-1.37,4.619-0.995
		l93.02,21.982c6.898,1.63,14.353,0.578,20.523-2.9l130.16-73.304C371.555,251.012,376.418,233.61,368.712,219.925z"/>
	<path d="M316.981,13.155h-170c-5.522,0-10,4.477-10,10v45.504c0,5.523,4.478,10,10,10h3.735v96.623c0,5.523,4.477,10,10,10h142.526
		c5.523,0,10-4.477,10-10V78.658h3.738c5.522,0,10-4.477,10-10V23.155C326.981,17.632,322.503,13.155,316.981,13.155z
		 M253.016,102.417h-42.072c-4.411,0-8-3.589-8-8c0-4.411,3.589-8,8-8h42.072c4.411,0,8,3.589,8,8
		C261.016,98.828,257.427,102.417,253.016,102.417z M306.981,58.658h-3.738H160.716h-3.735V33.155h150V58.658z"/>
</g>
</svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-6 col-sm-12 col-md-6 col-xl-3">
            <a href="/admin/bookings" class="card-link">
                <div class="card overflow-hidden">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h3 class="mb-2 fw-semibold">{{ $booking ?? '0' }}</h3>
                                <p class="text-muted fs-13 mb-0">Total Bookings</p>
                            </div>
                            <div class="col col-auto top-icn dash">
                                <div class="counter-icon bg-info dash ms-auto box-shadow-info">
                                    <i class="fa fa-calendar-check-o text-white"></i>
                                </div>
                            </div>
                        </div>
                        <p class="text-danger fs-12 mb-0">{{ $failedBooking ?? '0' }} need admin attention</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-6 col-sm-12 col-md-6 col-xl-3">
            <a href="{{ route('newsletter.index') }}" class="card-link">
                <div class="card overflow-hidden">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h3 class="mb-2 fw-semibold">{{ $newsletterSubscribers ?? '0' }}</h3>
                                <p class="text-muted fs-13 mb-0">Newsletter Subscribers</p>
                            </div>
                            <div class="col col-auto top-icn dash">
                                <div class="counter-icon bg-secondary dash ms-auto box-shadow-secondary">
                                    <i class="fa fa-envelope text-white"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-6 col-sm-12 col-md-6 col-xl-3">
            <a href="/admin/faq" class="card-link">
                <div class="card overflow-hidden">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h3 class="mb-2 fw-semibold">{{ $faq ?? '0' }}</h3>
                                <p class="text-muted fs-13 mb-0">Total FAQs</p>
                            </div>
                            <div class="col col-auto top-icn dash">
                                <div class="counter-icon bg-success dash ms-auto box-shadow-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640"><path d="M320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM320 240C302.3 240 288 254.3 288 272C288 285.3 277.3 296 264 296C250.7 296 240 285.3 240 272C240 227.8 275.8 192 320 192C364.2 192 400 227.8 400 272C400 319.2 364 339.2 344 346.5L344 350.3C344 363.6 333.3 374.3 320 374.3C306.7 374.3 296 363.6 296 350.3L296 342.2C296 321.7 310.8 307 326.1 302C332.5 299.9 339.3 296.5 344.3 291.7C348.6 287.5 352 281.7 352 272.1C352 254.4 337.7 240.1 320 240.1zM288 432C288 414.3 302.3 400 320 400C337.7 400 352 414.3 352 432C352 449.7 337.7 464 320 464C302.3 464 288 449.7 288 432z"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-6 col-sm-12 col-md-6 col-xl-3">
            <a href="/admin/activity-logs" class="card-link">
                <div class="card overflow-hidden">
                    <div class="card-body">
                        <div class="row">
                            <div class="col">
                                <h3 class="mb-2 fw-semibold">{{ $activityLog ?? '0' }}</h3>
                                <p class="text-muted fs-13 mb-0">Total Activity Logs</p>
                            </div>
                            <div class="col col-auto top-icn dash">
                                <div class="counter-icon bg-info dash ms-auto box-shadow-info">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640"><path d="M128 128C128 110.3 113.7 96 96 96C78.3 96 64 110.3 64 128L64 464C64 508.2 99.8 544 144 544L544 544C561.7 544 576 529.7 576 512C576 494.3 561.7 480 544 480L144 480C135.2 480 128 472.8 128 464L128 128zM534.6 214.6C547.1 202.1 547.1 181.8 534.6 169.3C522.1 156.8 501.8 156.8 489.3 169.3L384 274.7L326.6 217.4C314.1 204.9 293.8 204.9 281.3 217.4L185.3 313.4C172.8 325.9 172.8 346.2 185.3 358.7C197.8 371.2 218.1 371.2 230.6 358.7L304 285.3L361.4 342.7C373.9 355.2 394.2 355.2 406.7 342.7L534.7 214.7z"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

    </div>

@endsection
