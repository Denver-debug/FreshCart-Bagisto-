<x-admin::layouts>
    <x-slot:title>
        {{ $incident->reference }}
    </x-slot>

    <x-operations::header :title="$incident->reference" :status="$incident->status" :back="route('admin.operations.incidents.index')" />

    <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
        <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
            <x-operations::card :title="trans('operations::app.incidents.details')">
                <dl class="grid gap-3 text-sm sm:grid-cols-2 dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.product')</dt><dd class="font-semibold">{{ $incident->sku }} — {{ $incident->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.qty')</dt><dd>{{ $incident->qty }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.incidents.type')</dt><dd>{{ trans('operations::app.incidents.types.'.$incident->incident_type) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.location')</dt><dd>{{ $incident->location?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.batch')</dt><dd>{{ $incident->batch_no ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.expiry')</dt><dd>{{ $incident->expiry_date?->toDateString() ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.incidents.reported-by')</dt><dd>{{ $incident->reporter?->name }} · {{ core()->formatDate($incident->created_at, 'd M Y H:i') }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.incidents.stock-state')</dt><dd>{{ $incident->from_stock ? trans('operations::app.incidents.from-stock') : trans('operations::app.incidents.returned-in') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">@lang('operations::app.incidents.description')</dt><dd class="whitespace-pre-line">{{ $incident->description }}</dd></div>
                </dl>

                @if ($incident->attachments->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($incident->attachments as $photo)
                            <a href="{{ route('admin.operations.incidents.photo', [$incident->id, $photo->id]) }}" target="_blank" rel="noopener">
                                <img src="{{ route('admin.operations.incidents.photo', [$incident->id, $photo->id]) }}" alt="{{ $photo->original_name }}" class="h-24 w-24 rounded border object-cover dark:border-gray-800">
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-operations::card>

            @if (in_array($incident->status, ['quarantined', 'inspected']) && bouncer()->hasPermission('operations.incidents.inspect'))
                <x-operations::card :title="trans('operations::app.incidents.inspection')">
                    <form method="POST" action="{{ route('admin.operations.incidents.inspect', $incident->id) }}">
                        @csrf

                        <div class="mb-3 grid gap-2 sm:grid-cols-2">
                            @foreach (\FreshCart\Operations\Models\Incident::CHECKLIST as $check)
                                <label class="flex items-center gap-2 text-sm dark:text-gray-300">
                                    <input type="hidden" name="checklist[{{ $check }}]" value="0">
                                    <input type="checkbox" name="checklist[{{ $check }}]" value="1" @checked($incident->checklist[$check] ?? false)>
                                    @lang('operations::app.incidents.checks.'.$check)
                                </label>
                            @endforeach
                        </div>

                        <x-operations::field name="inspection_remarks" type="textarea" :label="trans('operations::app.incidents.inspection-remarks')" :value="$incident->inspection_remarks" required />

                        <button type="submit" class="secondary-button">@lang('operations::app.incidents.save-inspection')</button>
                    </form>
                </x-operations::card>
            @endif

            @if ($incident->status === 'inspected' && bouncer()->hasPermission('operations.incidents.classify'))
                <x-operations::card :title="trans('operations::app.incidents.classify')" class="border border-blue-200 dark:border-blue-900">
                    <form method="POST" action="{{ route('admin.operations.incidents.classify', $incident->id) }}" id="fc-classify-form">
                        @csrf

                        <x-operations::field
                            name="classification"
                            type="select"
                            :label="trans('operations::app.incidents.classification')"
                            :options="collect(\FreshCart\Operations\Models\Incident::CLASSIFICATIONS)->mapWithKeys(fn ($c) => [$c => trans('operations::app.incidents.classifications.'.$c)])->all()"
                            required
                        />

                        <x-operations::field name="supplier_id" type="select" :label="trans('operations::app.incidents.return-supplier')" :options="$suppliers" :value="$incident->supplier_id" :placeholder="trans('operations::app.common.select')" />

                        <x-operations::field name="remarks" type="textarea" :label="trans('operations::app.common.remarks')" />

                        <p class="mb-3 text-xs text-gray-500">@lang('operations::app.incidents.classify-info')</p>

                        <button
                            type="button"
                            class="primary-button"
                            @click="$emitter.emit('open-confirm-modal', { message: '{{ e(addslashes(trans('operations::app.incidents.confirm-classify'))) }}', agree: () => document.getElementById('fc-classify-form').requestSubmit() })"
                        >
                            @lang('operations::app.incidents.classify')
                        </button>
                    </form>
                </x-operations::card>
            @endif

            @if ($incident->classification)
                <x-operations::card :title="trans('operations::app.incidents.disposition')">
                    <p class="text-sm dark:text-gray-300" v-pre>
                        <strong>{{ trans('operations::app.incidents.classifications.'.$incident->classification) }}</strong>
                        · {{ $incident->classifier?->name }} · {{ $incident->classified_at?->format('d M Y H:i') }}
                    </p>

                    @if ($incident->adjustment)
                        <p class="mt-2 text-sm">
                            <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.adjustments.view', $incident->adjustment_id) }}">{{ $incident->adjustment->reference }}</a>
                            <x-operations::status :status="$incident->adjustment->status" />
                        </p>
                    @endif

                    @if ($incident->supplierReturn)
                        <p class="mt-2 text-sm">
                            <a class="text-blue-600 hover:underline" href="{{ route('admin.operations.supplier_returns.view', $incident->supplier_return_id) }}">{{ $incident->supplierReturn->reference }}</a>
                            <x-operations::status :status="$incident->supplierReturn->status" />
                        </p>
                    @endif
                </x-operations::card>
            @endif
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            @if ($incident->checklist)
                <x-operations::card :title="trans('operations::app.incidents.inspection')">
                    <ul class="grid gap-1 text-sm dark:text-gray-300">
                        @foreach ($incident->checklist as $check => $ok)
                            <li>{{ $ok ? '✔' : '✘' }} @lang('operations::app.incidents.checks.'.$check)</li>
                        @endforeach
                    </ul>

                    <p class="mt-2 text-xs text-gray-500" v-pre>{{ $incident->inspector?->name }} · {{ $incident->inspected_at?->format('d M Y H:i') }}</p>
                </x-operations::card>
            @endif

            <x-operations::history :document="$incident" />
        </div>
    </div>
</x-admin::layouts>
