<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>App</title>
        @isset($page)
            @inertiaHead
        @endisset
        <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    </head>
    <body>
        @isset($page)
            @inertia
        @else
            <div id="app"></div>
        @endisset
        <script src="{{ asset('assets/js/app.js') }}" defer></script>
    </body>
</html>


