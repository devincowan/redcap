
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>REDCap - @yield('title')</title>
    <link rel="stylesheet" type="text/css" href="{{APP_PATH_CSS}}style.css" media="screen,print">
    <link rel="stylesheet" type="text/css" href="{{APP_PATH_WEBPACK}}css/bundle.css" media="screen,print">
    <link rel="shortcut icon" href="{{APP_PATH_IMAGES}}favicon.ico" type="image/x-icon">
</head>
<body>
<style>
body {
    margin: 10px auto;
}
.alert {
  border: 1px solid rgba(0, 0, 0, .1) !important;
}
</style>
    <div class="container">
        @section('sidebar')
        {{-- here goes sidebar content --}}
        <div class="sidebar">
            <a href="{{APP_PATH_WEBROOT_PARENT}}">
                <img src="{{APP_PATH_IMAGES}}redcap-logo.png" title="REDCap" style="height:45px;">
            </a>
        </div>
        @show

        <div class="content">
            @yield('content')
        </div>
    </div>
</body>
</html>