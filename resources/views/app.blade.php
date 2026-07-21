<!-- resources/views/app.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>マイアプリ</title>
    <!-- ViteでCSSとJS（Vue）を読み込む -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <!-- Vueのコードがマウントされる場所（例: <div id="app">） -->
    @inertia
</body>
</html>
