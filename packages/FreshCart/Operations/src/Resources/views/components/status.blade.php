@props(['status'])

<span {{ $attributes->merge(['class' => \FreshCart\Operations\Support\StatusBadge::cssClass($status)]) }}>{{ \FreshCart\Operations\Support\StatusBadge::label($status) }}</span>
