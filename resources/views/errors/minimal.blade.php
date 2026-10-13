<!doctype html>
<html lang="en" dir="ltr">
<meta http-equiv="content-type" content="text/html;charset=UTF-8" /><!-- /Added by HTTrack -->
<head>

		<!-- META DATA -->
		<meta charset="UTF-8">
		<meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="author" content="bdCalling IT Ltd.">
		<meta name="keywords" content="laravel admin template, bootstrap admin template, admin dashboard template, admin dashboard, admin template, admin, bootstrap 5, laravel admin, laravel admin dashboard template, laravel ui template, laravel admin panel, admin panel, laravel admin dashboard, laravel template, admin ui dashboard">

        <!-- TITLE -->
		<title>@yield('title')</title>

        @include('auth.partials.styles')
    </head>


        <body class="ltr error-bg">

            <!-- GLOBAL-LOADER -->
            <div id="global-loader">
                <img src="{{asset('backend/images/loader.svg')}} " class="loader-img" alt="Loader">
            </div>

		<!-- PAGE -->
		<div class="page">
            <!-- PAGE-CONTENT OPEN -->
            @yield('content')
             <!-- PAGE-CONTENT OPEN CLOSED -->
         </div>
         <!-- End PAGE -->


      @include('auth.partials.scripts')

    </body>
</html>
