<x-admin::layouts>
    <x-slot:title>
        {{ $purchaseOrder->reference }}
    </x-slot>

    <x-operations::header
        :title="$purchaseOrder->reference"
        :status="$purchaseOrder->status"
        :back="route('admin.operations.purchase_orders.index')"
    >
        <a href="{{ route('admin.operations.purchase_orders.print', $purchaseOrder->id) }}" class="secondary-button">
            @lang('operations::app.common.print-pdf')
        </a>

        @if (bouncer()->hasPermission('operations.purchase_orders.create'))
            @if ($purchaseOrder->status === 'draft')
                <x-operations::action
                    :action="route('admin.operations.purchase_orders.issue', $purchaseOrder->id)"
                    :label="trans('operations::app.purchase-orders.issue')"
                    :confirm="trans('operations::app.purchase-orders.confirm-issue')"
                    variant="primary"
                />
            @endif

            @if (in_array($purchaseOrder->status, ['issued', 'partially_received']))
                <x-operations::action
                    :action="route('admin.operations.purchase_orders.close', $purchaseOrder->id)"
                    :label="trans('operations::app.purchase-orders.close')"
                    :confirm="trans('operations::app.purchase-orders.confirm-close')"
                />
            @endif

            @if (in_array($purchaseOrder->status, ['draft', 'issued']) && $purchaseOrder->receipts->isEmpty())
                <x-operations::action
                    :action="route('admin.operations.purchase_orders.cancel', $purchaseOrder->id)"
                    :label="trans('operations::app.common.cancel')"
                    :confirm="trans('operations::app.common.confirm-cancel')"
                />
            @endif
        @endif

        @if ($purchaseOrder->isReceivable() && bouncer()->hasPermission('operations.receiving.create'))
            <a href="{{ route('admin.operations.receiving.create', $purchaseOrder->id) }}" class="primary-button">
                @lang('operations::app.receiving.receive-goods')
            </a>
        @endif
    </x-operations::header>

    <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
        <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
            <x-operations::card :title="trans('operations::app.common.items')">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" v-pre>
                        <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                                <th class="px-2 py-2">@lang('operations::app.common.product')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.ordered')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.received')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.rejected')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.outstanding')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.unit-cost')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.total')</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($purchaseOrder->items as $item)
                                <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                    <td class="px-2 py-2 font-semibold">{{ $item->sku }}</td>
                                    <td class="px-2 py-2">{{ $item->name }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_ordered }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_received }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_rejected }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->outstandingQty() }}</td>
                                    <td class="px-2 py-2 text-right">{{ core()->formatBasePrice((float) $item->unit_cost) }}</td>
                                    <td class="px-2 py-2 text-right">{{ core()->formatBasePrice((float) $item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr>
                                <td colspan="7" class="px-2 py-2 text-right font-semibold dark:text-white">@lang('operations::app.common.total')</td>
                                <td class="px-2 py-2 text-right font-semibold dark:text-white">{{ core()->formatBasePrice((float) $purchaseOrder->subtotal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-operations::card>

            <x-operations::card :title="trans('operations::app.receiving.title')">
                @forelse ($purchaseOrder->receipts as $receipt)
                    <p class="text-sm">
                        <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.receiving.view', $receipt->id) }}">{{ $receipt->reference }}</a>
                        <span class="text-gray-500" v-pre>· {{ core()->formatDate($receipt->received_at, 'd M Y H:i') }} · {{ $receipt->receiver?->name }}</span>

                        @if ($receipt->has_discrepancy)
                            <span class="label-canceled">@lang('operations::app.receiving.discrepancy')</span>
                        @endif
                    </p>
                @empty
                    <p class="text-sm text-gray-500">@lang('operations::app.receiving.none-yet')</p>
                @endforelse
            </x-operations::card>
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.supplier')</dt><dd>{{ $purchaseOrder->supplier?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.purchase-orders.deliver-to')</dt><dd>{{ $purchaseOrder->location?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.purchase-orders.order-date')</dt><dd>{{ $purchaseOrder->order_date?->toDateString() }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.purchase-orders.expected-delivery')</dt><dd>{{ $purchaseOrder->expected_delivery_date?->toDateString() ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.created-by')</dt><dd>{{ $purchaseOrder->creator?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.notes')</dt><dd class="whitespace-pre-line">{{ $purchaseOrder->notes ?: '—' }}</dd></div>
                </dl>
            </x-operations::card>

            <x-operations::history :document="$purchaseOrder" />
        </div>
    </div>
</x-admin::layouts>
