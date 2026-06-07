@extends('layouts.app')


@section('content')
    <!-- ⚠️ 必ず id="app" の内側に書く必要があります -->
    <div id="app">
        <h1>管理画面</h1>

        <!-- ⚠️ 必ず小文字・ハイフン繋ぎで、app.jsで指定した名前と一致させます -->
        <user-list></user-list>
    </div>
@endsection