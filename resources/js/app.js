import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { handleUnauthorizedResponse, handleUnauthorizedStatus } from './utils/auth';

function onInertiaHttpException(event) {
    const response = event.detail?.response;
    if (handleUnauthorizedResponse(response)) {
        event.preventDefault?.();
        return false;
    }
}

// Inertia v3: httpException（v2 の invalid 相当）
router.on('httpException', onInertiaHttpException);

// 互換・フォールバック（環境差で DOM イベントのみ届く場合）
document.addEventListener('inertia:httpException', onInertiaHttpException);
document.addEventListener('inertia:invalid', onInertiaHttpException);
document.addEventListener('inertia:error', (event) => {
    handleUnauthorizedStatus(event.detail?.response?.status);
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
