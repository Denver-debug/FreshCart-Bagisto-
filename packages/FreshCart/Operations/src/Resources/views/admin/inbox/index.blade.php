<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.inbox.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.inbox.title')">
        <a href="{{ route('admin.operations.notifications.index') }}" class="secondary-button">@lang('operations::app.notifications.title')</a>
    </x-operations::header>

    <div class="mt-3.5 grid gap-2.5 xl:grid-cols-2">
        <x-operations::card :title="trans('operations::app.inbox.pending')">
            @forelse ($pending as $document)
                <a href="{{ $document['url'] }}" class="mb-2 flex items-center justify-between gap-2 rounded border p-2 text-sm hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">
                    <span v-pre>{{ $document['label'] }}</span>
                    <span class="text-xs text-gray-500">
                        @lang('operations::app.common.level', ['level' => $document['level']]) / {{ $document['levels'] }}
                        · {{ $document['submitted_at']?->diffForHumans() }}
                    </span>
                </a>
            @empty
                <p class="text-sm text-gray-500">@lang('operations::app.inbox.nothing-pending')</p>
            @endforelse
        </x-operations::card>

        <x-operations::card :title="trans('operations::app.inbox.recent')">
            @include('operations::admin.inbox.list', ['notifications' => $notifications])
        </x-operations::card>
    </div>
</x-admin::layouts>
