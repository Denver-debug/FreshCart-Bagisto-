<x-admin::layouts>
    <x-slot:title>
        {{ $batch->reference }}
    </x-slot>

    @php($isOwner = $batch->requested_by === auth()->guard('admin')->id())

    <x-operations::header :title="$batch->reference" :status="$batch->status" :back="route('admin.operations.price_changes.index')">
        @if ($batch->status === 'draft' && $isOwner)
            <x-operations::action
                :action="route('admin.operations.price_changes.submit', $batch->id)"
                :label="trans('operations::app.common.submit')"
                :confirm="trans('operations::app.common.confirm-submit')"
                variant="primary"
            />
        @endif

        @if (in_array($batch->status, ['draft', 'submitted', 'approved']) && ($isOwner || bouncer()->hasPermission('operations.price_changes.approve')))
            <x-operations::action
                :action="route('admin.operations.price_changes.cancel', $batch->id)"
                :label="trans('operations::app.common.cancel')"
                :confirm="trans('operations::app.common.confirm-cancel')"
            />
        @endif
    </x-operations::header>

    <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
        <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
            <x-operations::card :title="trans('operations::app.price-changes.lines')">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" v-pre>
                        <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.price-changes.old-price')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.price-changes.new-price')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.price-changes.change')</th>
                                <th class="px-2 py-2">@lang('operations::app.common.status')</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($batch->items as $item)
                                @php($old = (float) $item->old_price)
                                <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                    <td class="px-2 py-2 font-semibold">{{ $item->sku }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->old_price !== null ? core()->formatBasePrice($old) : '—' }}</td>
                                    <td class="px-2 py-2 text-right">{{ core()->formatBasePrice((float) $item->new_price) }}</td>
                                    <td class="px-2 py-2 text-right">{{ $old > 0 ? round(((float) $item->new_price - $old) / $old * 100, 1).'%' : '—' }}</td>
                                    <td class="px-2 py-2">
                                        {{ trans('operations::app.statuses.'.($item->status === 'pending' ? 'pending' : $item->status)) }}
                                        @if ($item->error)
                                            <span class="block text-xs text-red-600">{{ $item->error }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-operations::card>
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.promotions.name')</dt><dd>{{ $batch->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.price-changes.effective-at')</dt><dd>{{ $batch->effective_at->format('d M Y H:i') }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.requested-by')</dt><dd>{{ $batch->requester?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.approvals')</dt><dd>{{ $batch->approvals_received }} / {{ $batch->approval_level_required }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.price-changes.applied-at')</dt><dd>{{ $batch->applied_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                </dl>
            </x-operations::card>

            <x-operations::decision
                :document="$batch"
                approve-route="admin.operations.price_changes.approve"
                reject-route="admin.operations.price_changes.reject"
            />

            <x-operations::history :document="$batch" />
        </div>
    </div>
</x-admin::layouts>
