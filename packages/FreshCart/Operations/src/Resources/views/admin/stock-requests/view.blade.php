<x-admin::layouts>
    <x-slot:title>
        {{ $stockRequest->reference }}
    </x-slot>

    @php($isOwner = $stockRequest->requested_by === auth()->guard('admin')->id())

    <x-operations::header :title="$stockRequest->reference" :status="$stockRequest->status" :back="route('admin.operations.stock_requests.index')">
        @if ($stockRequest->status === 'draft' && $isOwner)
            <a href="{{ route('admin.operations.stock_requests.edit', $stockRequest->id) }}" class="secondary-button">@lang('operations::app.common.edit')</a>

            <x-operations::action
                :action="route('admin.operations.stock_requests.submit', $stockRequest->id)"
                :label="trans('operations::app.common.submit')"
                :confirm="trans('operations::app.common.confirm-submit')"
                variant="primary"
            />
        @endif

        @if (in_array($stockRequest->status, ['draft', 'submitted']) && $isOwner)
            <x-operations::action
                :action="route('admin.operations.stock_requests.cancel', $stockRequest->id)"
                :label="trans('operations::app.common.cancel')"
                :confirm="trans('operations::app.common.confirm-cancel')"
            />
        @endif

        @if (in_array($stockRequest->status, \FreshCart\Operations\Models\StockRequest::ALLOCATABLE_STATUSES) && bouncer()->hasPermission('operations.stock_requests.allocate'))
            <a href="{{ route('admin.operations.stock_requests.allocation', $stockRequest->id) }}" class="primary-button">@lang('operations::app.stock-requests.allocate')</a>
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
                                <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.requested')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.allocated-qty')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.dispatched')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.received')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.backorder')</th>
                                <th class="px-2 py-2">@lang('operations::app.stock-requests.override-reason')</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($stockRequest->items as $item)
                                <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                    <td class="px-2 py-2 font-semibold">{{ $item->sku }}</td>
                                    <td class="px-2 py-2">{{ $item->name }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_requested }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_allocated }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_dispatched }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_received }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->outstandingQty() }}</td>
                                    <td class="px-2 py-2">{{ $item->override_reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-operations::card>

            <x-operations::card :title="trans('operations::app.transfers.title')">
                @forelse ($stockRequest->transfers as $transfer)
                    <p class="text-sm">
                        <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.transfers.view', $transfer->id) }}">{{ $transfer->reference }}</a>
                        <x-operations::status :status="$transfer->status" />
                    </p>
                @empty
                    <p class="text-sm text-gray-500">@lang('operations::app.stock-requests.no-transfers')</p>
                @endforelse
            </x-operations::card>
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.stock-requests.branch')</dt><dd>{{ $stockRequest->location?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.stock-requests.warehouse')</dt><dd>{{ $stockRequest->sourceLocation?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.requested-by')</dt><dd>{{ $stockRequest->requester?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.priority')</dt><dd>{{ trans('operations::app.priorities.'.$stockRequest->priority) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.stock-requests.required-delivery')</dt><dd>{{ $stockRequest->needed_by?->toDateString() ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.notes')</dt><dd class="whitespace-pre-line">{{ $stockRequest->notes ?: '—' }}</dd></div>
                </dl>
            </x-operations::card>

            <x-operations::history :document="$stockRequest" />
        </div>
    </div>
</x-admin::layouts>
