<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.dashboard.title')
    </x-slot>

    @php($money = fn ($value) => core()->formatBasePrice((float) $value))

    <x-operations::header :title="trans('operations::app.dashboard.title')">
        <a href="{{ route('admin.operations.approvals.index') }}" class="primary-button">
            @lang('operations::app.dashboard.pending-my-approval', ['count' => $pending_my_approval])
        </a>
    </x-operations::header>

    <form method="GET" class="mt-3 flex flex-wrap items-end gap-2">
        <x-operations::field class="!mb-0" name="date_from" type="date" :label="trans('operations::app.common.date-from')" :value="$filters['date_from'] ?? ''" />
        <x-operations::field class="!mb-0" name="date_to" type="date" :label="trans('operations::app.common.date-to')" :value="$filters['date_to'] ?? ''" />
        <button type="submit" class="secondary-button">@lang('operations::app.common.filter')</button>
        <span class="text-xs text-gray-500">@lang('operations::app.dashboard.range-help')</span>
    </form>

    @if (empty($sections))
        <x-operations::card class="mt-3.5">
            <p class="text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.dashboard.no-sections')</p>
        </x-operations::card>
    @endif

    {{-- Management --}}
    @isset($sections['management'])
        @php($m = $sections['management'])

        <p class="mb-2 mt-5 text-base font-semibold text-gray-800 dark:text-white">@lang('operations::app.dashboard.management')</p>

        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            <x-operations::card>
                <p class="text-xs text-gray-500">@lang('operations::app.dashboard.inventory-value')</p>
                <p class="text-xl font-semibold dark:text-white">{{ $money($m['inventory_value']) }}</p>
                @if ($m['unvalued_products'])
                    <p class="text-xs text-gray-500">@lang('operations::app.dashboard.unvalued', ['count' => $m['unvalued_products']])</p>
                @endif
            </x-operations::card>

            <x-operations::card>
                <p class="text-xs text-gray-500">@lang('operations::app.dashboard.low-stock')</p>
                <p class="text-xl font-semibold dark:text-white"><a class="hover:underline" href="{{ route('admin.operations.reports.low_stock') }}">{{ $m['low_stock'] }}</a></p>
                <p class="text-xs text-red-600">@lang('operations::app.dashboard.out-of-stock', ['count' => $m['out_of_stock']])</p>
            </x-operations::card>

            <x-operations::card>
                <p class="text-xs text-gray-500">@lang('operations::app.dashboard.purchasing')</p>
                <p class="text-sm dark:text-gray-300">@lang('operations::app.dashboard.pending-pr', ['count' => $m['pending_purchase_approvals']])</p>
                <p class="text-sm dark:text-gray-300">@lang('operations::app.dashboard.open-po', ['count' => $m['outstanding_orders']])</p>
                <p class="text-sm {{ $m['overdue_deliveries'] ? 'text-red-600' : 'dark:text-gray-300' }}">@lang('operations::app.dashboard.overdue-po', ['count' => $m['overdue_deliveries']])</p>
            </x-operations::card>

            <x-operations::card>
                <p class="text-xs text-gray-500">@lang('operations::app.dashboard.distribution')</p>
                <p class="text-sm dark:text-gray-300">@lang('operations::app.dashboard.pending-sr', ['count' => $m['pending_stock_requests']])</p>
                <p class="text-sm dark:text-gray-300">@lang('operations::app.dashboard.in-transit', ['count' => $m['transfers_in_transit']])</p>
                <p class="text-sm {{ $m['transfers_overdue'] ? 'text-red-600' : 'dark:text-gray-300' }}">@lang('operations::app.dashboard.overdue-transfers', ['count' => $m['transfers_overdue']])</p>
            </x-operations::card>

            <x-operations::card>
                <p class="text-xs text-gray-500">@lang('operations::app.dashboard.held-stock')</p>
                @forelse ($m['held'] as $bucket => $qty)
                    <p class="text-sm dark:text-gray-300">@lang('operations::app.buckets.'.$bucket): {{ $qty }}</p>
                @empty
                    <p class="text-sm text-gray-500">@lang('operations::app.dashboard.none')</p>
                @endforelse
            </x-operations::card>

            <x-operations::card>
                <p class="text-xs text-gray-500">@lang('operations::app.dashboard.promotions')</p>
                @foreach (['active', 'scheduled', 'expired'] as $status)
                    <p class="text-sm dark:text-gray-300">@lang('operations::app.statuses.'.$status): {{ $m['promotions'][$status] ?? 0 }}</p>
                @endforeach
            </x-operations::card>

            <x-operations::card>
                <p class="text-xs text-gray-500">@lang('operations::app.dashboard.disposals-awaiting')</p>
                <p class="text-xl font-semibold dark:text-white">{{ $m['disposals_awaiting_approval'] }}</p>
            </x-operations::card>
        </div>

        <div class="mt-2 grid gap-2 xl:grid-cols-2">
            <x-operations::card :title="trans('operations::app.dashboard.stock-by-location')">
                <table class="w-full text-left text-sm" v-pre>
                    <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        <tr>
                            <th class="px-2 py-1">@lang('operations::app.common.location')</th>
                            <th class="px-2 py-1 text-right">@lang('operations::app.stock.sellable')</th>
                            <th class="px-2 py-1 text-right">@lang('operations::app.stock.held')</th>
                            <th class="px-2 py-1 text-right">@lang('operations::app.common.value')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($m['stock_by_location'] as $row)
                            <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                <td class="px-2 py-1">{{ $row->location }}</td>
                                <td class="px-2 py-1 text-right">{{ (int) $row->sellable_units }}</td>
                                <td class="px-2 py-1 text-right">{{ (int) $row->held_units }}</td>
                                <td class="px-2 py-1 text-right">{{ $money($row->sellable_value + $row->held_value) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-2 py-3 text-center text-gray-500">@lang('operations::app.dashboard.none')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-operations::card>

            @if ($m['sales'])
                @include('operations::admin.dashboard.sales', ['sales' => $m['sales']])
            @endif
        </div>
    @endisset

    {{-- Warehouse --}}
    @isset($sections['warehouse'])
        @php($w = $sections['warehouse'])

        <p class="mb-2 mt-5 text-base font-semibold text-gray-800 dark:text-white">@lang('operations::app.dashboard.warehouse')</p>

        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                'pending-inspections' => [$w['pending_inspections'], route('admin.operations.incidents.index')],
                'receiving-discrepancies' => [$w['receiving_discrepancies'], route('admin.operations.reports.receiving_discrepancies')],
                'pending-replenishment' => [$w['pending_replenishment'], route('admin.operations.stock_requests.index')],
                'awaiting-receipt' => [$w['awaiting_receipt'], route('admin.operations.transfers.index')],
                'low-stock' => [$w['low_stock'], route('admin.operations.reports.low_stock')],
            ] as $label => [$count, $url])
                <x-operations::card>
                    <p class="text-xs text-gray-500">@lang('operations::app.dashboard.'.$label)</p>
                    <p class="text-xl font-semibold dark:text-white"><a class="hover:underline" href="{{ $url }}">{{ $count }}</a></p>
                </x-operations::card>
            @endforeach
        </div>

        <div class="mt-2 grid gap-2 xl:grid-cols-2">
            <x-operations::card :title="trans('operations::app.dashboard.expected-deliveries')">
                @forelse ($w['expected_deliveries'] as $order)
                    <p class="text-sm dark:text-gray-300">
                        <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.purchase_orders.view', $order->id) }}">{{ $order->reference }}</a>
                        <span v-pre>· {{ $order->supplier?->name }} · {{ $order->expected_delivery_date?->toDateString() ?? '—' }}</span>
                        @if ($order->expected_delivery_date?->lt(today()))
                            <span class="label-canceled">@lang('operations::app.common.overdue')</span>
                        @endif
                    </p>
                @empty
                    <p class="text-sm text-gray-500">@lang('operations::app.dashboard.none')</p>
                @endforelse
            </x-operations::card>

            <x-operations::card :title="trans('operations::app.dashboard.picking-tasks')">
                @forelse ($w['picking_tasks'] as $transfer)
                    <p class="text-sm dark:text-gray-300">
                        <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.transfers.view', $transfer->id) }}">{{ $transfer->reference }}</a>
                        <span v-pre>→ {{ $transfer->destinationLocation?->name }}</span>
                        · @lang('operations::app.priorities.'.$transfer->priority) <x-operations::status :status="$transfer->status" />
                    </p>
                @empty
                    <p class="text-sm text-gray-500">@lang('operations::app.dashboard.none')</p>
                @endforelse
            </x-operations::card>
        </div>
    @endisset

    {{-- Purchasing --}}
    @isset($sections['purchasing'])
        @php($p = $sections['purchasing'])

        <p class="mb-2 mt-5 text-base font-semibold text-gray-800 dark:text-white">@lang('operations::app.dashboard.purchasing')</p>

        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                'pending-requests' => [$p['pending_requests'], route('admin.operations.purchase_requests.index')],
                'approved-not-ordered' => [$p['approved_not_ordered'], route('admin.operations.purchase_orders.create')],
                'awaiting-delivery' => [$p['awaiting_delivery'], route('admin.operations.receiving.index')],
                'outstanding-returns' => [$p['outstanding_returns'], route('admin.operations.supplier_returns.index')],
            ] as $label => [$count, $url])
                <x-operations::card>
                    <p class="text-xs text-gray-500">@lang('operations::app.dashboard.'.$label)</p>
                    <p class="text-xl font-semibold dark:text-white"><a class="hover:underline" href="{{ $url }}">{{ $count }}</a></p>
                </x-operations::card>
            @endforeach
        </div>

        <div class="mt-2 grid gap-2 xl:grid-cols-2">
            <x-operations::card :title="trans('operations::app.dashboard.replenishment-suggestions')">
                <p class="mb-2 text-xs text-gray-500">@lang('operations::app.dashboard.replenishment-info')</p>

                <table class="w-full text-left text-sm" v-pre>
                    <tbody>
                        @forelse ($p['suggestions'] as $row)
                            <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                <td class="px-2 py-1 font-semibold">{{ $row->sku }}</td>
                                <td class="px-2 py-1">{{ $row->location }}</td>
                                <td class="px-2 py-1 text-right">{{ $row->on_hand }} / {{ $row->reorder_level }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-2 py-3 text-center text-gray-500">@lang('operations::app.dashboard.none')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-operations::card>

            <x-operations::card :title="trans('operations::app.dashboard.supplier-performance')">
                <table class="w-full text-left text-sm" v-pre>
                    <tbody>
                        @forelse ($p['supplier_performance'] as $row)
                            <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                <td class="px-2 py-1">{{ $row->supplier }}</td>
                                <td class="px-2 py-1 text-right">{{ $row->orders }} PO</td>
                                <td class="px-2 py-1 text-right">@lang('operations::app.reports.columns.fill_rate'): {{ $row->fill_rate ?? '—' }}%</td>
                                <td class="px-2 py-1 text-right">@lang('operations::app.reports.columns.on_time_rate'): {{ $row->on_time_rate ?? '—' }}%</td>
                            </tr>
                        @empty
                            <tr><td class="px-2 py-3 text-center text-gray-500">@lang('operations::app.dashboard.insufficient-data')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-operations::card>
        </div>
    @endisset

    {{-- Branch --}}
    @isset($sections['branch'])
        @php($b = $sections['branch'])

        <p class="mb-2 mt-5 text-base font-semibold text-gray-800 dark:text-white">@lang('operations::app.dashboard.branch')</p>

        <div class="grid gap-2 xl:grid-cols-3">
            <x-operations::card :title="trans('operations::app.dashboard.low-stock')">
                @forelse ($b['low_stock'] as $row)
                    <p class="text-sm dark:text-gray-300" v-pre>{{ $row->sku }} · {{ $row->location }} · {{ $row->on_hand }} / {{ $row->reorder_level }}</p>
                @empty
                    <p class="text-sm text-gray-500">@lang('operations::app.dashboard.none')</p>
                @endforelse

                @if (bouncer()->hasPermission('operations.stock_requests.create'))
                    <a href="{{ route('admin.operations.stock_requests.create') }}" class="secondary-button mt-3">@lang('operations::app.stock-requests.create')</a>
                @endif
            </x-operations::card>

            <x-operations::card :title="trans('operations::app.dashboard.incoming')">
                <p class="mb-2 text-sm dark:text-gray-300">@lang('operations::app.dashboard.pending-sr', ['count' => $b['pending_requests']])</p>

                @forelse ($b['incoming'] as $transfer)
                    <p class="text-sm dark:text-gray-300">
                        <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.transfers.view', $transfer->id) }}">{{ $transfer->reference }}</a>
                        <span v-pre>← {{ $transfer->sourceLocation?->name }}</span>
                        <x-operations::status :status="$transfer->status" />
                    </p>
                @empty
                    <p class="text-sm text-gray-500">@lang('operations::app.dashboard.none')</p>
                @endforelse
            </x-operations::card>

            <x-operations::card :title="trans('operations::app.dashboard.recent-adjustments')">
                @forelse ($b['recent_adjustments'] as $adjustment)
                    <p class="text-sm dark:text-gray-300">
                        <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.adjustments.view', $adjustment->id) }}">{{ $adjustment->reference }}</a>
                        · @lang('operations::app.adjustments.reasons.'.$adjustment->reason_code) · {{ $money($adjustment->total_value) }}
                    </p>
                @empty
                    <p class="text-sm text-gray-500">@lang('operations::app.dashboard.none')</p>
                @endforelse
            </x-operations::card>
        </div>

        @if ($b['sales'] !== null)
            <x-operations::card class="mt-2" :title="trans('operations::app.reports.names.branch_sales')">
                @forelse ($b['sales'] as $row)
                    <p class="text-sm dark:text-gray-300" v-pre>{{ $row->location }}: {{ (int) $row->orders }} · {{ (int) $row->units }} · {{ $money($row->revenue) }}</p>
                @empty
                    <p class="text-sm text-gray-500">@lang('operations::app.dashboard.no-sales')</p>
                @endforelse
            </x-operations::card>
        @endif
    @endisset

    {{-- Finance --}}
    @isset($sections['finance'])
        @php($f = $sections['finance'])

        <p class="mb-2 mt-5 text-base font-semibold text-gray-800 dark:text-white">@lang('operations::app.dashboard.finance') <span class="text-xs font-normal text-gray-500">@lang('operations::app.dashboard.since', ['date' => $f['since']])</span></p>

        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                'inventory-value' => $money($f['inventory_value']),
                'adjustment-value' => $money($f['adjustment_value']).' ('.$f['adjustment_lines'].')',
                'damaged-value' => $money($f['damaged_value']),
                'expired-value' => $money($f['expired_value']),
                'quarantine-value' => $money($f['quarantine_value']),
                'disposal-value' => $money($f['disposal_value']),
                'received-value' => $money($f['received_value']).' ('.$f['receipts'].')',
            ] as $label => $value)
                <x-operations::card>
                    <p class="text-xs text-gray-500">@lang('operations::app.dashboard.'.$label)</p>
                    <p class="text-lg font-semibold dark:text-white">{{ $value }}</p>
                </x-operations::card>
            @endforeach
        </div>

        <p class="mt-2 text-xs">
            <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.reports.adjustments') }}">@lang('operations::app.reports.names.adjustments')</a> ·
            <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.reports.valuation') }}">@lang('operations::app.reports.names.valuation')</a> ·
            <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.reports.disposals') }}">@lang('operations::app.reports.names.disposals')</a>
        </p>
    @endisset
</x-admin::layouts>
