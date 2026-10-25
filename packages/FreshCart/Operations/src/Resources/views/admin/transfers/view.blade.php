<x-admin::layouts>
    <x-slot:title>
        {{ $transfer->reference }}
    </x-slot>

    @php
        $isOwner = $transfer->requested_by === auth()->guard('admin')->id();
        $receivable = in_array($transfer->status, \FreshCart\Operations\Models\Transfer::RECEIVABLE_STATUSES);
        $open = in_array($transfer->status, \FreshCart\Operations\Services\TransferService::OPEN_STATUSES);
    @endphp

    <x-operations::header :title="$transfer->reference" :status="$transfer->status" :back="route('admin.operations.transfers.index')">
        <a href="{{ route('admin.operations.transfers.print', $transfer->id) }}" class="secondary-button">@lang('operations::app.transfers.print')</a>

        @if ($transfer->status === 'draft' && $isOwner)
            <x-operations::action
                :action="route('admin.operations.transfers.submit', $transfer->id)"
                :label="trans('operations::app.common.submit')"
                :confirm="trans('operations::app.common.confirm-submit')"
                variant="primary"
            />
        @endif

        @if ($open && ($isOwner || bouncer()->hasPermission('operations.transfers.approve')))
            <x-operations::action
                :action="route('admin.operations.transfers.cancel', $transfer->id)"
                :label="trans('operations::app.common.cancel')"
                :confirm="trans('operations::app.common.confirm-cancel')"
            />
        @endif

        @if ($transfer->status === 'picking' && $canAtSource && bouncer()->hasPermission('operations.transfers.dispatch'))
            <x-operations::action
                :action="route('admin.operations.transfers.dispatch', $transfer->id)"
                :label="trans('operations::app.transfers.dispatch')"
                :confirm="trans('operations::app.transfers.confirm-dispatch')"
                variant="primary"
            >
                <x-operations::idempotency />
            </x-operations::action>
        @endif

        @if ($transfer->isTemporary() && in_array($transfer->status, ['received', 'partially_received']) && bouncer()->hasPermission('operations.transfers.create'))
            <x-operations::action
                :action="route('admin.operations.transfers.return', $transfer->id)"
                :label="trans('operations::app.transfers.create-return')"
                :confirm="trans('operations::app.transfers.confirm-return')"
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
                                <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.requested')</th>
                                @if ($open)
                                    <th class="px-2 py-2 text-right">@lang('operations::app.transfers.transferable')</th>
                                @endif
                                <th class="px-2 py-2 text-right">@lang('operations::app.transfers.picked-qty')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.stock-requests.dispatched')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.common.received')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.transfers.damaged')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.buckets.in_transit')</th>
                                <th class="px-2 py-2 text-right">@lang('operations::app.transfers.shortage')</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($transfer->items as $item)
                                <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                    <td class="px-2 py-2 font-semibold">{{ $item->sku }}</td>
                                    <td class="px-2 py-2">{{ $item->name }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_requested }}</td>
                                    @if ($open)
                                        <td class="px-2 py-2 text-right {{ $available[$item->id] < $item->qty_requested ? 'text-red-600' : '' }}">{{ $available[$item->id] }}</td>
                                    @endif
                                    <td class="px-2 py-2 text-right">{{ $item->qty_picked }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_dispatched }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_received }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_damaged }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->inTransitQty() }}</td>
                                    <td class="px-2 py-2 text-right">{{ $item->qty_written_off }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-operations::card>

            {{-- Warehouse: pick & pack --}}
            @if (in_array($transfer->status, ['approved', 'picking']) && $canAtSource && bouncer()->hasPermission('operations.transfers.pick'))
                <x-operations::card :title="trans('operations::app.transfers.pick-title')">
                    <form method="POST" action="{{ route('admin.operations.transfers.pick', $transfer->id) }}">
                        @csrf

                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($transfer->items as $item)
                                <label class="flex items-center justify-between gap-2 rounded border p-2 text-sm dark:border-gray-800 dark:text-gray-300">
                                    <span v-pre>{{ $item->sku }} <span class="text-xs text-gray-500">({{ $item->qty_requested }})</span></span>
                                    <input type="number" min="0" max="{{ $item->qty_requested }}" name="picked[{{ $item->id }}]" value="{{ old('picked.'.$item->id, $item->qty_picked ?: $item->qty_requested) }}" class="w-24 rounded-md border px-2 py-1.5 dark:border-gray-800 dark:bg-gray-900">
                                </label>
                            @endforeach
                        </div>

                        <button type="submit" class="secondary-button mt-3">@lang('operations::app.transfers.save-picking')</button>
                    </form>
                </x-operations::card>
            @endif

            {{-- Destination: receipt --}}
            @if ($receivable && $canAtDestination && bouncer()->hasPermission('operations.transfers.receive'))
                <x-operations::card :title="trans('operations::app.transfers.receive-title')">
                    <form method="POST" action="{{ route('admin.operations.transfers.receive', $transfer->id) }}" enctype="multipart/form-data" id="fc-transfer-receive">
                        @csrf

                        <x-operations::idempotency />

                        <div class="grid gap-2">
                            @foreach ($transfer->items as $item)
                                @continue($item->inTransitQty() === 0)

                                <div class="grid items-end gap-2 rounded border p-2 text-sm sm:grid-cols-4 dark:border-gray-800 dark:text-gray-300">
                                    <p class="font-semibold sm:col-span-1" v-pre>{{ $item->sku }}<span class="block text-xs font-normal text-gray-500">@lang('operations::app.buckets.in_transit'): {{ $item->inTransitQty() }}</span></p>

                                    <x-operations::field class="!mb-0" name="lines[{{ $item->id }}][qty_received]" type="number" min="0" :label="trans('operations::app.transfers.good')" :value="$item->inTransitQty()" />
                                    <x-operations::field class="!mb-0" name="lines[{{ $item->id }}][qty_damaged]" type="number" min="0" :label="trans('operations::app.transfers.damaged')" :value="0" />
                                    <x-operations::field class="!mb-0" name="lines[{{ $item->id }}][remarks]" :label="trans('operations::app.common.remarks')" />
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3 grid gap-x-3 sm:grid-cols-2">
                            <x-operations::field name="receiver_name" :label="trans('operations::app.transfers.receiver-name')" :value="auth()->guard('admin')->user()->name" required />
                            <x-operations::field name="remarks" :label="trans('operations::app.common.remarks')" />
                        </div>

                        <label class="mb-1.5 block text-xs font-medium text-gray-800 dark:text-white" for="fc-proof">@lang('operations::app.transfers.proof')</label>
                        <input id="fc-proof" type="file" name="proof" accept="image/*,application/pdf" capture="environment" class="text-sm dark:text-gray-300">
                        <x-operations::error name="proof" />

                        <div class="mt-3">
                            <button
                                type="button"
                                class="primary-button"
                                @click="$emitter.emit('open-confirm-modal', { message: '{{ e(addslashes(trans('operations::app.transfers.confirm-receive'))) }}', agree: () => document.getElementById('fc-transfer-receive').requestSubmit() })"
                            >
                                @lang('operations::app.transfers.confirm-receipt')
                            </button>
                        </div>
                    </form>
                </x-operations::card>
            @endif

            @if ($receivable && bouncer()->hasPermission('operations.transfers.close'))
                <x-operations::card :title="trans('operations::app.transfers.close-title')">
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.transfers.close-info')</p>

                    <form method="POST" action="{{ route('admin.operations.transfers.close', $transfer->id) }}">
                        @csrf

                        <x-operations::field name="remarks" type="textarea" :label="trans('operations::app.common.remarks')" required />

                        <button type="submit" class="secondary-button">@lang('operations::app.transfers.close-with-discrepancy')</button>
                    </form>
                </x-operations::card>
            @endif

            @if ($transfer->receipts->isNotEmpty())
                <x-operations::card :title="trans('operations::app.transfers.receipts')">
                    @foreach ($transfer->receipts as $receipt)
                        <div class="mb-2 border-b pb-2 text-sm last:border-0 dark:border-gray-800 dark:text-gray-300" v-pre>
                            <p class="font-semibold">{{ core()->formatDate($receipt->received_at, 'd M Y H:i') }} · {{ $receipt->receiver_name }} ({{ $receipt->receiver?->name }})</p>

                            @foreach ($receipt->items as $line)
                                <p class="text-xs">{{ $transfer->items->firstWhere('id', $line->transfer_item_id)?->sku }}: +{{ $line->qty_received }} @lang('operations::app.transfers.good'), {{ $line->qty_damaged }} @lang('operations::app.transfers.damaged') {{ $line->remarks }}</p>
                            @endforeach

                            @if ($receipt->remarks)
                                <p class="text-xs text-gray-500">{{ $receipt->remarks }}</p>
                            @endif

                            @foreach ($receipt->attachments as $attachment)
                                <p class="text-xs">📎 {{ $attachment->original_name }}</p>
                            @endforeach
                        </div>
                    @endforeach
                </x-operations::card>
            @endif
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.transfers.type')</dt><dd>{{ trans('operations::app.transfers.types.'.$transfer->type) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.source')</dt><dd>{{ $transfer->sourceLocation?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.destination')</dt><dd>{{ $transfer->destinationLocation?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.priority')</dt><dd>{{ trans('operations::app.priorities.'.$transfer->priority) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.requested-by')</dt><dd>{{ $transfer->requester?->name ?? '—' }}</dd></div>
                    @if ($transfer->stockRequest)
                        <div><dt class="text-xs text-gray-500">@lang('operations::app.stock-requests.title')</dt><dd>{{ $transfer->stockRequest->reference }}</dd></div>
                    @endif
                    @if ($transfer->expected_return_date)
                        <div><dt class="text-xs text-gray-500">@lang('operations::app.transfers.expected-return')</dt><dd>{{ $transfer->expected_return_date->toDateString() }}</dd></div>
                    @endif
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.reason')</dt><dd class="whitespace-pre-line">{{ $transfer->reason ?: '—' }}</dd></div>
                </dl>
            </x-operations::card>

            <x-operations::card :title="trans('operations::app.transfers.logistics')">
                <dl class="mb-3 grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.transfers.scheduled-dispatch')</dt><dd>{{ $transfer->scheduled_dispatch_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.transfers.expected-arrival')</dt><dd>{{ $transfer->expected_arrival_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.transfers.assigned-staff')</dt><dd>{{ $transfer->assignedStaff?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.transfers.driver')</dt><dd>{{ $transfer->driver_name ?? '—' }} {{ $transfer->vehicle ? '· '.$transfer->vehicle : '' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.transfers.dispatched-at')</dt><dd>{{ $transfer->dispatched_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                </dl>

                @if (in_array($transfer->status, ['approved', 'picking', 'in_transit']) && bouncer()->hasPermission('operations.transfers.schedule'))
                    <form method="POST" action="{{ route('admin.operations.transfers.schedule', $transfer->id) }}">
                        @csrf

                        <x-operations::field name="scheduled_dispatch_at" type="datetime-local" :label="trans('operations::app.transfers.scheduled-dispatch')" :value="$transfer->scheduled_dispatch_at?->format('Y-m-d\TH:i')" />
                        <x-operations::field name="expected_arrival_at" type="datetime-local" :label="trans('operations::app.transfers.expected-arrival')" :value="$transfer->expected_arrival_at?->format('Y-m-d\TH:i')" />
                        <x-operations::field name="assigned_staff_id" type="select" :label="trans('operations::app.transfers.assigned-staff')" :options="$staff" :value="$transfer->assigned_staff_id" :placeholder="trans('operations::app.common.select')" />
                        <x-operations::field name="driver_name" :label="trans('operations::app.transfers.driver')" :value="$transfer->driver_name" />
                        <x-operations::field name="vehicle" :label="trans('operations::app.transfers.vehicle')" :value="$transfer->vehicle" />

                        <button type="submit" class="secondary-button">@lang('operations::app.transfers.save-schedule')</button>
                    </form>
                @endif
            </x-operations::card>

            <x-operations::decision
                :document="$transfer"
                approve-route="admin.operations.transfers.approve"
                reject-route="admin.operations.transfers.reject"
                pending-status="pending_approval"
            />

            <x-operations::history :document="$transfer" />
        </div>
    </div>
</x-admin::layouts>
