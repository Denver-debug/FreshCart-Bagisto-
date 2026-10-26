<x-admin::layouts>
    <x-slot:title>
        {{ $adjustment->reference }}
    </x-slot>

    @php($isOwner = $adjustment->requested_by === auth()->guard('admin')->id())

    <x-operations::header :title="$adjustment->reference" :status="$adjustment->status" :back="route('admin.operations.adjustments.index')">
        @if ($adjustment->status === 'draft' && $isOwner)
            <x-operations::action
                :action="route('admin.operations.adjustments.submit', $adjustment->id)"
                :label="trans('operations::app.common.submit')"
                :confirm="trans('operations::app.common.confirm-submit')"
                variant="primary"
            />
        @endif

        @if (in_array($adjustment->status, ['draft', 'submitted']) && $isOwner)
            <x-operations::action
                :action="route('admin.operations.adjustments.cancel', $adjustment->id)"
                :label="trans('operations::app.common.cancel')"
                :confirm="trans('operations::app.common.confirm-cancel')"
            />
        @endif

        @if ($adjustment->status === 'approved' && bouncer()->hasPermission('operations.adjustments.post'))
            <x-operations::action
                :action="route('admin.operations.adjustments.post', $adjustment->id)"
                :label="trans('operations::app.adjustments.post')"
                :confirm="trans('operations::app.adjustments.confirm-post')"
                variant="primary"
            />
        @endif
    </x-operations::header>

    <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
        <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
            <x-operations::card :title="trans('operations::app.common.items')">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" v-pre>
                        <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                                <th class="px-2 py-2">@lang('operations::app.common.product')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.qty')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.unit-cost')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.value')</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($adjustment->items as $item)
                                <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                    <td class="px-2 py-2 font-semibold">{{ $item->sku }}</td>
                                    <td class="px-2 py-2">{{ $item->name }}</td>
                                    <td class="px-2 py-2 text-right">{{ $adjustment->direction === 'out' ? '−' : '+' }}{{ $item->qty }}</td>
                                    <td class="px-2 py-2 text-right">{{ core()->formatBasePrice((float) $item->unit_cost) }}</td>
                                    <td class="px-2 py-2 text-right">{{ core()->formatBasePrice((float) $item->line_value) }}</td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr>
                                <td colspan="4" class="px-2 py-2 text-right font-semibold dark:text-white">@lang('operations::app.common.total')</td>
                                <td class="px-2 py-2 text-right font-semibold dark:text-white">{{ core()->formatBasePrice((float) $adjustment->total_value) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-operations::card>
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.location')</dt><dd>{{ $adjustment->location?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.adjustments.direction')</dt><dd>{{ trans('operations::app.adjustments.directions.'.$adjustment->direction) }}{{ $adjustment->source_bucket ? ' · '.trans('operations::app.buckets.'.$adjustment->source_bucket) : '' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.reason')</dt><dd>{{ trans('operations::app.adjustments.reasons.'.$adjustment->reason_code) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.adjustments.explanation')</dt><dd class="whitespace-pre-line">{{ $adjustment->reason }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.requested-by')</dt><dd>{{ $adjustment->requester?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.approvals')</dt><dd>{{ $adjustment->approvals_received }} / {{ $adjustment->approval_level_required }}</dd></div>
                    @if ($adjustment->posted_at)
                        <div><dt class="text-xs text-gray-500">@lang('operations::app.adjustments.posted-by')</dt><dd>{{ $adjustment->poster?->name }} · {{ $adjustment->posted_at->format('d M Y H:i') }}</dd></div>
                    @endif
                    @if ($adjustment->origin)
                        <div><dt class="text-xs text-gray-500">@lang('operations::app.adjustments.source-document')</dt><dd>{{ $adjustment->origin->reference }}</dd></div>
                    @endif
                </dl>
            </x-operations::card>

            <x-operations::decision
                :document="$adjustment"
                approve-route="admin.operations.adjustments.approve"
                reject-route="admin.operations.adjustments.reject"
            />

            <x-operations::history :document="$adjustment" />
        </div>
    </div>
</x-admin::layouts>
