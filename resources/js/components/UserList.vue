<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

// ユーザー一覧を保存するリアクティブな配列
const users = ref([])

// 画面が読み込まれた時に実行される処理
onMounted(() => {
  fetchUsers()
})

// LaravelのAPIからデータを取得する関数
const fetchUsers = async () => {
  try {
    const response = await axios.get(base_url +'/api/users')

    users.value = response.data

  } catch (error) {
    console.error('ユーザーデータの取得に失敗しました:', error)
  }
}
</script>

<template>
  <div class="user-list-container">
    <h2>ユーザー一覧</h2>
    
    <table border="1" style="width: 100%; border-collapse: collapse; text-align: left;">
      <thead>
        <tr style="background-color: #f2f2f2;">
          <th>ID</th>
          <th>名前</th>
          <th>漢字氏名</th>
          <th>メールアドレス</th>
          <th>権限</th>
          <th>労働ID</th>
        </tr>
      </thead>
      <tbody>
        <!-- データが空の場合 -->
        <tr v-if="users.length === 0">
          <td colspan="6" style="text-align: center; padding: 10px;">データがありません</td>
        </tr>
        
        <!-- ループ処理でテーブルの各行を出力 -->
        <tr v-for="user in users" :key="user.userID">
          <td>{{ user.userID }}</td>
          <td>{{ user.name }}</td>
          <td>{{ user.kanji_name }}</td>
          <td>{{ user.email }}</td>
          <td>{{ user.permission }}</td>
          <td>{{ user.laborID }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.user-list-container {
  padding: 20px;
  font-family: sans-serif;
}
th, td {
  padding: 8px 12px;
}
</style>
