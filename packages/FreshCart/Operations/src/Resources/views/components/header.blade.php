@props(['title', 'back' => null, 'status' => null])

<div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
    <div class="flex items-center gap-2.5">
        <p class="text-xl font-bold text-gray-800 dark:text-white" v-pre>{{ $title }}</p>

        @if ($status)
            <x-operations::status :status="$status" />
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-2">
        @if ($back)
            <a
                href="{{ $back }}"
                class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
            >
                @lang('operations::app.common.back')
            </a>
        @endif

        {{ $slot }}
    </div>
</div>
