@php
$systemSetting = App\Models\SystemSetting::first();
$logo =
$systemSetting && $systemSetting->logo
? url(\Illuminate\Support\Facades\Storage::url($systemSetting->logo))
: null;
@endphp

<div class="sticky">
    <div class="app-sidebar__overlay" data-bs-toggle="sidebar"></div>
    <div class="app-sidebar">
        <div class="side-header">
            <a class="header-brand1" href="{{ route('dashboard') }}">
                <img src="{{ $logo ?? asset('frontend/logo.png') }}" class="header-brand-img desktop-logo" alt="logo">
                <img src="{{ $logo ?? asset('frontend/logo.png') }}" class="header-brand-img toggle-logo"
                    alt="logo">
                <img src="{{ $logo ?? asset('frontend/logo.png') }}" class="header-brand-img light-logo" alt="logo">
                <img src="{{ $logo ?? asset('frontend/logo.png') }}" class="header-brand-img light-logo1"
                    alt="logo">
            </a>
        </div>

        <div class="main-sidemenu">
            <div class="slide-left disabled" id="slide-left">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24"
                    viewBox="0 0 24 24">
                    <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z" />
                </svg>
            </div>

            <ul class="side-menu">
                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('dashboard') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon"
                            enable-background="new 0 0 24 24" viewBox="0 0 24 24">
                            <path d="M19.9794922,7.9521484l-6-5.2666016c-1.1339111-0.9902344-2.8250732-0.9902344-3.9589844,0l-6,5.2666016C3.3717041,8.5219116,2.9998169,9.3435669,3,10.2069702V19c0.0018311,1.6561279,1.3438721,2.9981689,3,3h2.5h7c0.0001831,0,0.0003662,0,0.0006104,0H18c1.6561279-0.0018311,2.9981689-1.3438721,3-3v-8.7930298C21.0001831,9.3435669,20.6282959,8.5219116,19.9794922,7.9521484z M15,21H9v-6c0.0014038-1.1040039,0.8959961-1.9985962,2-2h2c1.1040039,0.0014038,1.9985962,0.8959961,2,2V21z M20,19c-0.0014038,1.1040039-0.8959961,1.9985962-2,2h-2v-6c-0.0018311-1.6561279-1.3438721-2.9981689-3-3h-2c-1.6561279,0.0018311-2.9981689,1.3438721-3,3v6H6c-1.1040039-0.0014038-1.9985962-0.8959961-2-2v-8.7930298C3.9997559,9.6313477,4.2478027,9.0836182,4.6806641,8.7041016l6-5.2666016C11.0455933,3.1174927,11.5146484,2.9414673,12,2.9423828c0.4853516-0.0009155,0.9544067,0.1751099,1.3193359,0.4951172l6,5.2665405C19.7521973,9.0835571,20.0002441,9.6313477,20,10.2069702V19z" />
                        </svg>
                        <span class="side-menu__label">Dashboard</span>
                    </a>
                </li>

                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('user.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>

                        <span class="side-menu__label"> Users</span>
                    </a>
                </li>

                {{-- <li class="slide {{ request()->is('admin/mail*') ? 'active is-expanded' : '' }}">--}}
                {{-- <a class="side-menu__item {{ request()->is('admin/mail*') ? 'active is-expanded' : '' }}"--}}
                {{-- data-bs-toggle="slide" href="#">--}}
                {{-- <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640"><path d="M112 128C85.5 128 64 149.5 64 176C64 191.1 71.1 205.3 83.2 214.4L291.2 370.4C308.3 383.2 331.7 383.2 348.8 370.4L556.8 214.4C568.9 205.3 576 191.1 576 176C576 149.5 554.5 128 528 128L112 128zM64 260L64 448C64 483.3 92.7 512 128 512L512 512C547.3 512 576 483.3 576 448L576 260L377.6 408.8C343.5 434.4 296.5 434.4 262.4 408.8L64 260z"/></svg>--}}
                {{-- <span class="side-menu__label">E-mails</span><i class="angle fa fa-angle-right"></i>--}}
                {{-- </a>--}}

                {{-- <ul class="slide-menu">--}}
                {{-- <li><a href="{{ route('mail.index') }}" class="slide-item">Mail Template</a></li>--}}
                {{-- <li><a href="{{ route('email.index') }}" class="slide-item">Sends Email</a></li>--}}
                {{-- </ul>--}}
                {{-- </li>--}}

                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('review.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" class="side-menu__icon"
                            viewBox="0 0 640 640">
                            <path
                                d="M341.5 45.1C337.4 37.1 329.1 32 320.1 32C311.1 32 302.8 37.1 298.7 45.1L225.1 189.3L65.2 214.7C56.3 216.1 48.9 222.4 46.1 231C43.3 239.6 45.6 249 51.9 255.4L166.3 369.9L141.1 529.8C139.7 538.7 143.4 547.7 150.7 553C158 558.3 167.6 559.1 175.7 555L320.1 481.6L464.4 555C472.4 559.1 482.1 558.3 489.4 553C496.7 547.7 500.4 538.8 499 529.8L473.7 369.9L588.1 255.4C594.5 249 596.7 239.6 593.9 231C591.1 222.4 583.8 216.1 574.8 214.7L415 189.3L341.5 45.1z" />
                        </svg>
                        <span class="side-menu__label"> Reviews</span>
                    </a>
                </li>

                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('newsletter.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640" fill="currentColor">
                            <path d="M112 128C85.5 128 64 149.5 64 176C64 191.1 71.1 205.3 83.2 214.4L291.2 370.4C308.3 383.2 331.7 383.2 348.8 370.4L556.8 214.4C568.9 205.3 576 191.1 576 176C576 149.5 554.5 128 528 128L112 128zM64 260L64 448C64 483.3 92.7 512 128 512L512 512C547.3 512 576 483.3 576 448L576 260L377.6 408.8C343.5 434.4 296.5 434.4 262.4 408.8L64 260z"/>
                        </svg>
                        <span class="side-menu__label"> Newsletter</span>
                    </a>
                </li>

                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('faq.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640">
                            <path
                                d="M280 88C280 57.1 254.9 32 224 32C193.1 32 168 57.1 168 88C168 118.9 193.1 144 224 144C254.9 144 280 118.9 280 88zM304 300.7L341 350.6C353.8 333.1 369.5 317.9 387.3 305.6L331.1 229.9C306 196 266.3 176 224 176C181.7 176 142 196 116.8 229.9L46.3 324.9C35.8 339.1 38.7 359.1 52.9 369.7C67.1 380.3 87.1 377.3 97.7 363.1L144 300.7L144 576C144 593.7 158.3 608 176 608C193.7 608 208 593.7 208 576L208 416C208 407.2 215.2 400 224 400C232.8 400 240 407.2 240 416L240 576C240 593.7 254.3 608 272 608C289.7 608 304 593.7 304 576L304 300.7zM496 608C575.5 608 640 543.5 640 464C640 384.5 575.5 320 496 320C416.5 320 352 384.5 352 464C352 543.5 416.5 608 496 608zM496 508C507 508 516 517 516 528C516 539 507 548 496 548C485 548 476 539 476 528C476 517 485 508 496 508zM496 408C484.4 408 474.7 416.2 472.5 427.2C470.7 435.9 462.3 441.5 453.6 439.7C444.9 437.9 439.3 429.5 441.1 420.8C446.3 395.2 468.9 376 496 376C526.9 376 552 401.1 552 432C552 451.8 540.3 469.8 522.2 477.9L511.8 482.5C510.6 490.2 504 496 496 496C487.2 496 480 488.8 480 480C480 468.8 486.6 458.7 496.8 454.1L509.2 448.6C515.8 445.7 520 439.2 520 432C520 418.7 509.3 408 496 408z" />
                        </svg>
                        <span class="side-menu__label"> Faqs</span>
                    </a>
                </li>

                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('page.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640">
                            <path
                                d="M192 64C156.7 64 128 92.7 128 128L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.2L386.7 82.7C374.7 70.7 358.5 64 341.5 64L192 64zM453.5 240L360 240C346.7 240 336 229.3 336 216L336 122.5L453.5 240z" />
                        </svg>
                        <span class="side-menu__label"> Dynamic Page </span>
                    </a>
                </li>

                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('cities.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640">
                            <path
                                d="M352 64C316.7 64 288 92.7 288 128L288 160L240 160L240 88C240 74.7 229.3 64 216 64C202.7 64 192 74.7 192 88L192 160L128 160L128 88C128 74.7 117.3 64 104 64C90.7 64 80 74.7 80 88L80 162C52.4 169.1 32 194.2 32 224L32 512C32 547.3 60.7 576 96 576L544 576C579.3 576 608 547.3 608 512L608 320C608 284.7 579.3 256 544 256L480 256L480 128C480 92.7 451.3 64 416 64L352 64zM416 176L416 208C416 216.8 408.8 224 400 224L368 224C359.2 224 352 216.8 352 208L352 176C352 167.2 359.2 160 368 160L400 160C408.8 160 416 167.2 416 176zM400 256C408.8 256 416 263.2 416 272L416 304C416 312.8 408.8 320 400 320L368 320C359.2 320 352 312.8 352 304L352 272C352 263.2 359.2 256 368 256L400 256zM416 368L416 400C416 408.8 408.8 416 400 416L368 416C359.2 416 352 408.8 352 400L352 368C352 359.2 359.2 352 368 352L400 352C408.8 352 416 359.2 416 368zM528 352C536.8 352 544 359.2 544 368L544 400C544 408.8 536.8 416 528 416L496 416C487.2 416 480 408.8 480 400L480 368C480 359.2 487.2 352 496 352L528 352zM288 368L288 400C288 408.8 280.8 416 272 416L240 416C231.2 416 224 408.8 224 400L224 368C224 359.2 231.2 352 240 352L272 352C280.8 352 288 359.2 288 368zM272 256C280.8 256 288 263.2 288 272L288 304C288 312.8 280.8 320 272 320L240 320C231.2 320 224 312.8 224 304L224 272C224 263.2 231.2 256 240 256L272 256zM160 368L160 400C160 408.8 152.8 416 144 416L112 416C103.2 416 96 408.8 96 400L96 368C96 359.2 103.2 352 112 352L144 352C152.8 352 160 359.2 160 368zM144 256C152.8 256 160 263.2 160 272L160 304C160 312.8 152.8 320 144 320L112 320C103.2 320 96 312.8 96 304L96 272C96 263.2 103.2 256 112 256L144 256z" />
                        </svg>
                        <span class="side-menu__label"> Citys Page </span>
                    </a>
                </li>
                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('category.index') }}">
                        <svg viewBox="0 0 24 24" class="side-menu__icon" fill="none"
                            xmlns="http://www.w3.org/2000/svg" stroke="#495584">
                            <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                            <g id="SVGRepo_iconCarrier">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M6 2C3.79086 2 2 3.79086 2 6V7C2 9.20914 3.79086 11 6 11H7C9.20914 11 11 9.20914 11 7V6C11 3.79086 9.20914 2 7 2H6ZM17 2C14.7909 2 13 3.79086 13 6V7C13 9.20914 14.7909 11 17 11H18C20.2091 11 22 9.20914 22 7V6C22 3.79086 20.2091 2 18 2H17ZM6 13C3.79086 13 2 14.7909 2 17V18C2 20.2091 3.79086 22 6 22H7C9.20914 22 11 20.2091 11 18V17C11 14.7909 9.20914 13 7 13H6ZM17 13C14.7909 13 13 14.7909 13 17V18C13 20.2091 14.7909 22 17 22H18C20.2091 22 22 20.2091 22 18V17C22 14.7909 20.2091 13 18 13H17Z"
                                    fill="#495584"></path>
                            </g>
                        </svg>
                        <span class="side-menu__label"> Category Page </span>
                    </a>
                </li>
                
                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('destination-highlights.index') }}">
                        <svg viewBox="0 0 24 24" class="side-menu__icon" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#495584">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="#495584"/>
                        </svg>
                        <span class="side-menu__label"> Destination Highlights </span>
                    </a>
                </li>

                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('tempProduct.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640">
                            <path
                                d="M192 64C156.7 64 128 92.7 128 128L128 368L310.1 368L279.1 337C269.7 327.6 269.7 312.4 279.1 303.1C288.5 293.8 303.7 293.7 313 303.1L385 375.1C394.4 384.5 394.4 399.7 385 409L313 481C303.6 490.4 288.4 490.4 279.1 481C269.8 471.6 269.7 456.4 279.1 447.1L310.1 416.1L128 416.1L128 512.1C128 547.4 156.7 576.1 192 576.1L448 576.1C483.3 576.1 512 547.4 512 512.1L512 234.6C512 217.6 505.3 201.3 493.3 189.3L386.7 82.7C374.7 70.7 358.5 64 341.5 64L192 64zM453.5 240L360 240C346.7 240 336 229.3 336 216L336 122.5L453.5 240z" />
                        </svg>
                        <span class="side-menu__label"> Import Products </span>
                    </a>
                </li>

                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('product.index') }}">
                        <svg class="side-menu__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M465.4 192L431.1 144L209 144L174.7 192L465.4 192zM96 212.5C96 199.2 100.2 186.2 107.9 175.3L156.9 106.8C168.9 90 188.3 80 208.9 80L431 80C451.7 80 471.1 90 483.1 106.8L532 175.3C539.8 186.2 543.9 199.2 543.9 212.5L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 212.5z" />
                        </svg>
                        <span class="side-menu__label"> Products </span>
                    </a>
                </li>
                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('booking.index') }}">
                        <svg class="side-menu__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM160 256L160 480L480 480L480 256L160 256z" />
                        </svg>
                        <span class="side-menu__label">Bookings</span>
                    </a>
                </li>
                <li class="slide">
                    <a class="side-menu__item has-link" data-bs-toggle="slide" href="{{ route('availability.index') }}">
                        <svg class="side-menu__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64zM438 225.7C427.3 217.9 412.3 220.3 404.5 231L285.1 395.2L233 343.1C223.6 333.7 208.4 333.7 199.1 343.1C189.8 352.5 189.7 367.7 199.1 377L271.1 449C276.1 454 283 456.5 289.9 456C296.8 455.5 303.3 451.9 307.4 446.2L443.3 259.2C451.1 248.5 448.7 233.5 438 225.7z" />
                        </svg>
                        <span class="side-menu__label"> Availability </span>
                    </a>
                </li>

                <li class="slide {{ request()->is('admin/cms-settings*') ? 'active is-expanded' : '' }}">
                    <a class="side-menu__item {{ request()->is('admin/cms-settings*') ? 'active is-expanded' : '' }}"
                        data-bs-toggle="slide" href="#">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 512 512">
                            <path
                                d="M495.9 166.6c3.2 8.7 .5 18.4-6.4 24.6l-43.3 39.4c1.1 8.3 1.7 16.8 1.7 25.4s-.6 17.1-1.7 25.4l43.3 39.4c6.9 6.2 9.6 15.9 6.4 24.6c-4.4 11.9-9.7 23.3-15.8 34.3l-4.7 8.1c-6.6 11-14 21.4-22.1 31.2c-5.9 7.2-15.7 9.6-24.5 6.8l-55.7-17.7c-13.4 10.3-28.2 18.9-44 25.4l-12.5 57.1c-2 9.1-9 16.3-18.2 17.8c-13.8 2.3-28 3.5-42.5 3.5s-28.7-1.2-42.5-3.5c-9.2-1.5-16.2-8.7-18.2-17.8l-12.5-57.1c-15.8-6.5-30.6-15.1-44-25.4L83.1 425.9c-8.8 2.8-18.6 .3-24.5-6.8c-8.1-9.8-15.5-20.2-22.1-31.2l-4.7-8.1c-6.1-11-11.4-22.4-15.8-34.3c-3.2-8.7-.5-18.4 6.4-24.6l43.3-39.4C64.6 273.1 64 264.6 64 256s.6-17.1 1.7-25.4L22.4 191.2c-6.9-6.2-9.6-15.9-6.4-24.6c4.4-11.9 9.7-23.3 15.8-34.3l4.7-8.1c6.6-11 14-21.4 22.1-31.2c5.9-7.2 15.7-9.6 24.5-6.8l55.7 17.7c13.4-10.3 28.2-18.9 44-25.4l12.5-57.1c2-9.1 9-16.3 18.2-17.8C227.3 1.2 241.5 0 256 0s28.7 1.2 42.5 3.5c9.2 1.5 16.2 8.7 18.2 17.8l12.5 57.1c15.8 6.5 30.6 15.1 44 25.4l55.7-17.7c8.8-2.8 18.6-.3 24.5 6.8c8.1 9.8 15.5 20.2 22.1 31.2l4.7 8.1c6.1 11 11.4 22.4 15.8 34.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z" />
                        </svg>
                        <span class="side-menu__label">CMS Settings</span><i class="angle fa fa-angle-right"></i>
                    </a>

                    <ul class="slide-menu">
                        @if (Auth::user()->role === 'Admin')
                        <li><a href="{{ route('cmsSettings.sections.index') }}" class="slide-item">Home Sections</a></li>
                        <li><a href="{{ route('cmsSettings.banners.index') }}" class="slide-item">Home Banners</a></li>

                        <li><a href="{{ route('cmsSettings.blogs.index') }}" class="slide-item">Blog</a></li>
                        <li><a href="{{ route('cmsSettings.testimonial.index') }}" class="slide-item">Testimonials</a></li>
                        {{-- <li><a href="{{ route('cmsSettings.aboutPage') }}" class="slide-item">About Page</a>
                </li>
                <li><a href="{{ route('cmsSettings.contactPage') }}" class="slide-item">Contact Page</a></li>
                <li><a href="{{ route('cmsSettings.privacyPage') }}" class="slide-item">Privacy Page</a></li>
                <li><a href="{{ route('cmsSettings.termsPage') }}" class="slide-item">Terms Page</a></li>
                <li><a href="{{ route('cmsSettings.refundPage') }}" class="slide-item">Refund Page</a></li>
                <li><a href="{{ route('cmsSettings.shippingPage') }}" class="slide-item">Shipping Page</a></li>
                <li><a href="{{ route('cmsSettings.faqPage') }}" class="slide-item">FAQ Page</a></li>
                <li><a href="{{ route('cmsSettings.blogPage') }}" class="slide-item">Blog Page</a></li> --}}
                @endif
            </ul>
            </li>

            <hr>

            <li class="slide {{ request()->is('admin/settings*') ? 'active is-expanded' : '' }}">
                <a class="side-menu__item {{ request()->is('admin/settings*') ? 'active is-expanded' : '' }}"
                    data-bs-toggle="slide" href="#">
                    <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 512 512">
                        <path
                            d="M495.9 166.6c3.2 8.7 .5 18.4-6.4 24.6l-43.3 39.4c1.1 8.3 1.7 16.8 1.7 25.4s-.6 17.1-1.7 25.4l43.3 39.4c6.9 6.2 9.6 15.9 6.4 24.6c-4.4 11.9-9.7 23.3-15.8 34.3l-4.7 8.1c-6.6 11-14 21.4-22.1 31.2c-5.9 7.2-15.7 9.6-24.5 6.8l-55.7-17.7c-13.4 10.3-28.2 18.9-44 25.4l-12.5 57.1c-2 9.1-9 16.3-18.2 17.8c-13.8 2.3-28 3.5-42.5 3.5s-28.7-1.2-42.5-3.5c-9.2-1.5-16.2-8.7-18.2-17.8l-12.5-57.1c-15.8-6.5-30.6-15.1-44-25.4L83.1 425.9c-8.8 2.8-18.6 .3-24.5-6.8c-8.1-9.8-15.5-20.2-22.1-31.2l-4.7-8.1c-6.1-11-11.4-22.4-15.8-34.3c-3.2-8.7-.5-18.4 6.4-24.6l43.3-39.4C64.6 273.1 64 264.6 64 256s.6-17.1 1.7-25.4L22.4 191.2c-6.9-6.2-9.6-15.9-6.4-24.6c4.4-11.9 9.7-23.3 15.8-34.3l4.7-8.1c6.6-11 14-21.4 22.1-31.2c5.9-7.2 15.7-9.6 24.5-6.8l55.7 17.7c13.4-10.3 28.2-18.9 44-25.4l12.5-57.1c2-9.1 9-16.3 18.2-17.8C227.3 1.2 241.5 0 256 0s28.7 1.2 42.5 3.5c9.2 1.5 16.2 8.7 18.2 17.8l12.5 57.1c15.8 6.5 30.6 15.1 44 25.4l55.7-17.7c8.8-2.8 18.6-.3 24.5 6.8c8.1 9.8 15.5 20.2 22.1 31.2l4.7 8.1c6.1 11 11.4 22.4 15.8 34.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z" />
                    </svg>
                    <span class="side-menu__label">Settings</span><i class="angle fa fa-angle-right"></i>
                </a>

                <ul class="slide-menu">
                    <li><a href="{{ route('profile.setting') }}" class="slide-item">Profile Settings</a></li>
                    @if (Auth::user()->role === 'Admin')
                    <li><a href="{{ route('system.index') }}" class="slide-item">System Settings</a></li>
                    <li><a href="{{ route('mail.setting') }}" class="slide-item">Mail Settings</a></li>
                    <li><a href="{{ route('stripe.edit') }}" class="slide-item">Stripe Settings</a></li>
                    @endif
                </ul>
            </li>            @if (Auth::user()->role === 'Admin')            <li class="slide {{ request()->is('admin/queue-monitor*') ? 'active is-expanded' : '' }}">
                    <a class="side-menu__item has-link {{ request()->is('admin/queue-monitor*') ? 'active is-expanded' : '' }}" data-bs-toggle="slide" href="{{ route('queue-monitor.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640" fill="currentColor">
                            <path d="M192 64C156.7 64 128 92.7 128 128L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.2L386.7 82.7C374.7 70.7 358.5 64 341.5 64L192 64zM453.5 240L360 240C346.7 240 336 229.3 336 216L336 122.5L453.5 240z"/>
                        </svg>
                        <span class="side-menu__label"> Queue Monitor</span>
                    </a>
                </li>
            <li class="slide {{ request()->is('admin/globaltix*') ? 'active is-expanded' : '' }}">
                    <a class="side-menu__item has-link {{ request()->is('admin/globaltix*') ? 'active is-expanded' : '' }}" data-bs-toggle="slide" href="{{ route('admin.globaltix.health') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640" fill="currentColor">
                            <path d="M416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 512C544 547.3 515.3 576 480 576L160 576C124.7 576 96 547.3 96 512L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64zM256 240C238.3 240 224 254.3 224 272L224 352C224 369.7 238.3 384 256 384L384 384C401.7 384 416 369.7 416 352L416 272C416 254.3 401.7 240 384 240L256 240zM320 480C337.7 480 352 465.7 352 448C352 430.3 337.7 416 320 416C302.3 416 288 430.3 288 448C288 465.7 302.3 480 320 480z"/>
                        </svg>
                        <span class="side-menu__label"> GlobalTix Health</span>
                    </a>
                </li>
            <li class="slide {{ request()->is('admin/bokun*') ? 'active is-expanded' : '' }}">
                    <a class="side-menu__item has-link {{ request()->is('admin/bokun*') ? 'active is-expanded' : '' }}" data-bs-toggle="slide" href="{{ route('admin.bokun.health') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640" fill="currentColor">
                            <path d="M416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 512C544 547.3 515.3 576 480 576L160 576C124.7 576 96 547.3 96 512L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64zM256 240C238.3 240 224 254.3 224 272L224 352C224 369.7 238.3 384 256 384L384 384C401.7 384 416 369.7 416 352L416 272C416 254.3 401.7 240 384 240L256 240zM320 480C337.7 480 352 465.7 352 448C352 430.3 337.7 416 320 416C302.3 416 288 430.3 288 448C288 465.7 302.3 480 320 480z"/>
                        </svg>
                        <span class="side-menu__label"> Bokun Health</span>
                    </a>
                </li>
            <li class="slide {{ request()->is('admin/activity*') ? 'active is-expanded' : '' }}">
                    <a class="side-menu__item has-link {{ request()->is('admin/activity*') ? 'active is-expanded' : '' }}" data-bs-toggle="slide" href="{{ route('activity.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640">
                            <path
                                d="M552 256L408 256C398.3 256 389.5 250.2 385.8 241.2C382.1 232.2 384.1 221.9 391 215L437.7 168.3C362.4 109.7 253.4 115 184.2 184.2C109.2 259.2 109.2 380.7 184.2 455.7C259.2 530.7 380.7 530.7 455.7 455.7C463.9 447.5 471.2 438.8 477.6 429.6C487.7 415.1 507.7 411.6 522.2 421.7C536.7 431.8 540.2 451.8 530.1 466.3C521.6 478.5 511.9 490.1 501 501C401 601 238.9 601 139 501C39.1 401 39 239 139 139C233.3 44.7 382.7 39.4 483.3 122.8L535 71C541.9 64.1 552.2 62.1 561.2 65.8C570.2 69.5 576 78.3 576 88L576 232C576 245.3 565.3 256 552 256z"/>
                        </svg>
                        <span class="side-menu__label"> Activity Logs</span>
                    </a>
                </li>
                @endif
            <li class="slide">
                <a class="side-menu__item has-link" data-bs-toggle="slide" href="javascript:void(0);"
                    onclick="confirmLogout()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 640 640">
                        <path
                            d="M569 337C578.4 327.6 578.4 312.4 569 303.1L425 159C418.1 152.1 407.8 150.1 398.8 153.8C389.8 157.5 384 166.3 384 176L384 256L272 256C245.5 256 224 277.5 224 304L224 336C224 362.5 245.5 384 272 384L384 384L384 464C384 473.7 389.8 482.5 398.8 486.2C407.8 489.9 418.1 487.9 425 481L569 337zM224 160C241.7 160 256 145.7 256 128C256 110.3 241.7 96 224 96L160 96C107 96 64 139 64 192L64 448C64 501 107 544 160 544L224 544C241.7 544 256 529.7 256 512C256 494.3 241.7 480 224 480L160 480C142.3 480 128 465.7 128 448L128 192C128 174.3 142.3 160 160 160L224 160z" />
                    </svg>

                    <span class="side-menu__label"> Logout</span>
                </a>
            </li>
            </ul>

            <div class="slide-right" id="slide-right">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24"
                    viewBox="0 0 24 24">
                    <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z" />
                </svg>
            </div>
        </div>
    </div>
</div>