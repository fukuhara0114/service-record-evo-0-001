import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { handleUnauthorizedStatus } from './utils/auth';

document.addEventListener('inertia:error', (event) => {
    const status = event.detail?.response?.status;
    handleUnauthorizedStatus(status);
});

createInertiaApp({
  // コントローラーで指定された文字列（'ServiceRecordList'など）から
  // pagesフォルダ内のVueファイルを自動で探して読み込む設定です
  resolve: name => {
    const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
    const path = `./pages/${name.replace(/\./g, '/')}.vue`;
    const page = pages[path];
    if (!page) {
      throw new Error(`Page not found: ${path}`);
    }
    return page.default;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
