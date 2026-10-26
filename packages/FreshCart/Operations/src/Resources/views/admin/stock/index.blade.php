<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.stock.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.stock.title')" />

    <form method="GET" class="mt-3 flex flex-wrap items-end gap-2">
        <x-operations::field class="!mb-0" name="term" :label="trans('operations::app.line-items.scan')" :value="$filters['term'] ?? ''" />
        <x-operations::field class="!mb-0" name="location_id" type="select" :label="trans('operations::app.common.location')" :options="$locationOptions" :value="$filters['location_id'] ?? ''" :placeholder="trans('operations::app.common.all')" />

        <label class="mb-2 flex items-center gap-2 text-sm dark:text-gray-300">
            <input type="checkbox" name="low_only" value="1" @checked(! empty($filters['low_only']))>
            @lang('operations::app.stock.low-only')
        </label>

        <button type="submit" class="secondary-button">@lang('operations::app.common.filter')</button>
    </form>

    <x-operations::card class="mt-3">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" v-pre>
                <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                        <th class="px-2 py-2">@lang('operations::app.common.product')</th>
                        <th class="px-2 py-2">@lang('operations::app.common.location')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.stock.sellable')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.buckets.quarantine')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.buckets.damaged')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.buckets.expired')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.buckets.supplier_return')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.buckets.in_transit')</th>
                        <th class="px-2 py-2 text-right">@lang('operations::app.stock.reorder-level')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($rows as $row)
                        @php($reorder = $row->reorder_level ?? $defaultReorder)
                        <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                            <td class="px-2 py-2 font-semibold"><a class="text-blue-600 hover:underline" href="{{ route('admin.operations.stock.view', $row->product_id) }}">{{ $row->sku }}</a></td>
                            <td class="px-2 py-2">{{ $row->name }}</td>
                            <td class="px-2 py-2">{{ $row->location_name }}</td>
                            <td class="px-2 py-2 text-right {{ $reorder > 0 && $row->qty <= $reorder ? 'font-semibold text-red-600' : '' }}">{{ $row->qty }}</td>
                            <td class="px-2 py-2 text-right">{{ $row->quarantine_qty ?: '' }}</td>
                            <td class="px-2 py-2 text-right">{{ $row->damaged_qty ?: '' }}</td>
                            <td class="px-2 py-2 text-right">{{ $row->expired_qty ?: '' }}</td>
                            <td class="px-2 py-2 text-right">{{ $row->supplier_return_qty ?: '' }}</td>
                            <td class="px-2 py-2 text-right">{{ $row->in_transit_qty ?: '' }}</td>
                            <td class="px-2 py-2 text-right">{{ $reorder ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-2 py-6 text-center text-gray-500">@lang('operations::app.common.empty')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $rows->links() }}</div>
    </x-operations::card>
</x-admin::layouts>
