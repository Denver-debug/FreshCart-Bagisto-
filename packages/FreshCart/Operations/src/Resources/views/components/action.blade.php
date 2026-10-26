{{--
    A POST action button. With `confirm`, Bagisto's confirm modal must be accepted first.
--}}
@props([
    'action',
    'label',
    'confirm' => null,
    'variant' => 'secondary',
    'method' => 'POST',
])

<form
    method="POST"
    action="{{ $action }}"
    class="inline"
>
    @csrf

    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{ $slot }}

    <button
        type="{{ $confirm ? 'button' : 'submit' }}"
        class="{{ $variant === 'primary' ? 'primary-button' : 'secondary-button' }}"
        @if ($confirm)
            @click="$emitter.emit('open-confirm-modal', { message: '{{ e(addslashes($confirm)) }}', agree: () => $event.target.closest('form').requestSubmit() })"
        @endif
    >
        {{ $label }}
    </button>
</form>
