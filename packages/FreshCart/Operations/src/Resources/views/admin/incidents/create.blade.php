<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.incidents.create')
    </x-slot>

    <form method="POST" action="{{ route('admin.operations.incidents.store') }}" enctype="multipart/form-data" id="fc-incident-form">
        @csrf

        <x-operations::header :title="trans('operations::app.incidents.create')" :back="route('admin.operations.incidents.index')">
            <button
                type="button"
                class="primary-button"
                @click="$emitter.emit('open-confirm-modal', { message: '{{ e(addslashes(trans('operations::app.incidents.confirm-report'))) }}', agree: () => document.getElementById('fc-incident-form').requestSubmit() })"
            >
                @lang('operations::app.incidents.report')
            </button>
        </x-operations::header>

        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.incidents.create-info')</p>

        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                <x-operations::card :title="trans('operations::app.common.product')">
                    <x-operations::line-items :items="old('items', [])" location-field="inventory_source_id" />

                    <p class="mt-2 text-xs text-gray-500">@lang('operations::app.incidents.one-product')</p>
                </x-operations::card>

                <x-operations::card :title="trans('operations::app.incidents.photos')">
                    <input type="file" name="photos[]" accept="image/*" capture="environment" multiple class="text-sm dark:text-gray-300" aria-label="@lang('operations::app.incidents.photos')">
                    <p class="mt-1 text-xs text-gray-500">@lang('operations::app.incidents.photos-help')</p>
                    <x-operations::error name="photos" />
                    <x-operations::error name="photos.0" />
                </x-operations::card>
            </div>

            <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                <x-operations::card :title="trans('operations::app.common.general')">
                    <x-operations::field name="inventory_source_id" type="select" :label="trans('operations::app.common.location')" :options="$locationOptions" :placeholder="trans('operations::app.common.select')" required />

                    <x-operations::field
                        name="incident_type"
                        type="select"
                        :label="trans('operations::app.incidents.type')"
                        :options="collect(\FreshCart\Operations\Models\Incident::TYPES)->mapWithKeys(fn ($t) => [$t => trans('operations::app.incidents.types.'.$t)])->all()"
                        required
                    />

                    <x-operations::field name="batch_no" :label="trans('operations::app.common.batch')" />
                    <x-operations::field name="expiry_date" type="date" :label="trans('operations::app.common.expiry')" />
                    <x-operations::field name="supplier_id" type="select" :label="trans('operations::app.common.supplier')" :options="$suppliers" :placeholder="trans('operations::app.common.select')" />
                    <x-operations::field name="description" type="textarea" :label="trans('operations::app.incidents.description')" required />
                </x-operations::card>
            </div>
        </div>
    </form>
</x-admin::layouts>
