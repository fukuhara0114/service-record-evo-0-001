<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン</title>
    <style>
        .error { color: red; }
        .form-group { margin-bottom: 15px; }
    </style>
</head>
<body>
    <h2>ログイン</h2>

    <!-- エラーメッセージの表示 -->
    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ url('/login') }}">
        @csrf <!-- セキュリティ対策（必須） -->

        <div class="form-group">
            <label for="name">ユーザー名(name):</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">パスワード:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit">ログイン</button>
    </form>
</body>
</html>
