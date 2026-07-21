import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3'; 
createInertiaApp({
  // コントローラーで指定された文字列（'ServiceRecordList'など）から
  // pagesフォルダ内のVueファイルを自動で探して読み込む設定です
  resolve: name => {
    const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
    return pages[`./pages/${name}.vue`].default;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
