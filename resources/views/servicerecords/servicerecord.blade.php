@extends('layouts.app')


@section('content')
{{-- <body class="bg-gray-300 p-0 m-0 h-full"> --}}
    <!-- <h1 class="text-2xl font-bold mb-6">サービスレコード一覧</h1> -->
    <div class="flex justify-between items-center mb-6 px-10">
        <h1 class="text-3xl font-bold">{{$mode}}</h1>

        <input type="text" id="tableSearch" placeholder="キーワードで絞り込み..." class="border p-2 mb-4">


        <!-- ページネーション用 -->
        <div class="mt-6 flex items-center justify-between border-t border-gray-200 bg-white px-4 py-0 sm:px-6 rounded-lg shadow-sm">
            <!-- パソコン用・全画面用の表示（現在の件数情報とボタン） -->
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        全 <span class="font-medium">{{ $records->total() }}</span> 件中
                        <span class="font-medium">{{ $records->firstItem() }}</span> から
                        <span class="font-medium">{{ $records->lastItem() }}</span> 件目を表示
                    </p>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        <!-- 「前へ」ボタン -->
                        @if ($records->onFirstPage())
                            <span class="relative inline-flex items-center rounded-l-md border border-gray-300 bg-gray-100 border border-gray-300 px-3 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">
                                &laquo; 前へ
                            </span>
                        @else
                            <a href="{{ $records->previousPageUrl() }}" class="relative inline-flex items-center rounded-l-md border border-gray-300 bg-white border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                &laquo; 前へ
                            </a>
                        @endif

                        <!-- 現在のページ数表示 -->
                        <span class="relative inline-flex items-center border border-gray-300 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-600">
                            {{ $records->currentPage() }} / {{ $records->lastPage() }} ページ
                        </span>

                        <!-- 「次へ」ボタン -->
                        @if ($records->hasMorePages())
                            <a href="{{ $records->nextPageUrl() }}" class="relative inline-flex items-center rounded-r-md border border-gray-300 bg-white border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                次へ &raquo;
                            </a>
                        @endif
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- 詳細ボタン（リンク） -->
        <div id="detail-btn">
            <span>詳細</span>
        </div>
        <!-- ホームに戻るボタン（リンク） -->
        <a href="{{ url('/home') }}"
           class="close-to-home-btn"
           aria-label="閉じる"
           title="閉じる"
           style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:1px solid #94a3b8;border-radius:6px;background:#fff;color:#0f172a;text-decoration:none;font-size:22px;font-weight:700;line-height:1;">
            ×
        </a>

    </div>


    <div class="bg-white rounded-lg shadow overflow-hidden">

    <!-- 横スクロール  テーブルのコンテナ-->
    <div class="overflow-x-auto max-w-full max-h-[90vh] overflow-y-auto ">
        
        <!-- テーブル -->
        <table class="min-w-full border border-gray-300 text-sm">

            <!-- ヘッダ -->
            <thead class="bg-blue-600 text-white sticky top-0 z-20">
                    <tr>
                        <!-- orderIDのみスクロール時に左端に固定 -->
                        <th scope="col" class="sticky left-0 bg-blue-300 px-4 py-0 text-left text-xs font-bold text-gray-700 uppercase tracking-wider shadow-[2px_0_5px_rgba(0,0,0,0.05)] z-10">ID</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >receivedDate</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >RMA</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >productName</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>productType</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >SN</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >status</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >returnCode</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >a2la</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" style="width:400px;">dealer</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >dealer_depart</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >contactPerson</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>email</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>phone</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>zipcode</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>address1</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>address2</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >endUser</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >endUser_depart</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>endUser_contactPerson</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>endUser_email</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>endUser_phone</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>endUser_zipcode</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>endUser_address1</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>endUser_address2</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >deliveryDestination_company</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >laborID</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>quoteDate</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>quoteNum</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>poNum</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>orderDate</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>orderNum</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>invNum</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >price</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>rmaNumOverSea</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>shippedDate</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>shipTo</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>sentOut</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >sm_workorder</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >sm_quote</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >coNum</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >mapics_inv</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >mapics47</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >discount_service</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >shippingOut_requiredDate</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider" hidden>loaner_no_charge</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >lastEditPerson</th>
                        <th scope="col" class="border border-gray-300 px-3 py-0 text-left text-xs font-medium tracking-wider"       >lastEditDate</th>
                    </tr>
            </thead>
            
            <!-- テーブルのボディ（データ行） -->
            <tbody class="bg-gray-200 divide-y divide-gray-200">
               @forelse($records as $record)
                        <tr class="row transition-colors" data-order-id="{{$record->orderID}}">
                            <!-- orderID列の固定設定（背景色を白にして文字の重なりを防止） -->
                            <td class="sticky left-0 px-4 py-0  text-sm shadow-[2px_0_5px_rgba(0,0,0,0.05)]">
                                {{ $record->orderID }}
                            </td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->receivedDate }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->RMA }}</td>
                            <td class="border border-gray-300 px-3 py-0 truncate max-w-[200px]"   >{{ $record->productName }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->productType }}</td>
                            <td class="border border-gray-300 px-3 py-0 truncate max-w-[200px]">{{ $record->SN }}</td>
                            <td class="border border-gray-300 px-3 py-0 ">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 truncate max-w-[200px]">
                                    {{ $record->statusMaster->status ?? '' }}
                                </span>
                            </td>
                            <td class="border border-gray-300 px-3 py-0  truncate max-w-[200px]"   >{{ $record->returnCodeMaster->description ?? '' }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->a2la }}</td>
                            <td class="border border-gray-300 px-3 py-0  truncate max-w-[200px]"   >{{ $record->dealer }}</td>
                            <td class="border border-gray-300 px-3 py-0  truncate max-w-[200px]"   >{{ $record->dealer_depart }}</td>
                            <td class="border border-gray-300 px-3 py-0  truncate max-w-[200px]"   >{{ $record->contactPerson }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->email }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->phone }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->zipcode }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->address1 }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->address2 }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->deliverToEndUser }}</td>
                            <td class="border border-gray-300 px-3 py-0  truncate max-w-[200px]"   >{{ $record->endUser }}</td>
                            <td class="border border-gray-300 px-3 py-0  truncate max-w-[200px]"   >{{ $record->endUser_depart }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->endUser_contactPerson }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->endUser_email }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->endUser_phone }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->endUser_zipcode }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->endUser_address1 }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->endUser_address2 }}</td>
                            <td class="border border-gray-300 px-3 py-0  truncate max-w-[200px]"   >{{ $record->deliveryDestination_company }}</td>
                            <td class="border border-gray-300 px-3 py-0 ">{{ $record->laborMaster?->laborName }}</td>

                            {{-- <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->laborMaster->laborName}}</td> --}}
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->quoteDate }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->quoteNum }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->poNum }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->orderDate }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->orderNum }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->invNum }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->price }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->rmaNumOverSea }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->shippedDate }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->shipTo }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->sentOut }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->sm_workorder }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->sm_quote }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->coNum }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->mapics_inv }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->mapics47 }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->discount_service }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->shippingOut_requiredDate }}</td>
                            <td class="border border-gray-300 px-3 py-0 " hidden                   >{{ $record->loaner_no_charge }}</td>
                            <td class="border border-gray-300 px-3 py-0 "                          >{{ $record->lastEditPerson }}</td>
                            <td class="border border-gray-300 px-3 py-0  truncate max-w-[200px]"   >{{ $record->lastEditDate }}</td>
                        </tr>
                    @empty
                        <tr>
                            <!-- 65列分を結合してメッセージを表示 -->
                            <td colspan="65" class="px-6 py-00 text-center text-sm">
                                データがありません。
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

{{-- ↓↓↓ モーダル / ここに置く（ページの下）↓↓↓ --}}
<div id="modal-form" class="fixed inset-0 bg-white hidden z-50 overflow-auto">
    {{-- header --}}
    <div class="page-header h-[7v]">
        <!-- <div class="page-title">Title</div> -->

        <!-- ボタン -->
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" id="closeModal-btn"
                class="px-4 py-2 bg-gray-500 text-white rounded">
                閉じる
            </button>

            <button type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded">
                保存
            </button>
        </div>
    </div>


    {{-- Vue --}}
    <div id="app">
        <service-record-form
            :record='@json($record ?? null)'
            :statuses='@json($statuses)'
            :return-codes='@json($returnCodes)'
            :labors='@json($labors)'
            mode="{{ $mode }}"
        ></service-record-form>
    </div>

</div>
@endsection

<script>
document.getElementById('tableSearch').addEventListener('input', function() {
    const filterText = this.value.toLowerCase();
    const rows = document.querySelectorAll('#recordTableBody tr');

    rows.forEach(row => {
        // 「作業名」が入力文字を含んでいるかチェック
        const targetText = row.querySelector('.search-target').textContent.toLowerCase();
        
        if (targetText.includes(filterText)) {
            row.style.display = ''; // 表示
        } else {
            row.style.display = 'none'; // 非表示
        }
    });
});
</script>
