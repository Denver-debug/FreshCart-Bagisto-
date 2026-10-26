@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'options' => [],
    'placeholder' => null,
    'help' => null,
])

@php
    $id = 'fc_'.str_replace(['[', ']', '.'], '_', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($errorKey, $value);
    $inputClass = 'w-full rounded-md border px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400';

    if ($errors->has($errorKey)) {
        $inputClass .= ' !border-red-600';
    }
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'mb-4']) }}>
    @if ($label)
        <label
            for="{{ $id }}"
            class="mb-1.5 flex items-center gap-1 text-xs font-medium text-gray-800 dark:text-white {{ $required ? 'required' : '' }}"
        >
            {{ $label }}
        </label>
    @endif

    @switch($type)
        @case('textarea')
            <textarea v-pre
                id="{{ $id }}"
                name="{{ $name }}"
                rows="3"
                class="{{ $inputClass }}"
                placeholder="{{ $placeholder }}"
                @if ($required) required @endif
                {{ $attributes->except(['class']) }}
            >{{ $current }}</textarea>

            @break

        @case('select')
            <select v-pre
                id="{{ $id }}"
                name="{{ $name }}"
                class="{{ $inputClass }} custom-select"
                @if ($required) required @endif
                {{ $attributes->except(['class']) }}
            >
                @if ($placeholder)
                    <option value="">{{ $placeholder }}</option>
                @endif

                @foreach ($options as $optionValue => $optionLabel)
                    <option
                        value="{{ $optionValue }}"
                        @selected((string) $current === (string) $optionValue)
                    >
                        {{ $optionLabel }}
                    </option>
                @endforeach
            </select>

            @break

        @case('checkbox')
            <input type="hidden" name="{{ $name }}" value="0">

            <input
                type="checkbox"
                id="{{ $id }}"
                name="{{ $name }}"
                value="1"
                class="h-4 w-4"
                @checked((bool) $current)
                {{ $attributes->except(['class']) }}
            />

            @break

        @default
            <input
                type="{{ $type }}"
                id="{{ $id }}"
                name="{{ $name }}"
                value="{{ $current }}"
                class="{{ $inputClass }}"
                placeholder="{{ $placeholder }}"
                @if ($required) required @endif
                {{ $attributes->except(['class']) }}
            />
    @endswitch

    @if ($help)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @endif

    <x-operations::error :name="$errorKey" />
</div>
