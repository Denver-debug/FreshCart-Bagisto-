<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.purchase-orders.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.purchase-orders.title')">
        @if (bouncer()->hasPermission('operations.purchase_orders.create'))
            <a href="{{ route('admin.operations.purchase_orders.create') }}" class="primary-button">
                @lang('operations::app.purchase-orders.create')
            </a>
        @endif
    </x-operations::header>

    <x-admin::datagrid :src="route('admin.operations.purchase_orders.index')" />
</x-admin::layouts>
