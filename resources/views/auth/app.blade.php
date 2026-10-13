<!doctype html>
<html lang="en" dir="ltr">

<meta http-equiv="content-type" content="text/html;charset=UTF-8" />

<head>
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="author" content="bdCalling IT Ltd.">
    <meta name="keywords" content="pure_water_innovation">

    <title>@yield('title')</title>

    @include('auth.partials.styles')
</head>

<body class="ltr login-img">

    {{-- GLOBAL-LOADER --}}
    <div id="global-loader">
        <img src=" {{ asset('backend') }}/images/loader.svg" class="loader-img" alt="Loader">
    </div>

    <!-- PAGE -->
    <div class="page">
        <div>
            {{-- Header --}}
            @include('auth.partials.header')
            {{-- Header --}}

            <div class="container-login100">
                @yield('content')
            </div>
            <!-- CONTAINER CLOSED -->
        </div>
    </div>
    <!-- End PAGE -->

    @include('auth.partials.scripts')

</body>


</html>
