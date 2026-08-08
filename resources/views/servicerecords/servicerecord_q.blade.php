<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>サービスレコード一覧</title>
    
    <script src="https://jquery.com"></script>

    {{-- <style>
        /* 画面全体の縦スクロールを禁止 */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            height: 100% !important;
            overflow: hidden !important;
            font-family: sans-serif;
        }

        /* ① 検索窓エリア：最上部に絶対固定 */
        .fixed-header-zone {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 60px !important;
            background-color: #ffffff !important;
            border-bottom: 2px solid #3b82f6 !important;
            z-index: 99999 !important;
            padding: 14px 20px !important;
            box-sizing: border-box !important;
        }

        /* ② テーブルエリア：この箱の中だけをスクロールさせる */
        .scrollable-table-zone {
            position: absolute !important;
            top: 60px !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            overflow: auto !important;
            padding: 20px !important;
            box-sizing: border-box !important;
        }

        /* ③ テーブルヘッダーの固定 */
        #myLargeTable {
            width: 100% !important;
            table-layout: fixed !important;
            border-collapse: collapse !important;
            border: 1px solid #d1d5db !important;
        }
        #myLargeTable thead th {
            position: -webkit-sticky !important;
            position: sticky !important;
            top: 0 !important;
            background-color: #f3f4f6 !important;
            z-index: 9999 !important;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
        }

        /* 上下の罫線とホバー設定 */
        #myLargeTable tbody tr td {
            border-top: 1px solid #e5e7eb !important;
            border-bottom: 1px solid #e5e7eb !important;
            padding: 10px 12px !important;
        }
        #myLargeTable tbody tr:hover td {
            background-color: #f3f4f6 !important;
        }
        #myLargeTable th, #myLargeTable td {
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }
    </style> --}}
    <style>
    /* 画面全体の縦スクロールを禁止 */
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        height: 100% !important;
        overflow: hidden !important;
        font-family: sans-serif;
        font-size:12px;
    }

    /* ① 検索窓エリア：最上部に絶対固定 */
    .fixed-header-zone {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 60px !important;
        background-color: #ffffff !important;
        border-bottom: 2px solid #3b82f6 !important;
        z-index: 99999 !important;
        padding: 14px 20px !important;
        box-sizing: border-box !important;
    }

    /* ② テーブルエリア：内側スクロール */
    .scrollable-table-zone {
        position: absolute !important;
        top: 60px !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        overflow: auto !important;
        padding-left: 20px !important;
        padding-right: 20px !important;
        box-sizing: border-box !important;
    }

    /* ③ テーブルヘッダーの固定 */
    #myLargeTable {
        width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        border: 2px solid #1e1f21 !important;
    }
    #myLargeTable thead th {
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 0 !important;
        background-color: #0c51da !important;
        color:white;
        z-index: 9999 !important;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
        white-space: nowrap; /* ヘッダー文字は潰れないように改行禁止のままにします */
        border: 1px solid #333333 !important; 
    }

    /* 上下の罫線とホバー設定 */

    #myLargeTable tbody tr td {
        border: 1px solid #333333 !important;
        padding: 3px 4px !important;
        overflow: hidden !important;          /* 枠からはみ出た部分を隠す */
        vertical-align: top !important;       /* テキストを上揃えにする */

        white-space: nowrap !important;       /* 💡 絶対に自動改行させない */
        text-overflow: ellipsis !important;   /* 💡 はみ出た部分を「...」で省略表示する */
    }

    #myLargeTable_ tbody tr:hover td {
        color: white !important;
        background-color: #8147ad !important;
        
        /* 💡 改行禁止を解除し、長いテキストを枠内で強制折り返しさせる */
        /* white-space: normal !important;      
        word-break: break-all !important;     */
    }
    
    /* 共通設定（thのみに適用されるように調整） */
    #myLargeTable th {
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 10px 12px;
    }

    #myLargeTable tbody tr.active-row td {
        color: white !important;
        background-color: #a066e2 !important; /* 好みに応じて濃い青（#2563eb）などに調整してください */
        /* white-space: normal !important;      
        word-break: break-all !important; */
    }
</style>
</head>
<body>

    <!-- ① 自作検索窓 -->
    <div class="fixed-header-zone" style="display: flex !important; align-items: center !important; justify-content: space-between !important; padding: 14px 20px !important; box-sizing: border-box !important;">
    
    <!-- 💡 左側のスペースを埋めるためのダミー要素（これがあることで中央寄せが綺麗に決まります） -->
    <div style="flex: 1;"></div>

    <!-- 🚀 中央エリア：ラベルとインプットを横並びにして中央に配置 -->
    <div style="flex: 1; display: flex; justify-content: center; align-items: center; gap: 8px;">
        <label for="customSearchInput" style="font-weight: bold; margin-right: 2px; font-size: 14px; white-space: nowrap;">Quick Filer:</label>
        
        <input type="text" id="customSearchInput" placeholder="キーワードを入力してください（スペース区切りで複数検索可能）..." 
            style="width: 400px; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">

        <!-- 💡 クリアボタンを追加（マウスを乗せると少し色が薄くなります） -->
        <button type="button" onclick="clearSearchInput()" 
                style="padding: 6px 16px; background-color: #6b7280; color: white; border: none; border-radius: 4px; font-size: 14px; font-weight: bold; cursor: pointer; white-space: nowrap; transition: background-color 0.2s;"
                onmouseover="this.style.backgroundColor='#4b5563'" 
                onmouseout="this.style.backgroundColor='#6b7280'">
            Clear
        </button>
    </div>

    <!-- 🚀 右端エリア：Homeボタンを右寄せで配置 -->
    <div style="flex: 1; display: flex; justify-content: flex-end;">
        <a href="{{ url('/home') }}"
           class="close-to-home-btn"
           aria-label="閉じる"
           title="閉じる"
           style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:1px solid #94a3b8;border-radius:6px;background:#fff;color:#0f172a;text-decoration:none;font-size:22px;font-weight:700;line-height:1;">
            ×
        </a>
    </div>
</div>

    <!-- ② スクロールするテーブルのエリア -->
    <div class="scrollable-table-zone">
        <table id="myLargeTable" style="width:100%">
            <thead>
                <tr>
                    <th style="width:  80px;">ID</th>
                    <th style="width:  80px;">着荷日</th>
                    <th style="width: 120px;">Status</th>
                    <th style="width:  80px;">RMA</th>
                    <th style="width: 150px;">製品名</th>
                    <th style="width: 120px;">SN</th>
                    <th style="width: 100px;">作業内容</th>
                    <th style="width:  60px;">作業担当</th>
                    <th style="width: 200px;">販社</th>
                    <th style="width: 200px;">部署</th>
                    <th style="width: 100px;">担当者</th>
                    <th style="width: 200px;">E/U</th>
                    <th style="width: 200px;">E/U部署</th>
                    <th style="width: 100px;">E/U担当者</th>
                    <th style="width: 100px;">E/U都道府県</th>
                    <th style="width: 300px;">E/U住所</th>
                    <th style="width: 350px;">E/U Email</th>
                    <th style="width: 220px;">E/U Tel</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <!-- ここにはBladeのループは一切書きません。JavaScriptがデータを展開します -->
            </tbody>
        </table>
    </div>

    <!-- 3. 高速検索と超軽量描画のJavaScript -->
    <script>
    // Laravelから渡されたデータをJavaScriptの配列（JSON）として取得
    // const allRecords = @json($records);

    const allRecords = {!! $records->toJson() !!};
    console.log("【デバッグ】allRecordsの型:", typeof allRecords);
    console.log("【デバッグ】届いたデータの中身:", allRecords);

    // 💡 デバッグ用：ブラウザのF12コンソールに、statusMasterが届いているかをログ出力して確認します
    console.log("届いたデータの一番最初の1件:", allRecords[0]);

    // テーブルを描画する関数
    function renderTable(filteredData) {
        const tbody = document.getElementById('tableBody');
        let html = '';
        
        for (let i = 0; i < filteredData.length; i++) {
            const r = filteredData[i];
            
            html += `<tr  class="table-row" 
                        onclick="selectRow(this, '${r.orderID || ''}')" 
                        ondblclick="goToDetailPage('${r.orderID || ''}')" 
                        style="cursor: pointer;"
                    >
                <td style="text-align: center;" >${r.orderID || ''}</td>
                <td style="text-align: center;" >${r.receivedDate || ''}</td>
                <td                             >${r.status_master?.status || ''}</td>
                <td style="text-align: center;" >${r.RMA || ''}</td>
                <td                             >${r.productName || ''}</td>
                <td                             >${r.SN || ''}</td>
                <td style="text-align: center;" >${r.return_code_master?.description || ''}</td>
                <td style="text-align: center;" >${r.labor_master?.laborName || ''}</td>
                <td                             >${r.dealer || ''}</td>
                <td                             >${r.dealer_depart || ''}</td>
                <td                             >${r.contactPerson || ''}</td>
                <td                             >${r.endUser || ''}</td>
                <td                             >${r.endUser_depart || ''}</td>
                <td                             >${r.endUser_contactPerson || ''}</td>
                <td                             >${r.endUser_address1 || ''}</td>
                <td                             >${r.endUser_address2 || ''}</td>
                <td                             >${r.endUser_email || ''}</td>
                <td                             >${r.endUser_phone || ''}</td>
            </tr>`;
        }
        tbody.innerHTML = html;
    }
    // 初回読み込み時に全件表示を実行
    renderTable(allRecords);

    // 複数キーワード対応フィルター処理
    document.getElementById('customSearchInput').addEventListener('input', function() {
        const searchValue = this.value.toLowerCase().replace(/　/g, ' ');
        const keywords = searchValue.split(' ').filter(keyword => keyword.trim() !== '');

        if (keywords.length === 0) {
            renderTable(allRecords);
            return;
        }

        const filtered = allRecords.filter(r => {
            const combinedText = [
                r.receivedDate, r.productName, r.SN, r.endUser, 
                r.endUser_depart, r.endUser_contactPerson, 
                r.endUser_address1, r.endUser_address2, 
                r.endUser_email, r.endUser_phone,r.dealer, r.dealer_depart
            ].join(' ').toLowerCase();

            return keywords.every(keyword => combinedText.includes(keyword));
        });

        renderTable(filtered);
    });

    function clearSearchInput() {
        const input = document.getElementById('customSearchInput');
        if (!input) return;

        // 1. 入力欄の文字を完全に消去
        input.value = '';

        // 2. 以前作成した全件データ（allRecords）を renderTable 関数に渡してテーブルを初期状態に戻す
        if (typeof allRecords !== 'undefined' && typeof renderTable === 'function') {
            renderTable(allRecords);
        }

        // 3. 消した後にすぐ次の文字を打ち込めるよう、入力欄に自動でカーソルを合わせる（フォーカス）
        input.focus();
    }

    function selectRow(rowElement, orderId) {
        // 1. すべての行から、一旦「選択中クラス（active-row）」を消去して色をリセット
        const allRows = document.querySelectorAll('.table-row');
        allRows.forEach(row => {
            row.classList.remove('active-row');
        });

        // 2. クロックされた行だけに「選択中クラス」を付与して色を変える
        rowElement.classList.add('active-row');

        // 3. 【目的クリア】クリックされた行の orderID を取得してコンソールに表示
        console.log("選択された行の orderID:", orderId);

        // 💡 取得した orderID を使って次の処理（詳細ダイアログを開くなど）をしたい場合は、
        // ここに次の関数を記述します
        // openDetailModal(orderId);
    }

    function goToDetailPage(orderId) {
        console.log("ダブルクリックを検知しました。詳細ページへ移動します。orderID:", orderId);

        // 詳細ページへのURLを組み立て（お使いのRoute環境に合わせてパターンAかBを選んでください）
        
        // 【パターンA】URLの後ろにIDを繋げるタイプの場合
        const targetUrl = `${$rootPath}servicerecords/detail/${orderId}`;
        
        // 【パターンB】クエリパラメータ（?）でIDを渡すタイプの場合
        // const targetUrl = `${$rootPath}servicerecords/detail?orderID=${orderId}`;

        // 画面を遷移させる
        window.location.href = targetUrl;
    }
    </script>

</body>
</html>
