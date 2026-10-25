<x-admin::layouts>
    <x-slot:title>
        {{ $supplierReturn->reference }}
    </x-slot>

    @php($canManage = bouncer()->hasPermission('operations.supplier_returns.manage'))

    <x-operations::header :title="$supplierReturn->reference" :status="$supplierReturn->status" :back="route('admin.operations.supplier_returns.index')">
        @if ($supplierReturn->status === 'pending' && $canManage)
            <x-operations::action
                :action="route('admin.operations.supplier_returns.dispatch', $supplierReturn->id)"
                :label="trans('operations::app.supplier-returns.dispatch')"
                :confirm="trans('operations::app.supplier-returns.confirm-dispatch')"
                variant="primary"
            />

            <x-operations::action
                :action="route('admin.operations.supplier_returns.cancel', $supplierReturn->id)"
                :label="trans('operations::app.common.cancel')"
                :confirm="trans('operations::app.supplier-returns.confirm-cancel')"
            />
        @endif
    </x-operations::header>

    <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
        <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
            <form method="POST" action="{{ route('admin.operations.supplier_returns.resolve', $supplierReturn->id) }}">
                @csrf

                <x-operations::card :title="trans('operations::app.common.items')">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                                <tr>
                                    <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                                    <th class="px-2 py-2">@lang('operations::app.common.product')</th>
                                    <th class="px-2 py-2 text-right">@lang('operations::app.common.qty')</th>
                                    <th class="px-2 py-2 text-right">@lang('operations::app.common.value')</th>
                                    <th class="px-2 py-2">@lang('operations::app.supplier-returns.qty-replaced')</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($supplierReturn->items as $item)
                                    <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                        <td class="px-2 py-2 font-semibold" v-pre>{{ $item->sku }}</td>
                                        <td class="px-2 py-2" v-pre>
                                            {{ $item->name }}

                                            @unless ($quality->isInStock($item))
                                                <span class="block text-xs text-gray-500">@lang('operations::app.supplier-returns.not-in-stock')</span>
                                            @endunless
                                        </td>
                                        <td class="px-2 py-2 text-right">{{ $item->qty }}</td>
                                        <td class="px-2 py-2 text-right">{{ core()->formatBasePrice($item->qty * (float) $item->unit_cost) }}</td>
                                        <td class="px-2 py-2">
                                            @if ($supplierReturn->status === 'dispatched' && $canManage && $quality->isInStock($item))
                                                <input type="number" min="0" max="{{ $item->qty }}" name="replaced[{{ $item->id }}]" value="0" class="w-24 rounded-md border px-2 py-1.5 dark:border-gray-800 dark:bg-gray-900" aria-label="@lang('operations::app.supplier-returns.qty-replaced')">
                                            @else
                                                {{ $item->qty_replaced }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-operations::card>

                @if ($supplierReturn->status === 'dispatched' && $canManage)
                    <x-operations::card class="mt-2" :title="trans('operations::app.supplier-returns.resolve')">
                        <p class="mb-3 text-xs text-gray-500">@lang('operations::app.supplier-returns.resolve-info')</p>

                        <div class="grid gap-x-3 sm:grid-cols-2">
                            <x-operations::field name="credit_amount" type="number" step="0.01" min="0" :label="trans('operations::app.supplier-returns.credit-amount')" />
                            <x-operations::field name="notes" :label="trans('operations::app.common.notes')" />
                        </div>

                        <button type="submit" class="primary-button">@lang('operations::app.supplier-returns.resolve')</button>
                    </x-operations::card>
                @endif
            </form>
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.supplier')</dt><dd>{{ $supplierReturn->supplier?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.location')</dt><dd>{{ $supplierReturn->location?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.supplier-returns.credit-amount')</dt><dd>{{ $supplierReturn->credit_amount !== null ? core()->formatBasePrice((float) $supplierReturn->credit_amount) : '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.notes')</dt><dd class="whitespace-pre-line">{{ $supplierReturn->notes ?: '—' }}</dd></div>
                </dl>
            </x-operations::card>

            <x-operations::history :document="$supplierReturn" />
        </div>
    </div>
</x-admin::layouts>
