<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.stock-policies.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.stock-policies.title')" />

    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.stock-policies.info')</p>

    <div class="mt-3.5 grid gap-2.5 xl:grid-cols-2">
        <x-operations::card :title="trans('operations::app.stock-policies.add')">
            <form method="POST" action="{{ route('admin.operations.stock_policies.store') }}">
                @csrf

                <x-operations::field
                    name="inventory_source_id"
                    type="select"
                    :label="trans('operations::app.common.location')"
                    :options="$locationOptions"
                    :placeholder="trans('operations::app.common.select')"
                    required
                />

                <x-operations::line-items
                    :items="old('items', [])"
                    location-field="inventory_source_id"
                    :extra="[
                        ['key' => 'reorder_level', 'label' => trans('operations::app.stock-policies.reorder-level'), 'step' => 1, 'min' => 0, 'default' => 0],
                        ['key' => 'min_stock', 'label' => trans('operations::app.stock-policies.min-stock'), 'step' => 1, 'min' => 0, 'default' => 0],
                        ['key' => 'max_stock', 'label' => trans('operations::app.stock-policies.max-stock'), 'step' => 1, 'min' => 0],
                    ]"
                />

                <p class="mt-2 text-xs text-gray-500">@lang('operations::app.stock-policies.qty-ignored')</p>

                <button type="submit" class="primary-button mt-3">@lang('operations::app.common.save')</button>
            </form>
        </x-operations::card>

        <x-operations::card :title="trans('operations::app.stock-policies.import')">
            <form method="POST" action="{{ route('admin.operations.stock_policies.import') }}" enctype="multipart/form-data">
                @csrf

                <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.stock-policies.import-info')</p>

                <input type="file" name="file" accept=".csv,text/csv" class="text-sm dark:text-gray-300" required aria-label="@lang('operations::app.stock-policies.import')">

                <x-operations::error name="file" />

                <button type="submit" class="secondary-button mt-3">@lang('operations::app.common.upload')</button>
            </form>
        </x-operations::card>
    </div>

    <x-operations::card class="mt-2.5">
        <form method="GET" class="mb-3 flex flex-wrap items-end gap-2">
            <x-operations::field class="!mb-0" name="term" :label="trans('operations::app.common.sku')" :value="$filters['term'] ?? ''" />
            <x-operations::field class="!mb-0" name="location_id" type="select" :label="trans('operations::app.common.location')" :options="$locationOptions" :placeholder="trans('operations::app.common.all')" :value="$filters['location_id'] ?? ''" />
            <button type="submit" class="secondary-button">@lang('operations::app.common.filter')</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                        <th class="px-2 py-2">@lang('operations::app.common.location')</th>
                        <th class="px-2 py-2">@lang('operations::app.stock-policies.reorder-level')</th>
                        <th class="px-2 py-2">@lang('operations::app.stock-policies.min-stock')</th>
                        <th class="px-2 py-2">@lang('operations::app.stock-policies.max-stock')</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($policies as $policy)
                        <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                            <td class="px-2 py-2" v-pre>{{ $policy->sku }}</td>
                            <td class="px-2 py-2" v-pre>{{ $policy->location?->name }}</td>
                            <td class="px-2 py-2">{{ $policy->reorder_level }}</td>
                            <td class="px-2 py-2">{{ $policy->min_stock }}</td>
                            <td class="px-2 py-2">{{ $policy->max_stock ?? '—' }}</td>
                            <td class="px-2 py-2 text-right">
                                <x-operations::action
                                    :action="route('admin.operations.stock_policies.delete', $policy->id)"
                                    method="DELETE"
                                    :label="trans('operations::app.common.delete')"
                                    :confirm="trans('operations::app.common.confirm-delete')"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-2 py-6 text-center text-gray-500">@lang('operations::app.common.empty')</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $policies->links() }}</div>
    </x-operations::card>
</x-admin::layouts>
