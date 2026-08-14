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

    body {
        display: flex !important;
        flex-direction: column !important;
    }

    /* ① 検索窓エリア：最上部固定（フィルター群は中央） */
    .fixed-header-zone {
        position: relative !important;
        flex: 0 0 auto !important;
        width: 100% !important;
        background-color: #ffffff !important;
        border-bottom: 2px solid #3b82f6 !important;
        z-index: 99999 !important;
        padding: 10px 56px 10px 20px !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-wrap: nowrap !important;
        align-items: flex-end !important;
        justify-content: center !important;
        gap: 12px !important;
    }

    .header-filters-center {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-end;
        justify-content: center;
        gap: 12px;
        max-width: 100%;
        min-width: 0;
    }

    .query-search-row {
        display: flex;
        flex-wrap: nowrap;
        gap: 10px;
        align-items: flex-end;
        margin-bottom: 0;
        flex: 0 0 auto;
        min-width: 0;
    }

    .query-field {
        display: flex;
        flex-direction: column;
        gap: 3px;
        width: 200px;
        max-width: 200px;
        min-width: 200px;
        flex: 0 0 200px;
        box-sizing: border-box;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }

    .query-field-year {
        width: 120px;
        max-width: 120px;
        min-width: 120px;
        flex: 0 0 120px;
    }

    .query-field input,
    .query-field select {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        padding: 6px 8px;
        border: 1px solid #94a3b8;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 700;
    }

    .query-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        padding-bottom: 1px;
        flex: 0 0 auto;
    }

    .query-btn {
        padding: 6px 14px;
        border: none;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        color: #fff;
        background: #2563eb;
    }

    .query-btn-secondary {
        background: #64748b;
    }

    .quick-filter-inline {
        display: flex !important;
        align-items: flex-end !important;
        gap: 8px;
        flex: 0 0 auto;
        min-width: 220px;
        padding-bottom: 1px;
    }

    .quick-filter-inline input {
        flex: 0 0 280px;
        width: 280px;
        min-width: 160px;
        max-width: 320px;
        padding: 6px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
    }

    .result-count {
        flex: 0 0 auto;
        align-self: flex-end;
        padding-bottom: 8px;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
        white-space: nowrap;
    }

    .header-home {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        margin-left: 0;
    }

    /* ② テーブルエリア：内側スクロール */
    .scrollable-table-zone {
        position: relative !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
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
@php
    $filters = $filters ?? ['dealer' => '', 'productName' => '', 'SN' => '', 'endUser' => '', 'year' => null];
    $yearOptions = $yearOptions ?? [];
    $selectedYear = $filters['year'] ?? null;
@endphp

    <!-- ① 検索窓（query設定 + Quick Filter を中央配置） -->
    <div class="fixed-header-zone">
        <div class="header-filters-center">
            <form method="get" action="{{ url('/servicerecord_q') }}" class="query-search-row">
                <label class="query-field">
                    <span>dealer</span>
                    <input type="text" name="dealer" value="{{ $filters['dealer'] ?? '' }}" placeholder="dealer">
                </label>
                <label class="query-field">
                    <span>productName</span>
                    <input type="text" name="productName" value="{{ $filters['productName'] ?? '' }}" placeholder="productName">
                </label>
                <label class="query-field">
                    <span>SN</span>
                    <input type="text" name="SN" value="{{ $filters['SN'] ?? '' }}" placeholder="SN">
                </label>
                <label class="query-field">
                    <span>endUser</span>
                    <input type="text" name="endUser" value="{{ $filters['endUser'] ?? '' }}" placeholder="endUser">
                </label>
                <label class="query-field query-field-year">
                    <span>受注年</span>
                    <select name="year">
                        <option value="" @selected($selectedYear === null)>過去1年</option>
                        <option value="all" @selected($selectedYear === 'all')>全件</option>
                        @foreach ($yearOptions as $year)
                            <option value="{{ $year }}" @selected($selectedYear === (int) $year)>
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <div class="query-actions">
                    <button type="submit" class="query-btn">検索</button>
                    <a href="{{ url('/servicerecord_q') }}" class="query-btn query-btn-secondary" style="text-decoration:none;display:inline-flex;align-items:center;">クリア</a>
                </div>
            </form>

            <div class="quick-filter-inline">
                <label for="customSearchInput" style="font-weight: bold; font-size: 14px; white-space: nowrap; padding-bottom: 6px;">Quick Filer:</label>
                <input type="text" id="customSearchInput" placeholder="キーワードを入力してください（スペース区切りで複数検索可能）...">
                <button type="button" onclick="clearSearchInput()"
                        style="padding: 6px 16px; background-color: #6b7280; color: white; border: none; border-radius: 4px; font-size: 14px; font-weight: bold; cursor: pointer; white-space: nowrap;"
                        onmouseover="this.style.backgroundColor='#4b5563'"
                        onmouseout="this.style.backgroundColor='#6b7280'">
                    Clear
                </button>
                <span id="resultCount" class="result-count" aria-live="polite"></span>
            </div>
        </div>

        <div class="header-home">
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
                    <th style="width:  80px;">受注日</th>
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
    const allRecords = {!! $records->toJson() !!};
    const appBaseUrl = @json(url('/'));
    const queryHitCount = Array.isArray(allRecords) ? allRecords.length : 0;

    function updateResultCount(visibleCount) {
        const el = document.getElementById('resultCount');
        if (!el) return;
        const visible = Number(visibleCount) || 0;
        if (visible === queryHitCount) {
            el.textContent = `ヒット: ${queryHitCount}件`;
        } else {
            el.textContent = `表示: ${visible}件 / ヒット: ${queryHitCount}件`;
        }
    }

    function renderTable(filteredData) {
        const tbody = document.getElementById('tableBody');
        let html = '';
        const rows = Array.isArray(filteredData) ? filteredData : [];

        for (let i = 0; i < rows.length; i++) {
            const r = rows[i];

            html += `<tr  class="table-row"
                        onclick="selectRow(this, '${r.orderID || ''}')"
                        ondblclick="goToDetailPage('${r.orderID || ''}', '${r.order_type || 'service'}')"
                        style="cursor: pointer;"
                    >
                <td style="text-align: center;" >${r.orderID || ''}</td>
                <td style="text-align: center;" >${r.receivedDate || ''}</td>
                <td style="text-align: center;" >${r.orderDate || ''}</td>
                <td                             >${statusLabel(r)}</td>
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
        updateResultCount(rows.length);
    }
    renderTable(allRecords);

    function statusLabel(r) {
        if (r?.order_type === 'waiting_list') return '';
        if (r?.order_type === 'loaner') return r.status_master_loaner?.status || '';
        return r.status_master?.status || '';
    }

    document.getElementById('customSearchInput').addEventListener('input', function() {
        const searchValue = this.value.toLowerCase().replace(/　/g, ' ');
        const keywords = searchValue.split(' ').filter(keyword => keyword.trim() !== '');

        if (keywords.length === 0) {
            renderTable(allRecords);
            return;
        }

        const filtered = allRecords.filter(r => {
            const combinedText = [
                r.receivedDate, r.orderDate, r.productName, r.SN, r.endUser,
                r.endUser_depart, r.endUser_contactPerson,
                r.endUser_address1, r.endUser_address2,
                r.endUser_email, r.endUser_phone, r.dealer, r.dealer_depart
            ].join(' ').toLowerCase();

            return keywords.every(keyword => combinedText.includes(keyword));
        });

        renderTable(filtered);
    });

    function clearSearchInput() {
        const input = document.getElementById('customSearchInput');
        if (!input) return;
        input.value = '';
        if (typeof allRecords !== 'undefined' && typeof renderTable === 'function') {
            renderTable(allRecords);
        }
        input.focus();
    }

    function selectRow(rowElement, orderId) {
        const allRows = document.querySelectorAll('.table-row');
        allRows.forEach(row => {
            row.classList.remove('active-row');
        });
        rowElement.classList.add('active-row');
        console.log("選択された行の orderID:", orderId);
    }

    function goToDetailPage(orderId, orderType) {
        if (!orderId) return;
        const base = String(appBaseUrl || '/').replace(/\/?$/, '/');
        const callerUrl = window.location.href;
        const returnUrl = encodeURIComponent(callerUrl);
        const type = String(orderType || 'service');

        // 詳細クローズ時の復帰先（クエリ欠落対策）
        try {
            sessionStorage.setItem('sr_list_return_url', callerUrl);
        } catch (e) {
            // ignore
        }

        // admin と同様: loaner / waiting_list は貸出詳細、それ以外はサービス詳細（Admin一覧の詳細UI）
        if (type === 'loaner' || type === 'waiting_list') {
            window.location.href = `${base}servicerecord/loaner/detail/${orderId}?returnUrl=${returnUrl}`;
            return;
        }

        const params = new URLSearchParams({
            orderType: 'service',
            arrival: 'hide_future',
            openOrderID: String(orderId),
            returnUrl: callerUrl,
        });
        window.location.href = `${base}servicerecord/administrator?${params.toString()}`;
    }
    </script>

</body>
</html>
