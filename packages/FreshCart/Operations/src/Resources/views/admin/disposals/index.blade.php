<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.disposals.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.disposals.title')">
        <a href="{{ route('admin.operations.candidates.index') }}" class="secondary-button">@lang('operations::app.disposals.review-list')</a>

        @if (bouncer()->hasPermission('operations.disposals.create'))
            <a href="{{ route('admin.operations.disposals.create') }}" class="primary-button">@lang('operations::app.disposals.create')</a>
        @endif
    </x-operations::header>

    <x-admin::datagrid :src="route('admin.operations.disposals.index')" />
</x-admin::layouts>
