<x-admin::layouts>
    <x-slot:title>
        {{ $receipt->reference }}
    </x-slot>

    <x-operations::header :title="$receipt->reference" :back="route('admin.operations.receiving.index')">
        @if ($receipt->has_discrepancy)
            <span class="label-canceled">@lang('operations::app.receiving.discrepancy')</span>
        @endif

        <a href="{{ route('admin.operations.purchase_orders.view', $receipt->purchase_order_id) }}" class="secondary-button">
            {{ $receipt->purchaseOrder->reference }}
        </a>
    </x-operations::header>

    <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
        <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
            <x-operations::card :title="trans('operations::app.common.items')">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" v-pre>
                        <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.ordered')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.receiving.qty-received')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.receiving.qty-accepted')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.receiving.qty-rejected')</th>
                                <th class="px-2 py-2">@lang('operations::app.common.batch')</th>
                                <th class="px-2 py-2">@lang('operations::app.common.expiry')</th>
                                <th class="px-2 py-2">@lang('operations::app.receiving.inspection')</th>
                                <th class="px-2 py-2">@lang('operations::app.receiving.discrepancy-note')</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($receipt->items as $item)
                                <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                    <td class="px-2 py-2 font-semibold">{{ $item->sku }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->purchaseOrderItem?->qty_ordered }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_received }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_accepted }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_rejected }}</td>
                                    <td class="px-2 py-2">{{ $item->batch_no ?? '—' }}</td>
                                    <td class="px-2 py-2">{{ $item->expiry_date?->toDateString() ?? '—' }}</td>
                                    <td class="px-2 py-2">{{ trans('operations::app.receiving.inspection-'.$item->inspection_result) }}</td>
                                    <td class="px-2 py-2">
                                        {{ $item->discrepancy_note ?? '' }}

                                        @if ($item->over_receipt_reason)
                                            <span class="block text-xs text-red-600">@lang('operations::app.receiving.over-receipt-reason'): {{ $item->over_receipt_reason }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-operations::card>
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.supplier')</dt><dd>{{ $receipt->purchaseOrder->supplier?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.location')</dt><dd>{{ $receipt->location?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.receiving.received-by')</dt><dd>{{ $receipt->receiver?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.receiving.received-at')</dt><dd>{{ core()->formatDate($receipt->received_at, 'd M Y H:i') }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.receiving.delivery-note')</dt><dd>{{ $receipt->delivery_note_no ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.receiving.inspection-remarks')</dt><dd class="whitespace-pre-line">{{ $receipt->remarks ?: '—' }}</dd></div>
                </dl>
            </x-operations::card>

            <x-operations::history :document="$receipt" />
        </div>
    </div>
</x-admin::layouts>
