/**
 * 出荷予定カレンダーの設定定数
 *
 * 1日あたりの出荷予定台数上限（目安）。
 * 超過しても登録は可能だが、カレンダー上で赤背景などにより超過を明示する。
 * サーバ側は config/shipping.php（.env: SHIPPING_DAILY_CAPACITY）と揃えること。
 */
export const SHIPPING_DAILY_CAPACITY = 8
