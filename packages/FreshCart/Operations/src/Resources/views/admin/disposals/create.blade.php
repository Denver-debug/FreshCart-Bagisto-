<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.disposals.create')
    </x-slot>

    <form method="POST" action="{{ route('admin.operations.disposals.store') }}">
        @csrf

        <x-operations::header :title="trans('operations::app.disposals.create')" :back="route('admin.operations.disposals.index')">
            <button type="submit" class="primary-button">@lang('operations::app.common.save-draft')</button>
        </x-operations::header>

        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                <x-operations::card :title="trans('operations::app.common.items')">
                    <x-operations::line-items :items="old('items', [])" location-field="inventory_source_id" />
                </x-operations::card>
            </div>

            <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                <x-operations::card :title="trans('operations::app.common.general')">
                    <x-operations::field name="inventory_source_id" type="select" :label="trans('operations::app.common.location')" :options="$locationOptions" :placeholder="trans('operations::app.common.select')" required />

                    <x-operations::field
                        name="item_reason"
                        type="select"
                        :label="trans('operations::app.common.reason')"
                        :options="collect(\FreshCart\Operations\Models\DisposalRequest::REASONS)->mapWithKeys(fn ($r) => [$r => trans('operations::app.disposals.reasons.'.$r)])->all()"
                        required
                    />

                    <x-operations::field
                        name="source_bucket"
                        type="select"
                        :label="trans('operations::app.adjustments.source-bucket')"
                        :options="collect(array_merge(['sellable'], \FreshCart\Operations\Models\StockHold::BUCKETS))->reject(fn ($b) => $b === 'in_transit')->mapWithKeys(fn ($b) => [$b => trans('operations::app.buckets.'.$b)])->all()"
                        required
                    />

                    <x-operations::field
                        name="disposition"
                        type="select"
                        :label="trans('operations::app.disposals.disposition')"
                        :options="['dispose' => trans('operations::app.disposals.dispositions.dispose'), 'return_to_supplier' => trans('operations::app.disposals.dispositions.return_to_supplier')]"
                        required
                    />

                    <x-operations::field name="supplier_id" type="select" :label="trans('operations::app.common.supplier')" :options="$suppliers" :placeholder="trans('operations::app.common.select')" />

                    <x-operations::field name="reason" type="textarea" :label="trans('operations::app.adjustments.explanation')" required />
                </x-operations::card>
            </div>
        </div>
    </form>
</x-admin::layouts>
