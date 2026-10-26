<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.incidents.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.incidents.title')">
        @if (bouncer()->hasPermission('operations.incidents.create'))
            <a href="{{ route('admin.operations.incidents.create') }}" class="primary-button">@lang('operations::app.incidents.create')</a>
        @endif
    </x-operations::header>

    <x-admin::datagrid :src="route('admin.operations.incidents.index')" />
</x-admin::layouts>
