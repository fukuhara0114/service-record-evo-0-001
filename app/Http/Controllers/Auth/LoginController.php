<?php
// app/Http/Controllers/Auth/LoginController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    // ログイン画面を表示する
    // ※Vue（Inertia）を使う場合は Inertia::render('Auth/Login') に書き換えてください
    public function showLoginForm()
    {
        return view('auth.login'); 
    }

    // ログイン処理
    public function login(Request $request)
    {
        // バリデーション（emailではなくnameを必須にする）
        $credentials = $request->validate([
            'name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 認証試行
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // セッションの再生成（セッション固定攻撃対策）
            $request->session()->regenerate();

            // 意図したページ（今回はルート /）へリダイレクト
            return redirect()->intended('/');
        }

        // 認証失敗時
        return back()->withErrors([
            'name' => 'ユーザー名またはパスワードが正しくありません。',
        ])->onlyInput('name');
    }

    // ログアウト処理
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
