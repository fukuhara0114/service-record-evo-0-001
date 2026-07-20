<!-- resources/views/home.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ホーム画面</title>
    <style>
        /* ページ全体の背景を少し暗く、フォントサイズを指定 */
        body {
            background-color: #f5f5f5;
            font-family: sans-serif;
            font-size: 16px;
            margin: 0;
            padding: 40px;
            text-align: center;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 30px;
        }
        /* メニューボタンのスタイル */
        .menu-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 300px;
            margin: 0 auto;
        }
        .btn {
            display: block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: bold;
            transition: background 0.2s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        /* ログアウトボタン用のスタイル */
        .logout-btn {
            margin-top: 50px;
            background: none;
            border: none;
            color: #ff4444;
            cursor: pointer;
            font-size: 14px;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <h1>メニューを選択してください</h1>

    <div class="menu-container">
        <!-- Vueアプリ（トップページ）へ遷移するボタン -->
        {{-- <a href="{{ url('/servicerecord') }}" class="btn">Vueアプリケーションを開く</a>
        <a href="{{ url('/servicerecord/administrator') }}" class="btn">Vueアプリケーションを開く</a> --}}
        <a href="{{ url('/servicerecord_q') }}" class="btn">ServiceRecord 一覧</a>
        {{-- <a href="{{ url('/') }}" class="btn">Vueアプリケーションを開く</a>
        <a href="{{ url('/') }}" class="btn">Vueアプリケーションを開く</a>
        <a href="{{ url('/') }}" class="btn">Vueアプリケーションを開く</a> --}}
        
        <!-- 今後、別のBladeページなどを増やす場合はここにボタンを追加できます -->
        <!-- <a href="{{ url('/other-page') }}" class="btn">別のページを開く</a> -->
    </div>

    <!-- ログアウト用の簡易リンク -->
    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="logout-btn">ログアウトする</button>
    </form>

</body>
</html>
