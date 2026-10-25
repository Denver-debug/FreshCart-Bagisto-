<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.suppliers.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.suppliers.title')">
        @if (bouncer()->hasPermission('operations.setup.suppliers.create'))
            <a href="{{ route('admin.operations.suppliers.create') }}" class="primary-button">
                @lang('operations::app.suppliers.create')
            </a>
        @endif
    </x-operations::header>

    <x-admin::datagrid :src="route('admin.operations.suppliers.index')" />
</x-admin::layouts>
