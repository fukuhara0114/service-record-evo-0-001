<!-- resources/views/app.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>マイアプリ</title>
    <!-- ViteでCSSとJS（Vue）を読み込む -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <!-- Vueのコードがマウントされる場所（例: <div id="app">） -->
    <div id="app">
        <!-- ここにVueコンポーネントを配置する -->
        <example-component></example-component>
    </div>

    <!-- ログアウト用のフォーム（Vue側からボタン等で送信できるように用意） -->
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
</body>
</html>
