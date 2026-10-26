<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.reports.names.'.$key)
    </x-slot>

    @php($formatter = app(\FreshCart\Operations\Http\Controllers\Admin\ReportController::class))

    <x-operations::header :title="trans('operations::app.reports.names.'.$key)" :back="route('admin.operations.reports.index')">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="secondary-button">@lang('operations::app.common.export-csv')</a>
    </x-operations::header>

    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.reports.descriptions.'.$key)</p>

    @if ($note)
        <p class="mt-1 text-xs text-gray-500">{{ $note }}</p>
    @endif

    <form method="GET" class="mt-3 flex flex-wrap items-end gap-2">
        @if (in_array('date', $filterFields))
            <x-operations::field class="!mb-0" name="date_from" type="date" :label="trans('operations::app.common.date-from')" :value="$filters['date_from'] ?? ''" />
            <x-operations::field class="!mb-0" name="date_to" type="date" :label="trans('operations::app.common.date-to')" :value="$filters['date_to'] ?? ''" />
        @endif

        @if (in_array('location', $filterFields))
            <x-operations::field class="!mb-0" name="location_id" type="select" :label="trans('operations::app.common.location')" :options="$locationOptions" :value="$filters['location_id'] ?? ''" :placeholder="trans('operations::app.common.all')" />
        @endif

        @if (in_array('supplier', $filterFields))
            <x-operations::field class="!mb-0" name="supplier_id" type="select" :label="trans('operations::app.common.supplier')" :options="$supplierOptions" :value="$filters['supplier_id'] ?? ''" :placeholder="trans('operations::app.common.all')" />
        @endif

        @if (in_array('category', $filterFields))
            <x-operations::field class="!mb-0" name="category_id" type="select" :label="trans('operations::app.reports.category')" :options="$categoryOptions" :value="$filters['category_id'] ?? ''" :placeholder="trans('operations::app.common.all')" />
        @endif

        @if (in_array('sku', $filterFields))
            <x-operations::field class="!mb-0" name="sku" :label="trans('operations::app.common.sku')" :value="$filters['sku'] ?? ''" />
        @endif

        @if (in_array('reference', $filterFields))
            <x-operations::field class="!mb-0" name="reference" :label="trans('operations::app.common.reference')" :value="$filters['reference'] ?? ''" />
        @endif

        @if (in_array('type', $filterFields))
            <x-operations::field class="!mb-0" name="type" type="select" :label="trans('operations::app.stock.movement-type')" :options="trans('operations::app.stock.movement-types')" :value="$filters['type'] ?? ''" :placeholder="trans('operations::app.common.all')" />
        @endif

        @if (in_array('transfer_status', $filterFields))
            <x-operations::field class="!mb-0" name="status" type="select" :label="trans('operations::app.common.status')" :options="collect(['approved', 'picking', 'in_transit', 'partially_received', 'received', 'closed_with_discrepancy'])->mapWithKeys(fn ($s) => [$s => trans('operations::app.statuses.'.$s)])->all()" :value="$filters['status'] ?? ''" :placeholder="trans('operations::app.common.all')" />
        @endif

        @if (in_array('adjustment_reason', $filterFields))
            <x-operations::field class="!mb-0" name="reason" type="select" :label="trans('operations::app.common.reason')" :options="trans('operations::app.adjustments.reasons')" :value="$filters['reason'] ?? ''" :placeholder="trans('operations::app.common.all')" />
        @endif

        @if (in_array('incident_type', $filterFields))
            <x-operations::field class="!mb-0" name="type" type="select" :label="trans('operations::app.incidents.type')" :options="trans('operations::app.incidents.types')" :value="$filters['type'] ?? ''" :placeholder="trans('operations::app.common.all')" />
        @endif

        @if (in_array('days', $filterFields))
            <x-operations::field class="!mb-0" name="days" type="number" min="0" :label="trans('operations::app.reports.days')" :value="$filters['days'] ?? ''" />
        @endif

        <button type="submit" class="secondary-button">@lang('operations::app.common.filter')</button>
    </form>

    @if ($summary)
        <div class="mt-3 grid gap-2 sm:grid-cols-3">
            @foreach ($summary as $metric => $value)
                <x-operations::card>
                    <p class="text-xs text-gray-500">@lang('operations::app.reports.summary.'.$metric)</p>
                    <p class="text-lg font-semibold dark:text-white">{{ $metric === 'revenue' ? core()->formatBasePrice($value) : $value }}</p>
                </x-operations::card>
            @endforeach
        </div>
    @endif

    <x-operations::card class="mt-3">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" v-pre>
                <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <tr>
                        @foreach ($columns as $column => $type)
                            <th class="whitespace-nowrap px-2 py-2 {{ in_array($type, ['int', 'money', 'percent']) ? 'text-right' : '' }}">@lang('operations::app.reports.columns.'.$column)</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                            @foreach ($columns as $column => $type)
                                <td class="px-2 py-2 {{ in_array($type, ['int', 'money', 'percent']) ? 'text-right' : '' }}">
                                    @if ($type === 'money' && ($row->{$column} ?? null) !== null)
                                        {{ core()->formatBasePrice((float) $row->{$column}) }}
                                    @else
                                        {{ $formatter->plain($row->{$column} ?? null, $type) ?: '—' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-2 py-6 text-center text-gray-500">@lang('operations::app.reports.empty')</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="mt-3">{{ $rows->links() }}</div>
        @endif
    </x-operations::card>
</x-admin::layouts>
