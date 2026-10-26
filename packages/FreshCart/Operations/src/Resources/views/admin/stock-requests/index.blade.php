<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.stock-requests.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.stock-requests.title')">
        @if (bouncer()->hasPermission('operations.stock_requests.create'))
            <a href="{{ route('admin.operations.stock_requests.create') }}" class="primary-button">
                @lang('operations::app.stock-requests.create')
            </a>
        @endif
    </x-operations::header>

    <x-admin::datagrid :src="route('admin.operations.stock_requests.index')" />
</x-admin::layouts>
