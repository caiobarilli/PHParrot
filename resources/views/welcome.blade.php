<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-bg text-ink">
    <main class="mx-auto max-w-3xl px-4 py-16 text-center">
        <h1 class="text-4xl font-semibold">{{ config('app.name') }}</h1>
    </main>
</body>
</html>
