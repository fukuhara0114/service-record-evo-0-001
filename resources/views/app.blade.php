<!-- resources/views/app.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ServiceRecord Evo</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base-url" content="{{ url('/') }}">
    <meta name="auth-kanji-name" content="{{ auth()->user()?->kanji_name }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <!-- Vueのコードがマウントされる場所（例: <div id="app">） -->
    @inertia
</body>
</html>
