<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.stock-requests.allocate-title', ['reference' => $stockRequest->reference])
    </x-slot>

    <x-operations::header
        :title="trans('operations::app.stock-requests.allocate-title', ['reference' => $stockRequest->reference])"
        :status="$stockRequest->status"
        :back="route('admin.operations.stock_requests.view', $stockRequest->id)"
    />

    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.stock-requests.rule-info')</p>

    <form method="GET" class="mt-3 flex flex-wrap items-end gap-2">
        <x-operations::field class="!mb-0" name="warehouse_id" type="select" :label="trans('operations::app.stock-requests.warehouse')" :options="$warehouses" :value="$warehouseId" />
        <button type="submit" class="secondary-button">@lang('operations::app.stock-requests.recalculate')</button>
    </form>

    <form method="POST" action="{{ route('admin.operations.stock_requests.allocate', $stockRequest->id) }}" id="fc-allocate-form">
        @csrf

        <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">

        <x-operations::card class="mt-3">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        <tr>
                            <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.requested')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.allocated-qty')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.common.outstanding')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.warehouse-available')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.warehouse-min')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.branch-on-hand')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.suggested')</th>
                            <th class="px-2 py-2">@lang('operations::app.stock-requests.allocate-qty')</th>
                            <th class="px-2 py-2">@lang('operations::app.stock-requests.override-reason')</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($lines as $itemId => $line)
                            <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                <td class="px-2 py-2 font-semibold" v-pre>{{ $line['item']->sku }}<span class="block text-xs font-normal text-gray-500">{{ $line['item']->name }}</span></td>
                                <td class="px-2 py-2 text-right">{{ $line['requested'] }}</td>
                                <td class="px-2 py-2 text-right">{{ $line['allocated'] }}</td>
                                <td class="px-2 py-2 text-right">{{ $line['outstanding'] }}</td>
                                <td class="px-2 py-2 text-right">
                                    {{ $line['warehouse_available'] }}

                                    @if ($line['warehouse_committed'])
                                        <span class="block text-xs text-gray-500">@lang('operations::app.stock-requests.committed', ['qty' => $line['warehouse_committed']])</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right">{{ $line['warehouse_min'] }}</td>
                                <td class="px-2 py-2 text-right">
                                    {{ $line['branch_on_hand'] }}

                                    @if ($line['branch_max'] !== null)
                                        <span class="block text-xs text-gray-500">@lang('operations::app.stock-requests.max', ['qty' => $line['branch_max']])</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right font-semibold">{{ $line['suggested'] }}</td>
                                <td class="px-2 py-2">
                                    <input
                                        type="number"
                                        min="0"
                                        max="{{ $line['outstanding'] }}"
                                        name="allocations[{{ $itemId }}][qty]"
                                        value="{{ old("allocations.$itemId.qty", $line['suggested']) }}"
                                        class="w-24 rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900"
                                        aria-label="@lang('operations::app.stock-requests.allocate-qty')"
                                    >
                                </td>
                                <td class="px-2 py-2">
                                    <input
                                        type="text"
                                        name="allocations[{{ $itemId }}][reason]"
                                        value="{{ old("allocations.$itemId.reason") }}"
                                        class="w-48 rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900"
                                        placeholder="@lang('operations::app.stock-requests.reason-placeholder')"
                                        aria-label="@lang('operations::app.stock-requests.override-reason')"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="primary-button"
                    @click="$emitter.emit('open-confirm-modal', { message: '{{ e(addslashes(trans('operations::app.stock-requests.confirm-allocate'))) }}', agree: () => document.getElementById('fc-allocate-form').requestSubmit() })"
                >
                    @lang('operations::app.stock-requests.allocate')
                </button>
            </div>
        </x-operations::card>
    </form>

    @if ($stockRequest->status === 'submitted')
        <x-operations::card class="mt-3" :title="trans('operations::app.stock-requests.reject')">
            <form method="POST" action="{{ route('admin.operations.stock_requests.reject', $stockRequest->id) }}">
                @csrf

                <x-operations::field name="remarks" type="textarea" :label="trans('operations::app.common.remarks')" required />

                <button type="submit" class="secondary-button">@lang('operations::app.common.reject')</button>
            </form>
        </x-operations::card>
    @endif
</x-admin::layouts>
