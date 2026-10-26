<x-admin::layouts>
    <x-slot:title>
        {{ $supplier->exists ? trans('operations::app.suppliers.edit') : trans('operations::app.suppliers.create') }}
    </x-slot>

    <form
        method="POST"
        action="{{ $supplier->exists ? route('admin.operations.suppliers.update', $supplier->id) : route('admin.operations.suppliers.store') }}"
    >
        @csrf

        @if ($supplier->exists)
            @method('PUT')
        @endif

        <x-operations::header
            :title="$supplier->exists ? $supplier->name : trans('operations::app.suppliers.create')"
            :back="route('admin.operations.suppliers.index')"
        >
            <button type="submit" class="primary-button">@lang('operations::app.common.save')</button>
        </x-operations::header>

        <div class="mt-3.5 grid gap-2.5 lg:grid-cols-2">
            <x-operations::card :title="trans('operations::app.common.general')">
                <x-operations::field name="code" :label="trans('operations::app.suppliers.code')" :value="$supplier->code" required />
                <x-operations::field name="name" :label="trans('operations::app.suppliers.name')" :value="$supplier->name" required />
                <x-operations::field name="payment_terms" :label="trans('operations::app.suppliers.payment-terms')" :value="$supplier->payment_terms" />
                <x-operations::field name="lead_time_days" type="number" min="0" :label="trans('operations::app.suppliers.lead-time')" :value="$supplier->lead_time_days" />
                <x-operations::field
                    name="status"
                    type="select"
                    :label="trans('operations::app.common.status')"
                    :value="(int) $supplier->status"
                    :options="[1 => trans('operations::app.common.active'), 0 => trans('operations::app.common.inactive')]"
                />
            </x-operations::card>

            <x-operations::card :title="trans('operations::app.suppliers.contact')">
                <x-operations::field name="contact_name" :label="trans('operations::app.suppliers.contact-name')" :value="$supplier->contact_name" />
                <x-operations::field name="email" type="email" :label="trans('operations::app.suppliers.email')" :value="$supplier->email" />
                <x-operations::field name="phone" :label="trans('operations::app.suppliers.phone')" :value="$supplier->phone" />
                <x-operations::field name="address" type="textarea" :label="trans('operations::app.suppliers.address')" :value="$supplier->address" />
            </x-operations::card>
        </div>
    </form>
</x-admin::layouts>
