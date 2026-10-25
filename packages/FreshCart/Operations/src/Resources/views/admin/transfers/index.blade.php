<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.transfers.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.transfers.title')">
        @if (bouncer()->hasPermission('operations.transfers.create'))
            <a href="{{ route('admin.operations.transfers.create') }}" class="primary-button">
                @lang('operations::app.transfers.create')
            </a>
        @endif
    </x-operations::header>

    <x-admin::datagrid :src="route('admin.operations.transfers.index')" />
</x-admin::layouts>
