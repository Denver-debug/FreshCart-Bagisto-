{{-- One key per rendered form: replaying the same submission is rejected by a unique index. --}}
<input
    type="hidden"
    name="idempotency_key"
    value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}"
>
