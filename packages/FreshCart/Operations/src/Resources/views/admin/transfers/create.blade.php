<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.transfers.create')
    </x-slot>

    <form method="POST" action="{{ route('admin.operations.transfers.store') }}">
        @csrf

        <x-operations::header :title="trans('operations::app.transfers.create')" :back="route('admin.operations.transfers.index')">
            <button type="submit" class="primary-button">@lang('operations::app.common.save-draft')</button>
        </x-operations::header>

        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                <x-operations::card :title="trans('operations::app.common.items')">
                    <x-operations::line-items :items="old('items', [])" location-field="source_inventory_source_id" />

                    <p class="mt-2 text-xs text-gray-500">@lang('operations::app.transfers.available-hint')</p>
                </x-operations::card>
            </div>

            <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                <x-operations::card :title="trans('operations::app.common.general')">
                    <x-operations::field
                        name="type"
                        type="select"
                        :label="trans('operations::app.transfers.type')"
                        :options="[
                            'inter_branch' => trans('operations::app.transfers.types.inter_branch'),
                            'temporary' => trans('operations::app.transfers.types.temporary'),
                            'emergency' => trans('operations::app.transfers.types.emergency'),
                        ]"
                        required
                    />

                    <x-operations::field name="source_inventory_source_id" type="select" :label="trans('operations::app.common.source')" :options="$sourceOptions" :placeholder="trans('operations::app.common.select')" required />

                    <x-operations::field name="destination_inventory_source_id" type="select" :label="trans('operations::app.common.destination')" :options="$destinationOptions" :placeholder="trans('operations::app.common.select')" required />

                    <x-operations::field
                        name="priority"
                        type="select"
                        :label="trans('operations::app.common.priority')"
                        :options="collect(\FreshCart\Operations\Models\PurchaseRequest::PRIORITIES)->mapWithKeys(fn ($p) => [$p => trans('operations::app.priorities.'.$p)])->all()"
                        value="normal"
                        required
                    />

                    <x-operations::field name="expected_return_date" type="date" :label="trans('operations::app.transfers.expected-return')" :help="trans('operations::app.transfers.expected-return-help')" />

                    <x-operations::field name="reason" type="textarea" :label="trans('operations::app.common.reason')" required />
                </x-operations::card>
            </div>
        </div>
    </form>
</x-admin::layouts>
