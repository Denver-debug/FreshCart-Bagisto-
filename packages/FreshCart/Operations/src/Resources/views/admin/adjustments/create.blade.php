<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.adjustments.create')
    </x-slot>

    <form method="POST" action="{{ route('admin.operations.adjustments.store') }}">
        @csrf

        <x-operations::header :title="trans('operations::app.adjustments.create')" :back="route('admin.operations.adjustments.index')">
            <button type="submit" class="primary-button">@lang('operations::app.common.save-draft')</button>
        </x-operations::header>

        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.adjustments.create-info')</p>

        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                <x-operations::card :title="trans('operations::app.common.items')">
                    <x-operations::line-items
                        :items="old('items', [])"
                        location-field="inventory_source_id"
                        :extra="[['key' => 'unit_cost', 'label' => trans('operations::app.common.unit-cost'), 'step' => '0.0001', 'min' => 0, 'default_from' => 'cost']]"
                    />
                </x-operations::card>
            </div>

            <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                <x-operations::card :title="trans('operations::app.common.general')">
                    <x-operations::field name="inventory_source_id" type="select" :label="trans('operations::app.common.location')" :options="$locationOptions" :placeholder="trans('operations::app.common.select')" required />

                    <x-operations::field
                        name="direction"
                        type="select"
                        :label="trans('operations::app.adjustments.direction')"
                        :options="['out' => trans('operations::app.adjustments.directions.out'), 'in' => trans('operations::app.adjustments.directions.in')]"
                        required
                    />

                    <x-operations::field
                        name="source_bucket"
                        type="select"
                        :label="trans('operations::app.adjustments.source-bucket')"
                        :options="collect(\FreshCart\Operations\Models\StockHold::BUCKETS)->mapWithKeys(fn ($b) => [$b => trans('operations::app.buckets.'.$b)])->all()"
                        :placeholder="trans('operations::app.buckets.sellable')"
                        :help="trans('operations::app.adjustments.source-bucket-help')"
                    />

                    <x-operations::field
                        name="reason_code"
                        type="select"
                        :label="trans('operations::app.common.reason')"
                        :options="collect(\FreshCart\Operations\Models\Adjustment::REASONS)->mapWithKeys(fn ($r) => [$r => trans('operations::app.adjustments.reasons.'.$r)])->all()"
                        required
                    />

                    <x-operations::field name="reason" type="textarea" :label="trans('operations::app.adjustments.explanation')" required />
                </x-operations::card>
            </div>
        </div>
    </form>
</x-admin::layouts>
