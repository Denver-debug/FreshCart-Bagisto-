<x-admin::layouts>
    <x-slot:title>
        {{ $disposal->reference }}
    </x-slot>

    @php($isOwner = $disposal->requested_by === auth()->guard('admin')->id())

    <x-operations::header :title="$disposal->reference" :status="$disposal->status" :back="route('admin.operations.disposals.index')">
        <a href="{{ route('admin.operations.disposals.print', $disposal->id) }}" class="secondary-button">@lang('operations::app.common.print-pdf')</a>

        @if ($disposal->status === 'draft' && $isOwner)
            <x-operations::action
                :action="route('admin.operations.disposals.submit', $disposal->id)"
                :label="trans('operations::app.common.submit')"
                :confirm="trans('operations::app.common.confirm-submit')"
                variant="primary"
            />
        @endif

        @if (in_array($disposal->status, ['draft', 'submitted', 'inspected']) && ($isOwner || bouncer()->hasPermission('operations.disposals.approve')))
            <x-operations::action
                :action="route('admin.operations.disposals.cancel', $disposal->id)"
                :label="trans('operations::app.common.cancel')"
                :confirm="trans('operations::app.common.confirm-cancel')"
            />
        @endif

        @if ($disposal->status === 'approved' && bouncer()->hasPermission('operations.disposals.execute'))
            <x-operations::action
                :action="route('admin.operations.disposals.segregate', $disposal->id)"
                :label="trans('operations::app.disposals.segregate')"
                :confirm="trans('operations::app.disposals.confirm-segregate')"
                variant="primary"
            />
        @endif
    </x-operations::header>

    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.disposals.workflow')</p>

    <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
        <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
            <form method="POST" action="{{ route('admin.operations.disposals.value', $disposal->id) }}" id="fc-valuation-form">
                @csrf

                <x-operations::card :title="trans('operations::app.common.items')">
                    @php($canValue = $disposal->status === 'inspected' && bouncer()->hasPermission('operations.disposals.value'))

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                                <tr>
                                    <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                                    <th class="px-2 py-2">@lang('operations::app.common.batch')</th>
                                    <th class="px-2 py-2 text-right">@lang('operations::app.common.qty')</th>
                                    <th class="px-2 py-2">@lang('operations::app.adjustments.source-bucket')</th>
                                    <th class="px-2 py-2">@lang('operations::app.common.reason')</th>
                                    <th class="px-2 py-2">@lang('operations::app.disposals.disposition')</th>
                                    <th class="px-2 py-2 text-right">@lang('operations::app.common.unit-cost')</th>
                                    <th class="px-2 py-2 text-right">@lang('operations::app.common.value')</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($disposal->items as $item)
                                    <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                        <td class="px-2 py-2 font-semibold" v-pre>{{ $item->sku }}<span class="block text-xs font-normal text-gray-500">{{ $item->name }}</span></td>
                                        <td class="px-2 py-2" v-pre>{{ $item->batch_no ?? '—' }}<span class="block text-xs text-gray-500">{{ $item->expiry_date?->toDateString() }}</span></td>
                                        <td class="px-2 py-2 text-right">{{ $item->qty }}</td>
                                        <td class="px-2 py-2">@lang('operations::app.buckets.'.$item->source_bucket)</td>
                                        <td class="px-2 py-2">@lang('operations::app.disposals.reasons.'.$item->reason)</td>
                                        <td class="px-2 py-2">@lang('operations::app.disposals.dispositions.'.$item->disposition)</td>
                                        <td class="px-2 py-2 text-right">
                                            @if ($canValue)
                                                <input type="number" step="0.0001" min="0" name="unit_costs[{{ $item->id }}]" value="{{ $item->unit_cost }}" class="w-28 rounded-md border px-2 py-1.5 dark:border-gray-800 dark:bg-gray-900" aria-label="@lang('operations::app.common.unit-cost')">
                                            @else
                                                {{ core()->formatBasePrice((float) $item->unit_cost) }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-right">{{ core()->formatBasePrice((float) $item->line_value) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>

                            <tfoot>
                                <tr>
                                    <td colspan="7" class="px-2 py-2 text-right font-semibold dark:text-white">@lang('operations::app.common.total')</td>
                                    <td class="px-2 py-2 text-right font-semibold dark:text-white">{{ core()->formatBasePrice((float) $disposal->total_value) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if ($canValue)
                        <div class="mt-3">
                            <x-operations::field name="valuation_remarks" type="textarea" :label="trans('operations::app.disposals.valuation-remarks')" />

                            <button type="submit" class="primary-button">@lang('operations::app.disposals.confirm-valuation')</button>
                        </div>
                    @endif
                </x-operations::card>
            </form>

            @if ($disposal->status === 'submitted' && bouncer()->hasPermission('operations.disposals.inspect'))
                <x-operations::card :title="trans('operations::app.disposals.inspection')">
                    <form method="POST" action="{{ route('admin.operations.disposals.inspect', $disposal->id) }}">
                        @csrf

                        <x-operations::field name="inspection_remarks" type="textarea" :label="trans('operations::app.disposals.inspection-remarks')" required />

                        <button type="submit" class="primary-button">@lang('operations::app.disposals.confirm-inspection')</button>
                    </form>
                </x-operations::card>
            @endif

            @if ($disposal->status === 'quarantined' && bouncer()->hasPermission('operations.disposals.execute'))
                <x-operations::card :title="trans('operations::app.disposals.complete')">
                    <form method="POST" action="{{ route('admin.operations.disposals.complete', $disposal->id) }}" enctype="multipart/form-data" id="fc-complete-form">
                        @csrf

                        <div class="grid gap-x-3 sm:grid-cols-2">
                            <x-operations::field name="disposal_method" :label="trans('operations::app.disposals.method')" :placeholder="trans('operations::app.disposals.method-placeholder')" required />
                            <x-operations::field name="supplier_id" type="select" :label="trans('operations::app.common.supplier')" :options="$suppliers" :value="$disposal->supplier_id" :placeholder="trans('operations::app.common.select')" />
                        </div>

                        <x-operations::field name="completion_remarks" type="textarea" :label="trans('operations::app.common.remarks')" />

                        <label class="mb-1.5 block text-xs font-medium text-gray-800 dark:text-white required" for="fc-evidence">@lang('operations::app.disposals.evidence')</label>
                        <input id="fc-evidence" type="file" name="evidence[]" multiple accept="image/*,application/pdf" capture="environment" class="text-sm dark:text-gray-300" required>
                        <x-operations::error name="evidence" />
                        <x-operations::error name="evidence.0" />

                        <div class="mt-3">
                            <button
                                type="button"
                                class="primary-button"
                                @click="$emitter.emit('open-confirm-modal', { message: '{{ e(addslashes(trans('operations::app.disposals.confirm-complete'))) }}', agree: () => document.getElementById('fc-complete-form').requestSubmit() })"
                            >
                                @lang('operations::app.disposals.complete')
                            </button>
                        </div>
                    </form>
                </x-operations::card>
            @endif

            @if ($disposal->attachments->isNotEmpty())
                <x-operations::card :title="trans('operations::app.disposals.evidence')">
                    <ul class="grid gap-1 text-sm">
                        @foreach ($disposal->attachments as $file)
                            <li><a class="text-blue-600 hover:underline" href="{{ route('admin.operations.disposals.evidence', [$disposal->id, $file->id]) }}" target="_blank" rel="noopener" v-pre>{{ $file->original_name }}</a></li>
                        @endforeach
                    </ul>
                </x-operations::card>
            @endif
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.location')</dt><dd>{{ $disposal->location?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.requested-by')</dt><dd>{{ $disposal->requester?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.reason')</dt><dd class="whitespace-pre-line">{{ $disposal->reason }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.disposals.inspection-remarks')</dt><dd class="whitespace-pre-line">{{ $disposal->inspection_remarks ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.disposals.valuation-remarks')</dt><dd class="whitespace-pre-line">{{ $disposal->valuation_remarks ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.approvals')</dt><dd>{{ $disposal->approvals_received }} / {{ $disposal->approval_level_required }}</dd></div>
                    @if ($disposal->completed_at)
                        <div><dt class="text-xs text-gray-500">@lang('operations::app.disposals.method')</dt><dd>{{ $disposal->disposal_method }} · {{ $disposal->completed_at->format('d M Y H:i') }}</dd></div>
                    @endif
                    @if ($disposal->supplierReturn)
                        <div><dt class="text-xs text-gray-500">@lang('operations::app.supplier-returns.title')</dt><dd>{{ $disposal->supplierReturn->reference }}</dd></div>
                    @endif
                </dl>
            </x-operations::card>

            <x-operations::decision
                :document="$disposal"
                approve-route="admin.operations.disposals.approve"
                reject-route="admin.operations.disposals.reject"
                pending-status="valued"
            />

            <x-operations::history :document="$disposal" />
        </div>
    </div>
</x-admin::layouts>
