<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* デスクトップ: 現状維持（〜767 / 768〜1023 のみ変化） */
        .login-card {
            background-color: #2563eb;
            color: #fff;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            width: 30vw;
            max-width: 400px;
            height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-y: auto;
        }

        /* タブレット・スマホ: ログインカードを画面一杯に */
        @media (max-width: 1023px) {
            body.login-body {
                padding: 0 !important;
                align-items: stretch !important;
                justify-content: stretch !important;
            }

            .login-card {
                width: 100vw;
                max-width: none;
                height: 100dvh;
                min-height: 100vh;
                border-radius: 0;
                box-shadow: none;
                padding: 2rem 1.5rem;
                justify-content: center;
                gap: 1.5rem;
            }

            .login-card h2 {
                font-size: 1.75rem;
            }

            .login-card form {
                max-width: 420px;
                width: 100%;
                margin: 0 auto;
            }

            .login-card label {
                font-size: 0.875rem;
            }

            .login-card input {
                padding: 0.75rem 0.875rem;
                font-size: 1rem;
            }

            .login-card button[type="submit"] {
                padding: 0.875rem 1rem;
                font-size: 1rem;
                margin-top: 0.75rem;
            }
        }
    </style>
</head>
<body class="login-body bg-gray-300 flex items-center justify-center min-h-screen p-4">

    <div class="login-card">
        <h2 class="font-times text-2xl font-bold text-center">XRite Service Record</h2>

        @if ($errors->any())
            <div class="bg-white text-red-600 p-2 rounded text-xs shadow-inner">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ url('/login') }}" class="space-y-3 flex-1 flex flex-col justify-center">
            @csrf

            <div>
                <label for="name" class="block text-xs font-medium mb-1">ユーザー名(name)</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                    class="w-full px-3 py-1.5 text-gray-900 border border-transparent rounded-lg bg-white/90 focus:bg-white focus:ring-2 focus:ring-blue-400 outline-none text-sm transition">
            </div>

            <div>
                <label for="password" class="block text-xs font-medium mb-1">パスワード</label>
                <input type="password" id="password" name="password" required
                    class="w-full px-3 py-1.5 text-gray-900 border border-transparent rounded-lg bg-white/90 focus:bg-white focus:ring-2 focus:ring-blue-400 outline-none text-sm transition">
            </div>

            <button type="submit"
                class="w-full bg-white text-blue-600 hover:bg-orange-500 hover:text-white font-bold py-2 px-4 rounded-lg shadow transition duration-200 text-sm mt-2">
                ログイン
            </button>
        </form>
    </div>

</body>
</html>
