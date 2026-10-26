<x-admin::layouts>
    <x-slot:title>
        {{ $purchaseRequest->reference }}
    </x-slot>

    @php($isOwner = $purchaseRequest->requested_by === auth()->guard('admin')->id())

    <x-operations::header
        :title="$purchaseRequest->reference"
        :status="$purchaseRequest->status"
        :back="route('admin.operations.purchase_requests.index')"
    >
        @if ($purchaseRequest->isEditable() && $isOwner)
            <a href="{{ route('admin.operations.purchase_requests.edit', $purchaseRequest->id) }}" class="secondary-button">
                @lang('operations::app.common.edit')
            </a>

            <x-operations::action
                :action="route('admin.operations.purchase_requests.submit', $purchaseRequest->id)"
                :label="trans('operations::app.common.submit')"
                :confirm="trans('operations::app.common.confirm-submit')"
                variant="primary"
            />
        @endif

        @if (in_array($purchaseRequest->status, ['draft', 'submitted']) && ($isOwner || bouncer()->hasPermission('operations.purchase_requests.approve')))
            <x-operations::action
                :action="route('admin.operations.purchase_requests.cancel', $purchaseRequest->id)"
                :label="trans('operations::app.common.cancel')"
                :confirm="trans('operations::app.common.confirm-cancel')"
            />
        @endif

        @if ($purchaseRequest->status === 'approved' && bouncer()->hasPermission('operations.purchase_orders.create'))
            <a
                href="{{ route('admin.operations.purchase_orders.create', ['request_ids' => [$purchaseRequest->id]]) }}"
                class="primary-button"
            >
                @lang('operations::app.purchase-requests.create-po')
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
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.qty')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.purchase-requests.estimated-unit-cost')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.ordered')</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($purchaseRequest->items as $item)
                                <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                    <td class="px-2 py-2 font-semibold">{{ $item->sku }}</td>
                                    <td class="px-2 py-2">{{ $item->name }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->estimated_unit_cost !== null ? core()->formatBasePrice((float) $item->estimated_unit_cost) : '—' }}</td>
                                    <td class="px-2 py-2 text-right">
                                        {{ $orderLines->where('purchase_request_item_id', $item->id)->filter(fn ($line) => $line->purchaseOrder->status !== 'cancelled')->sum('qty_ordered') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr>
                                <td colspan="3" class="px-2 py-2 text-right font-semibold dark:text-white">@lang('operations::app.purchase-requests.estimated-total')</td>
                                <td class="px-2 py-2 text-right font-semibold dark:text-white">{{ core()->formatBasePrice((float) $purchaseRequest->estimated_total) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-operations::card>

            @if ($orderLines->isNotEmpty())
                <x-operations::card :title="trans('operations::app.purchase-requests.linked-orders')">
                    <ul class="grid gap-1 text-sm">
                        @foreach ($orderLines->pluck('purchaseOrder')->unique('id') as $order)
                            <li>
                                <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.purchase_orders.view', $order->id) }}">{{ $order->reference }}</a>
                                <x-operations::status :status="$order->status" />
                            </li>
                        @endforeach
                    </ul>
                </x-operations::card>
            @endif
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.purchase-requests.requesting-location')</dt><dd>{{ $purchaseRequest->location?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.requested-by')</dt><dd>{{ $purchaseRequest->requester?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.priority')</dt><dd>{{ trans('operations::app.priorities.'.$purchaseRequest->priority) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.needed-by')</dt><dd>{{ $purchaseRequest->needed_by?->toDateString() ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.approvals')</dt><dd>{{ $purchaseRequest->approvals_received }} / {{ $purchaseRequest->approval_level_required }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.purchase-requests.justification')</dt><dd class="whitespace-pre-line">{{ $purchaseRequest->justification }}</dd></div>
                </dl>
            </x-operations::card>

            <x-operations::decision
                :document="$purchaseRequest"
                approve-route="admin.operations.purchase_requests.approve"
                reject-route="admin.operations.purchase_requests.reject"
            />

            <x-operations::history :document="$purchaseRequest" />
        </div>
    </div>
</x-admin::layouts>
