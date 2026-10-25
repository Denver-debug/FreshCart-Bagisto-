<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.supplier-returns.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.supplier-returns.title')" />

    <x-admin::datagrid :src="route('admin.operations.supplier_returns.index')" />
</x-admin::layouts>
