//
import './bootstrap'; // 最初からある場合はそのまま残す
import { createApp } from 'vue';

// 1. 作成したVueコンポーネントをインポートする
import ExampleComponent from './components/ExampleComponent.vue';

// 2. Vueアプリケーションを作成する
const app = createApp({});

// 3. コンポーネントを登録する（Blade側で <example-component> として使えるようになります）
app.component('example-component', ExampleComponent);

// 4. Blade側にある <div id="app"> にマウント（適用）する
app.mount('#app');
