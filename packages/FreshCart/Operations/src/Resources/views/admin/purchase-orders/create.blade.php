<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.purchase-orders.create')
    </x-slot>

    <form method="POST" action="{{ route('admin.operations.purchase_orders.store') }}">
        @csrf

        <x-operations::header :title="trans('operations::app.purchase-orders.create')" :back="route('admin.operations.purchase_orders.index')">
            <button type="submit" class="primary-button" @disabled($requests->isEmpty())>@lang('operations::app.purchase-orders.create-draft')</button>
        </x-operations::header>

        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                <x-operations::card :title="trans('operations::app.purchase-orders.approved-lines')">
                    <x-operations::error name="lines" />

                    @if ($requests->isEmpty())
                        <p class="text-sm text-gray-500">@lang('operations::app.purchase-orders.no-approved')</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th class="px-2 py-2"></th>
                                        <th class="px-2 py-2">@lang('operations::app.common.reference')</th>
                                        <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                                        <th class="px-2 py-2">@lang('operations::app.common.product')</th>
                                        <th class="px-2 py-2 text-right">@lang('operations::app.common.remaining')</th>
                                        <th class="px-2 py-2">@lang('operations::app.common.qty')</th>
                                        <th class="px-2 py-2">@lang('operations::app.common.unit-cost')</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @php($index = 0)

                                    @foreach ($requests as $request)
                                        @foreach ($request->items as $item)
                                            @php($remaining = $item->qty - (int) ($ordered[$item->id] ?? 0))

                                            @continue($remaining <= 0)

                                            <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                                <td class="px-2 py-2">
                                                    <input type="hidden" name="lines[{{ $index }}][purchase_request_item_id]" value="{{ $item->id }}">
                                                    <input type="hidden" name="lines[{{ $index }}][selected]" value="0">
                                                    <input type="checkbox" name="lines[{{ $index }}][selected]" value="1" @checked(old("lines.$index.selected", 1)) aria-label="{{ $item->sku }}">
                                                </td>
                                                <td class="px-2 py-2" v-pre>{{ $request->reference }}<span class="block text-xs text-gray-500">{{ $request->location?->name }}</span></td>
                                                <td class="px-2 py-2 font-semibold" v-pre>{{ $item->sku }}</td>
                                                <td class="px-2 py-2" v-pre>{{ $item->name }}</td>
                                                <td class="px-2 py-2 text-right">{{ $remaining }}</td>
                                                <td class="px-2 py-2">
                                                    <input type="number" min="0" max="{{ $remaining }}" step="1" name="lines[{{ $index }}][qty]" value="{{ old("lines.$index.qty", $remaining) }}" class="w-24 rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900" aria-label="@lang('operations::app.common.qty')">
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="number" min="0" step="0.0001" name="lines[{{ $index }}][unit_cost]" value="{{ old("lines.$index.unit_cost", $item->estimated_unit_cost ?? 0) }}" class="w-32 rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900" aria-label="@lang('operations::app.common.unit-cost')">
                                                </td>
                                            </tr>

                                            @php($index++)
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-operations::card>
            </div>

            <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                <x-operations::card :title="trans('operations::app.common.general')">
                    <x-operations::field name="supplier_id" type="select" :label="trans('operations::app.common.supplier')" :options="$suppliers" :placeholder="trans('operations::app.common.select')" required />

                    <x-operations::field
                        name="inventory_source_id"
                        type="select"
                        :label="trans('operations::app.purchase-orders.deliver-to')"
                        :options="$locationOptions"
                        :value="$requests->first()?->inventory_source_id"
                        required
                    />

                    <x-operations::field name="expected_delivery_date" type="date" :label="trans('operations::app.purchase-orders.expected-delivery')" />

                    <x-operations::field name="notes" type="textarea" :label="trans('operations::app.common.notes')" />
                </x-operations::card>
            </div>
        </div>
    </form>
</x-admin::layouts>
