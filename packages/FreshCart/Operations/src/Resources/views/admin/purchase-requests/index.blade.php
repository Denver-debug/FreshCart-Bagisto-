<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.purchase-requests.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.purchase-requests.title')">
        @if (bouncer()->hasPermission('operations.purchase_requests.create'))
            <a href="{{ route('admin.operations.purchase_requests.create') }}" class="primary-button">
                @lang('operations::app.purchase-requests.create')
            </a>
        @endif
    </x-operations::header>

    <x-admin::datagrid :src="route('admin.operations.purchase_requests.index')" />
</x-admin::layouts>
