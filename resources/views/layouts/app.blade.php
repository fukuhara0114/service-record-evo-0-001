<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Home</title>

    {{-- ☆☆☆☆☆☆　javascriptに共通のルートディレクトリを教える為のスクリプト ↓開始　☆☆☆☆☆☆ 
        これを入れる事で@vite(['resources/css/app.css', 'resources/js/app.js'])で読み込まれる'resources/js/app.js'で"baseUrl"をルートディレクトリとして使える様になる
    --}}
    <script>
        window.App = {
            baseUrl: "{{ url('/') }}"
        };
    </script>
    {{--　☆☆☆☆☆☆　javascriptに共通のルートディレクトリを教える為のスクリプト ↑終了 ☆☆☆☆☆☆  --}}


    {{-- ✅ Tailwind（Vite経由）--}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{-- <body class="bg-light"> --}}
<body class="bg-gray-100">
    {{-- @yield('header') --}}

    @yield('content')

    {{-- @yield('footer') --}}
     @livewireScripts 
</body>
</html>