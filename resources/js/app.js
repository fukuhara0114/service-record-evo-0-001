//
import './bootstrap';
import { createApp } from 'vue';
import UserList from './components/UserList.vue'; // 追加

const app = createApp({});

// <user-list /> というカスタムタグ名で登録
app.component('user-list', UserList);

app.mount('#app');
