<!-- resources/views/home.blade.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ホーム画面</title>
    <style>
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

        .menu-container {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: nowrap;
            min-height: 60vh;
            width: 100%;
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
            min-width: 160px;
            text-align: center;
            border: none;
            cursor: pointer;
            box-sizing: border-box;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .btn-muted {
            background-color: #64748b;
        }

        .btn-muted:hover {
            background-color: #475569;
        }

        .logout-btn {
            margin-top: 50px;
            background: none;
            border: none;
            color: #ff4444;
            cursor: pointer;
            font-size: 14px;
            text-decoration: underline;
        }

        .menu-tablet {
            display: none;
        }

        .phone-redirect {
            display: none;
            min-height: 60vh;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 16px;
        }

        /* タブレット: Gallery / カメラ / Logistics */
        @media (min-width: 768px) and (max-width: 1023px) {
            body {
                padding: 24px;
            }

            .menu-desktop {
                display: none !important;
            }

            .menu-tablet {
                display: flex;
                flex-direction: column;
                max-width: 420px;
                min-height: 60vh;
                gap: 20px;
            }

            .menu-tablet .btn {
                width: 100%;
                min-width: 0;
                padding: 18px 24px;
                font-size: 20px;
            }
        }

        /* スマホ: カメラへ遷移（表示はリダイレクト待ちのみ） */
        @media (max-width: 767px) {
            .menu-desktop,
            .menu-tablet,
            .logout-form {
                display: none !important;
            }

            .phone-redirect {
                display: flex;
            }
        }
    </style>
</head>
<body>

    <div class="menu-container menu-desktop">
        <a href="{{ url('/servicerecord_q') }}" class="btn">ServiceRecord</a>
        <a href="{{ url('/servicerecord/administrator') }}" class="btn">Admin</a>
        <a href="{{ url('/servicerecord/engineer') }}" class="btn">Engineer</a>
        <a href="{{ url('/servicerecord/intake') }}" class="btn">案件登録</a>
        <a href="{{ url('/servicerecord/loaner/create') }}" class="btn">貸出機登録</a>
        <a href="{{ url('/servicerecord/loaner/calendar') }}" class="btn">貸出カレンダー</a>
        <a href="{{ url('/servicerecord/master-price-revision') }}" class="btn">価格改定</a>
        <a href="{{ url('/servicerecord/camera') }}" class="btn">カメラ</a>
        <a href="{{ url('/servicerecord/gallery') }}" class="btn">Gallery</a>
    </div>

    <div class="menu-container menu-tablet">
        <a href="{{ url('/servicerecord/gallery') }}" class="btn">Gallery</a>
        <a href="{{ url('/servicerecord/camera') }}" class="btn">カメラ</a>
        <button type="button" class="btn btn-muted" disabled title="未実装">Logistics</button>
    </div>

    <p class="phone-redirect">カメラ画面へ移動しています…</p>

    <form class="logout-form" action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" class="logout-btn">ログアウトする</button>
    </form>

    <script>
        (function () {
            var cameraUrl = @json(url('/servicerecord/camera'));
            var phoneMq = window.matchMedia('(max-width: 767px)');

            function redirectPhoneToCamera() {
                if (phoneMq.matches) {
                    window.location.replace(cameraUrl);
                }
            }

            redirectPhoneToCamera();

            if (typeof phoneMq.addEventListener === 'function') {
                phoneMq.addEventListener('change', redirectPhoneToCamera);
            } else if (typeof phoneMq.addListener === 'function') {
                phoneMq.addListener(redirectPhoneToCamera);
            }
        })();
    </script>

</body>
</html>
