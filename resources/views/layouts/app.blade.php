<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name', 'HomeTrans CRM') }}</title>

    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">

    {{-- Load fonts for consistent typography. --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    {{-- Bootstrap 5 styles and custom overrides.--}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ config('app.version') }}">
</head>
<body class="bg-light">
<div class="d-flex flex-column min-vh-100">
    @include('partials.header')

    {{-- Remove vertical padding for case chat routes --}}
    <main class="container {{ request()->is('case/*') ? '' : 'py-4' }} flex-grow-1">
        @yield('content')
    </main>

    {{-- Unified site footer. --}}
    <footer class="bg-white border-top py-3 mt-auto">
        <div class="container text-center text-muted small">
            &copy; {{ date("Y") }} <a class="text-dark" href="https://hometrans.uk/" target="_blank">HomeTrans</a> CRM. All content is private.
        </div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

@stack('scripts')
</body>
</html>
