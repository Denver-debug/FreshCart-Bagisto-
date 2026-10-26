<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.adjustments.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.adjustments.title')">
        @if (bouncer()->hasPermission('operations.adjustments.create'))
            <a href="{{ route('admin.operations.adjustments.create') }}" class="primary-button">@lang('operations::app.adjustments.create')</a>
        @endif
    </x-operations::header>

    <x-admin::datagrid :src="route('admin.operations.adjustments.index')" />
</x-admin::layouts>
