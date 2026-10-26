@props(['title' => null])

<div {{ $attributes->merge(['class' => 'box-shadow rounded bg-white p-4 dark:bg-gray-900']) }}>
    @if ($title)
        <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">{{ $title }}</p>
    @endif

    {{ $slot }}
</div>
