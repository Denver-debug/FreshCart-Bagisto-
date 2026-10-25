<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.promotions.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.promotions.title')">
        @if (bouncer()->hasPermission('operations.promotions.create'))
            <a href="{{ route('admin.operations.promotions.create') }}" class="primary-button">@lang('operations::app.promotions.create')</a>
        @endif
    </x-operations::header>

    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.promotions.info')</p>

    <x-admin::datagrid :src="route('admin.operations.promotions.index')" />
</x-admin::layouts>
