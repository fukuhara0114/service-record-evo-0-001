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
    
    axios.defaults.baseURL = window.App?.baseUrl || '/';
    const response = await axios.get('api/users')

    users.value = response.data

  } catch (error) {
    console.error('ユーザーデータの取得に失敗しました:', error)
  }
}
</script>

<template>
  <div class="user-list-container max-w-6xl mx-auto p-6 bg-white rounded-xl shadow-sm border border-gray-100">
  <!-- タイトル -->
  <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
    ユーザー一覧
  </h2>
  
  <!-- レスポンシブ用の横スクロールラッパー -->
  <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
    <table class="w-full border-collapse bg-white text-left text-sm text-gray-600">
      <!-- テーブルヘッダー -->
      <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-700 border-b border-gray-200">
        <tr>
          <th scope="col" class="px-6 py-4">ID</th>
          <th scope="col" class="px-6 py-4">名前</th>
          <th scope="col" class="px-6 py-4">漢字氏名</th>
          <th scope="col" class="px-6 py-4">メールアドレス</th>
          <th scope="col" class="px-6 py-4">権限</th>
          <th scope="col" class="px-6 py-4">労働ID</th>
        </tr>
      </thead>
      
      <!-- テーブルボディ -->
      <tbody class="divide-y divide-gray-100">
        <!-- データが空の場合 -->
        <tr v-if="users.length === 0">
          <td colspan="6" class="px-6 py-10 text-center text-gray-400 bg-gray-50/50">
            データがありません
          </td>
        </tr>
        
        <!-- ループ処理でテーブルの各行を出力 -->
        <tr 
          v-for="user in users" 
          :key="user.userID"
          class="hover:bg-gray-50/70 transition-colors"
        >
          <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">{{ user.userID }}</td>
          <td class="px-6 py-4 whitespace-nowrap">{{ user.name }}</td>
          <td class="px-6 py-4 whitespace-nowrap">{{ user.kanji_name }}</td>
          <td class="px-6 py-4">{{ user.email }}</td>
          <td class="px-6 py-4 whitespace-nowrap">
            <!-- 権限を少しバッジっぽく目立たせる装飾（お好みで） -->
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
              {{ user.permission }}
            </span>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ user.laborID }}</td>
        </tr>
      </tbody>
    </table>
  </div>
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
