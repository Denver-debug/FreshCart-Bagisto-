<x-admin::layouts>
    <x-slot:title>
        {{ $stockRequest->exists ? $stockRequest->reference : trans('operations::app.stock-requests.create') }}
    </x-slot>

    <form
        method="POST"
        action="{{ $stockRequest->exists ? route('admin.operations.stock_requests.update', $stockRequest->id) : route('admin.operations.stock_requests.store') }}"
    >
        @csrf

        @if ($stockRequest->exists)
            @method('PUT')
        @endif

        <x-operations::header
            :title="$stockRequest->exists ? $stockRequest->reference : trans('operations::app.stock-requests.create')"
            :back="route('admin.operations.stock_requests.index')"
        >
            <button type="submit" class="primary-button">@lang('operations::app.common.save-draft')</button>
        </x-operations::header>

        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                <x-operations::card :title="trans('operations::app.common.items')">
                    <x-operations::line-items
                        :items="old('items', $stockRequest->items->map(fn ($item) => [
                            'product_id' => $item->product_id,
                            'sku' => $item->sku,
                            'name' => $item->name,
                            'qty' => $item->qty_requested,
                        ])->all())"
                        location-field="inventory_source_id"
                    />

                    <p class="mt-2 text-xs text-gray-500">@lang('operations::app.stock-requests.available-hint')</p>
                </x-operations::card>
            </div>

            <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                <x-operations::card :title="trans('operations::app.common.general')">
                    <x-operations::field
                        name="inventory_source_id"
                        type="select"
                        :label="trans('operations::app.stock-requests.branch')"
                        :options="$branchOptions"
                        :value="$stockRequest->inventory_source_id"
                        :placeholder="trans('operations::app.common.select')"
                        required
                    />

                    <x-operations::field
                        name="priority"
                        type="select"
                        :label="trans('operations::app.common.priority')"
                        :options="collect(\FreshCart\Operations\Models\PurchaseRequest::PRIORITIES)->mapWithKeys(fn ($p) => [$p => trans('operations::app.priorities.'.$p)])->all()"
                        :value="$stockRequest->priority"
                        required
                    />

                    <x-operations::field name="needed_by" type="date" :label="trans('operations::app.stock-requests.required-delivery')" :value="$stockRequest->needed_by?->toDateString()" />

                    <x-operations::field name="notes" type="textarea" :label="trans('operations::app.common.notes')" :value="$stockRequest->notes" />
                </x-operations::card>
            </div>
        </div>
    </form>
</x-admin::layouts>
