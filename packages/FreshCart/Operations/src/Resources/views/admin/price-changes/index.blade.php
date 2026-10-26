<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.price-changes.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.price-changes.title')">
        @if (bouncer()->hasPermission('operations.price_changes.create'))
            <a href="{{ route('admin.operations.price_changes.create') }}" class="primary-button">@lang('operations::app.price-changes.create')</a>
        @endif
    </x-operations::header>

    <x-admin::datagrid :src="route('admin.operations.price_changes.index')" />
</x-admin::layouts>
