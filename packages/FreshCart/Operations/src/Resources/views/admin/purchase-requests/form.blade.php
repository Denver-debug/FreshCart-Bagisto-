<x-admin::layouts>
    <x-slot:title>
        {{ $purchaseRequest->exists ? $purchaseRequest->reference : trans('operations::app.purchase-requests.create') }}
    </x-slot>

    <form
        method="POST"
        action="{{ $purchaseRequest->exists ? route('admin.operations.purchase_requests.update', $purchaseRequest->id) : route('admin.operations.purchase_requests.store') }}"
    >
        @csrf

        @if ($purchaseRequest->exists)
            @method('PUT')
        @endif

        <x-operations::header
            :title="$purchaseRequest->exists ? $purchaseRequest->reference : trans('operations::app.purchase-requests.create')"
            :back="$purchaseRequest->exists ? route('admin.operations.purchase_requests.view', $purchaseRequest->id) : route('admin.operations.purchase_requests.index')"
        >
            <button type="submit" class="primary-button">@lang('operations::app.common.save-draft')</button>
        </x-operations::header>

        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                <x-operations::card :title="trans('operations::app.common.items')">
                    <x-operations::line-items
                        :items="old('items', $purchaseRequest->items->map(fn ($item) => [
                            'product_id' => $item->product_id,
                            'sku' => $item->sku,
                            'name' => $item->name,
                            'qty' => $item->qty,
                            'estimated_unit_cost' => $item->estimated_unit_cost,
                        ])->all())"
                        location-field="inventory_source_id"
                        :extra="[
                            ['key' => 'estimated_unit_cost', 'label' => trans('operations::app.purchase-requests.estimated-unit-cost'), 'step' => '0.01', 'min' => 0, 'default_from' => 'cost'],
                        ]"
                    />
                </x-operations::card>
            </div>

            <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                <x-operations::card :title="trans('operations::app.common.general')">
                    <x-operations::field
                        name="inventory_source_id"
                        type="select"
                        :label="trans('operations::app.purchase-requests.requesting-location')"
                        :options="$locationOptions"
                        :value="$purchaseRequest->inventory_source_id"
                        :placeholder="trans('operations::app.common.select')"
                        required
                    />

                    <x-operations::field
                        name="priority"
                        type="select"
                        :label="trans('operations::app.common.priority')"
                        :options="collect(\FreshCart\Operations\Models\PurchaseRequest::PRIORITIES)->mapWithKeys(fn ($p) => [$p => trans('operations::app.priorities.'.$p)])->all()"
                        :value="$purchaseRequest->priority"
                        required
                    />

                    <x-operations::field
                        name="needed_by"
                        type="date"
                        :label="trans('operations::app.common.needed-by')"
                        :value="$purchaseRequest->needed_by?->toDateString()"
                    />

                    <x-operations::field
                        name="justification"
                        type="textarea"
                        :label="trans('operations::app.purchase-requests.justification')"
                        :value="$purchaseRequest->justification"
                        required
                    />
                </x-operations::card>
            </div>
        </div>
    </form>
</x-admin::layouts>
