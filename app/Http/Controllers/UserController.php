<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; 

class UserController extends Controller
{
    // ユーザー一覧をVueに返す処理
    public function index(): JsonResponse
    {
        $users = User::all();

        return response()->json($users);
    }

    // 新規登録の処理
    public function store(Request $request)
    {
        // Vueから送られてきたデータを保存
        $user = User::create([
            'name'       => $request->name,
            'kanji_name' => $request->kanji_name,
            'email'      => $request->email,
            'password'   => bcrypt($request->password), // 暗号化
            'permission' => $request->permission,
            'laborID'    => $request->laborID,
        ]);

        return response()->json(['message' => '登録しました', 'user' => $user]);
    }
}
