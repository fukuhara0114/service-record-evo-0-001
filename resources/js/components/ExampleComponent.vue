<template>
  <div class="welcome-box">
    <h1>ログインに成功しました！Vueが動いています。</h1>
    <p>テストコンポーネントの表示に成功しました。</p>
    <button type="button" @click="logout" class="logout-btn">ログアウト</button>
  </div>
</template>

<script>
export default {
  name: 'ExampleComponent',
  methods: {
    logout() {
      // Blade側にあるログアウトフォームを強制送信する
      const form = document.getElementById('logout-form');
      if (form) {
        form.submit();
      } else {
        console.error('ログアウトフォームが見つかりません');
      }
    }
  }
}
</script>

<style scoped>
.welcome-box {
  padding: 20px;
  border: 1px solid #ccc;
  border-radius: 8px;
  background-color: #f9f9f9;
}
.logout-btn {
  background-color: #ff4444;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
}
</style>
