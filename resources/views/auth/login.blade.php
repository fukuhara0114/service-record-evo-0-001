<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-300 flex items-center justify-center min-h-screen p-4">

    <!-- 
      【カードのカスタマイズ】
      ・bg-blue-600 : カードの背景色を青に設定
      ・w-[30vw] max-w-[400px] : 幅を30vw、最大400pxに制限
      ・h-[300px] : 高さを300pxに固定
      ・flex flex-col justify-between : 限られた高さの中で要素をきれいに収める設定
    -->
    <div class="bg-blue-600 text-white p-6 rounded-xl shadow-lg w-[30vw] max-w-[400px] h-[300px] flex flex-col justify-between overflow-y-auto">
        
        <h2 class="font-times text-2xl font-bold text-center">XRite Service Record</h2>

        <!-- エラーメッセージの表示（枠を白、文字を赤にして見やすくしています） -->
        @if ($errors->any())
            <div class="bg-white text-red-600 p-2 rounded text-xs shadow-inner">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ url('/login') }}" class="space-y-3 flex-1 flex flex-col justify-center">
            @csrf

            <!-- ユーザー名 -->
            <div>
                <label for="name" class="block text-xs font-medium mb-1">ユーザー名(name)</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                    class="w-full px-3 py-1.5 text-gray-900 border border-transparent rounded-lg bg-white/90 focus:bg-white focus:ring-2 focus:ring-blue-400 outline-none text-sm transition">
            </div>

            <!-- パスワード -->
            <div>
                <label for="password" class="block text-xs font-medium mb-1">パスワード</label>
                <input type="password" id="password" name="password" required
                    class="w-full px-3 py-1.5 text-gray-900 border border-transparent rounded-lg bg-white/90 focus:bg-white focus:ring-2 focus:ring-blue-400 outline-none text-sm transition">
            </div>

            <!-- ログインボタン -->
            <button type="submit" 
                class="w-full bg-white text-blue-600 hover:bg-blue-50 font-bold py-2 px-4 rounded-lg shadow transition duration-200 text-sm mt-2">
                ログイン
            </button>
        </form>
        
    </div>

</body>
</html>
