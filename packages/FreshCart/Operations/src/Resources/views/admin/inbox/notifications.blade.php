<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.notifications.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.notifications.title')" :back="route('admin.operations.approvals.index')">
        <x-operations::action :action="route('admin.operations.notifications.read_all')" :label="trans('operations::app.notifications.mark-all-read')" />
    </x-operations::header>

    <x-operations::card class="mt-3.5">
        @include('operations::admin.inbox.list', ['notifications' => $notifications])

        <div class="mt-3">{{ $notifications->links() }}</div>
    </x-operations::card>
</x-admin::layouts>
