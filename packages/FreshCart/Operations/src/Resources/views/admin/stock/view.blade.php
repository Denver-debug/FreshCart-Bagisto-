<x-admin::layouts>
    <x-slot:title>
        {{ $product->sku }}
    </x-slot>

    <x-operations::header :title="$product->sku.' — '.$product->name" :back="route('admin.operations.stock.index')" />

    <div class="mt-3.5 grid gap-2.5 xl:grid-cols-2">
        <x-operations::card :title="trans('operations::app.stock.sellable')">
            <table class="w-full text-left text-sm" v-pre>
                <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-2 py-2">@lang('operations::app.common.location')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.stock.sellable')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.stock.transferable')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inventories as $inventory)
                        <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                            <td class="px-2 py-2">{{ $inventory->name }}</td>
                            <td class="px-2 py-2 text-right">{{ $inventory->qty }}</td>
                            <td class="px-2 py-2 text-right">{{ $inventory->transferable }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-2 py-4 text-center text-gray-500">@lang('operations::app.common.empty')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-operations::card>

        <x-operations::card :title="trans('operations::app.stock.holds')">
            <table class="w-full text-left text-sm" v-pre>
                <tbody>
                    @forelse ($holds as $hold)
                        <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                            <td class="px-2 py-2">{{ $hold->location?->name }}</td>
                            <td class="px-2 py-2">{{ trans('operations::app.buckets.'.$hold->bucket) }}</td>
                            <td class="px-2 py-2">{{ $hold->lot?->batch_no }}</td>
                            <td class="px-2 py-2 text-right">{{ $hold->qty }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-2 py-4 text-center text-gray-500">@lang('operations::app.stock.no-holds')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-operations::card>

        <x-operations::card :title="trans('operations::app.stock.lots')" class="xl:col-span-2">
            <table class="w-full text-left text-sm" v-pre>
                <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-2 py-2">@lang('operations::app.common.location')</th>
                        <th class="px-2 py-2">@lang('operations::app.common.batch')</th>
                        <th class="px-2 py-2">@lang('operations::app.common.expiry')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.common.qty')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.common.unit-cost')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lots as $lot)
                        <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                            <td class="px-2 py-2">{{ $lot->location?->name }}</td>
                            <td class="px-2 py-2">{{ $lot->batch_no ?? '—' }}</td>
                            <td class="px-2 py-2 {{ $lot->expiry_date?->isPast() ? 'text-red-600' : '' }}">{{ $lot->expiry_date?->toDateString() ?? '—' }}</td>
                            <td class="px-2 py-2 text-right">{{ $lot->qty_on_hand }}</td>
                            <td class="px-2 py-2 text-right">{{ $lot->unit_cost !== null ? core()->formatBasePrice((float) $lot->unit_cost) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-4 text-center text-gray-500">@lang('operations::app.stock.no-lots')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-operations::card>

        <x-operations::card :title="trans('operations::app.stock.movements')" class="xl:col-span-2">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" v-pre>
                    <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        <tr>
                            <th class="px-2 py-2">@lang('operations::app.stock.when')</th>
                            <th class="px-2 py-2">@lang('operations::app.common.location')</th>
                            <th class="px-2 py-2">@lang('operations::app.stock.movement-type')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.common.qty')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.stock.change')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.stock.balance')</th>
                            <th class="px-2 py-2">@lang('operations::app.common.reference')</th>
                            <th class="px-2 py-2">@lang('operations::app.stock.actor')</th>
                            <th class="px-2 py-2">@lang('operations::app.common.reason')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                <td class="whitespace-nowrap px-2 py-2">{{ $movement->created_at?->format('d M Y H:i') }}</td>
                                <td class="px-2 py-2">{{ $movement->location?->name }}</td>
                                <td class="px-2 py-2">
                                    {{ trans('operations::app.stock.movement-types.'.$movement->movement_type) }}
                                    @if ($movement->bucket_from || $movement->bucket_to)
                                        <span class="block text-xs text-gray-500">{{ $movement->bucket_from ? trans('operations::app.buckets.'.$movement->bucket_from) : trans('operations::app.buckets.sellable') }} → {{ $movement->bucket_to ? trans('operations::app.buckets.'.$movement->bucket_to) : ($movement->sellable_delta > 0 ? trans('operations::app.buckets.sellable') : '—') }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right">{{ $movement->qty }}</td>
                                <td class="px-2 py-2 text-right {{ $movement->sellable_delta < 0 ? 'text-red-600' : ($movement->sellable_delta > 0 ? 'text-green-600' : '') }}">{{ $movement->sellable_delta > 0 ? '+' : '' }}{{ $movement->sellable_delta }}</td>
                                <td class="px-2 py-2 text-right">{{ $movement->sellable_after }}</td>
                                <td class="px-2 py-2">{{ $movement->reference_no }}</td>
                                <td class="px-2 py-2">{{ $movement->admin?->name ?? trans('operations::app.common.system') }}</td>
                                <td class="px-2 py-2">{{ $movement->reason }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-2 py-4 text-center text-gray-500">@lang('operations::app.common.empty')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $movements->links() }}</div>
        </x-operations::card>
    </div>
</x-admin::layouts>
