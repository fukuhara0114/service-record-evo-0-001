<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー管理システム</title>
    @vite('resources/js/app.js')
</head>
<body>
    <!-- ⚠️ 必ず id="app" の内側に書く必要があります -->
    <div id="app">
        <h1>管理画面</h1>

        <!-- ⚠️ 必ず小文字・ハイフン繋ぎで、app.jsで指定した名前と一致させます -->
        <user-list></user-list>
    </div>
</body>
</html>