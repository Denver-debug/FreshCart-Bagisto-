<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.receiving.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.receiving.title')" />

    <x-operations::card class="mt-3.5" :title="trans('operations::app.receiving.expected')">
        @if ($expected->isEmpty())
            <p class="text-sm text-gray-500">@lang('operations::app.receiving.none-expected')</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        <tr>
                            <th class="px-2 py-2">@lang('operations::app.purchase-orders.reference')</th>
                            <th class="px-2 py-2">@lang('operations::app.common.supplier')</th>
                            <th class="px-2 py-2">@lang('operations::app.purchase-orders.deliver-to')</th>
                            <th class="px-2 py-2">@lang('operations::app.purchase-orders.expected-delivery')</th>
                            <th class="px-2 py-2">@lang('operations::app.common.status')</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($expected as $order)
                            <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                <td class="px-2 py-2 font-semibold"><a class="text-blue-600 hover:underline" href="{{ route('admin.operations.purchase_orders.view', $order->id) }}">{{ $order->reference }}</a></td>
                                <td class="px-2 py-2" v-pre>{{ $order->supplier?->name }}</td>
                                <td class="px-2 py-2" v-pre>{{ $order->location?->name }}</td>
                                <td class="px-2 py-2">
                                    {{ $order->expected_delivery_date?->toDateString() ?? '—' }}

                                    @if ($order->expected_delivery_date?->isPast() && ! $order->expected_delivery_date->isToday())
                                        <span class="label-canceled">@lang('operations::app.common.overdue')</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2"><x-operations::status :status="$order->status" /></td>
                                <td class="px-2 py-2 text-right">
                                    @if (bouncer()->hasPermission('operations.receiving.create'))
                                        <a href="{{ route('admin.operations.receiving.create', $order->id) }}" class="secondary-button">@lang('operations::app.receiving.receive-goods')</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-operations::card>

    <p class="mb-2 mt-5 text-base font-semibold text-gray-800 dark:text-white">@lang('operations::app.receiving.history')</p>

    <x-admin::datagrid :src="route('admin.operations.receiving.index')" />
</x-admin::layouts>
