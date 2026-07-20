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
        white-space: nowrap; /* ヘッダー文字は潰れないように改行禁止のままにします */
    }

    /* 上下の罫線とホバー設定 */
    #myLargeTable tbody tr td {
        border-top: 1px solid #e5e7eb !important;
        border-bottom: 1px solid #e5e7eb !important;
        padding: 10px 12px !important;
        
        /* ★【最重要】枠線からはみ出る長いテキストを自動改行させる設定 */
        white-space: normal !important;      /* 改行禁止を解除し、自動折り返しを許可 */
        word-break: break-all !important;     /* 英語の長い単語やURL、長文も枠内で強制折り返し */
        vertical-align: top !important;       /* 複数行になったとき、テキストをセルの「上揃え」にする（見やすさ向上） */
    }
    
    #myLargeTable tbody tr:hover td {
        background-color: #f3f4f6 !important;
    }
    
    /* 共通設定（thのみに適用されるように調整） */
    #myLargeTable th {
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 10px 12px;
    }
</style>
</head>
<body>

    <!-- ① 自作検索窓 -->
    <div class="fixed-header-zone">
        <label for="customSearchInput" style="font-weight: bold; margin-right: 10px; font-size: 14px;">テーブル内一括フィルター:</label>
        <input type="text" id="customSearchInput" placeholder="キーワードを入力してください（スペース区切りで複数検索可能）..." 
               style="width: 400px; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
    </div>

    <!-- ② スクロールするテーブルのエリア -->
    <div class="scrollable-table-zone">
        <table id="myLargeTable" style="width:100%">
            <thead>
                <tr>
                    <th style="width: 120px;">着荷日</th>
                    <th style="width: 150px;">製品名</th>
                    <th style="width: 120px;">SN</th>
                    <th style="width: 250px;">E/U</th>
                    <th style="width: 250px;">E/U部署</th>
                    <th style="width: 120px;">E/U担当者</th>
                    <th style="width: 100px;">E/U都道府県</th>
                    <th style="width: 300px;">E/U住所</th>
                    <th style="width: 350px;">E/U Email</th>
                    <th style="width: 220px;">E/U Tel</th>
                    <th style="width: 150px;">販社</th>
                    <th style="width: 150px;">販社部署</th>
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
    const allRecords = @json($records);

    // テーブルを描画する関数
    function renderTable(filteredData) {
        const tbody = document.getElementById('tableBody');
        let html = '';
        
        for (let i = 0; i < filteredData.length; i++) {
            const r = filteredData[i];
            html += `<tr>
                <td>${r.receivedDate || ''}</td>
                <td>${r.productName || ''}</td>
                <td>${r.SN || ''}</td>
                <td>${r.endUser || ''}</td>
                <td>${r.endUser_depart || ''}</td>
                <td>${r.endUser_contactPerson || ''}</td>
                <td>${r.endUser_address1 || ''}</td>
                <td>${r.endUser_address2 || ''}</td>
                <td>${r.endUser_email || ''}</td>
                <td>${r.endUser_phone || ''}</td>
                <td>${r.dealer || ''}</td>
                <td>${r.dealer_depart || ''}</td>
                
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
    </script>

</body>
</html>
