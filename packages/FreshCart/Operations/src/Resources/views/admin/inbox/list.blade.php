@forelse ($notifications as $notification)
    <a
        href="{{ route('admin.operations.notifications.open', $notification->id) }}"
        class="mb-2 block rounded border p-2 text-sm hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800 {{ $notification->read_at ? 'text-gray-500' : 'font-semibold text-gray-800 dark:text-white' }}"
    >
        <span v-pre>{{ $notification->title }}</span>
        <span class="block text-xs font-normal text-gray-500">{{ $notification->created_at?->diffForHumans() }}</span>
    </a>
@empty
    <p class="text-sm text-gray-500">@lang('operations::app.notifications.empty')</p>
@endforelse
