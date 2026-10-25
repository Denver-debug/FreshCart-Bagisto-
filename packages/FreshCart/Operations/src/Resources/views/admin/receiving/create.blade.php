<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.receiving.receive-for', ['reference' => $purchaseOrder->reference])
    </x-slot>

    <form method="POST" action="{{ route('admin.operations.receiving.store', $purchaseOrder->id) }}" id="fc-receiving-form">
        @csrf

        <x-operations::idempotency />

        <x-operations::header
            :title="trans('operations::app.receiving.receive-for', ['reference' => $purchaseOrder->reference])"
            :back="route('admin.operations.purchase_orders.view', $purchaseOrder->id)"
        >
            <button
                type="button"
                class="primary-button"
                @click="$emitter.emit('open-confirm-modal', { message: '{{ e(addslashes(trans('operations::app.receiving.confirm-post'))) }}', agree: () => document.getElementById('fc-receiving-form').requestSubmit() })"
            >
                @lang('operations::app.receiving.post')
            </button>
        </x-operations::header>

        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300" v-pre>
            {{ $purchaseOrder->supplier?->name }} → {{ $purchaseOrder->location?->name }}
            · @lang('operations::app.receiving.tolerance', ['percent' => $tolerance])
        </p>

        <div class="mt-3.5 grid gap-2.5">
            <x-operations::card>
                <v-fc-receiving-scan></v-fc-receiving-scan>
            </x-operations::card>

            <x-operations::error name="lines" />

            @foreach ($purchaseOrder->items as $index => $item)
                <x-operations::card data-sku="{{ $item->sku }}" class="fc-receiving-line">
                    <input type="hidden" name="lines[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}">

                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-base font-semibold text-gray-800 dark:text-white" v-pre>{{ $item->sku }} — {{ $item->name }}</p>

                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            @lang('operations::app.common.ordered'): {{ $item->qty_ordered }}
                            · @lang('operations::app.common.received'): {{ $item->qty_received }}
                            · <strong>@lang('operations::app.common.outstanding'): {{ $item->outstandingQty() }}</strong>
                        </p>
                    </div>

                    <div class="grid gap-x-3 sm:grid-cols-2 lg:grid-cols-4">
                        <x-operations::field name="lines[{{ $index }}][qty_received]" type="number" min="0" step="1" :label="trans('operations::app.receiving.qty-received')" :value="0" class="fc-qty-received" />
                        <x-operations::field name="lines[{{ $index }}][qty_rejected]" type="number" min="0" step="1" :label="trans('operations::app.receiving.qty-rejected')" :value="0" />
                        <x-operations::field name="lines[{{ $index }}][batch_no]" :label="trans('operations::app.common.batch')" />
                        <x-operations::field name="lines[{{ $index }}][expiry_date]" type="date" :label="trans('operations::app.common.expiry')" />
                        <x-operations::field name="lines[{{ $index }}][storage_location]" :label="trans('operations::app.receiving.storage-location')" />
                        <x-operations::field name="lines[{{ $index }}][discrepancy_note]" :label="trans('operations::app.receiving.discrepancy-note')" class="lg:col-span-2" />

                        @if ($canOverride)
                            <x-operations::field name="lines[{{ $index }}][over_receipt_reason]" :label="trans('operations::app.receiving.over-receipt-reason')" :help="trans('operations::app.receiving.over-receipt-help')" />
                        @endif
                    </div>
                </x-operations::card>
            @endforeach

            <x-operations::card :title="trans('operations::app.common.general')">
                <div class="grid gap-x-3 sm:grid-cols-2">
                    <x-operations::field name="delivery_note_no" :label="trans('operations::app.receiving.delivery-note')" />
                    <x-operations::field name="remarks" type="textarea" :label="trans('operations::app.receiving.inspection-remarks')" />
                </div>
            </x-operations::card>
        </div>
    </form>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-fc-receiving-scan-template">
            <div>
                <label class="mb-1.5 block text-xs font-medium text-gray-800 dark:text-white" for="fc-scan">
                    @lang('operations::app.receiving.scan')
                </label>

                <input
                    id="fc-scan"
                    type="text"
                    class="w-full rounded-md border px-3 py-2.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    placeholder="@lang('operations::app.line-items.scan-placeholder')"
                    v-model="term"
                    @keydown.enter.prevent="scan"
                    autocomplete="off"
                    autofocus
                />

                <p class="mt-1 text-xs" :class="error ? 'text-red-600' : 'text-green-600'" v-if="message">@{{ message }}</p>
            </div>
        </script>

        <script type="module">
            app.component('v-fc-receiving-scan', {
                template: '#v-fc-receiving-scan-template',

                data() {
                    return { term: '', message: '', error: false };
                },

                methods: {
                    // Each scan of a PO line's SKU adds one unit to "received" for that line.
                    scan() {
                        const sku = this.term.trim();

                        this.term = '';

                        if (! sku) {
                            return;
                        }

                        const line = Array.from(document.querySelectorAll('.fc-receiving-line'))
                            .find((element) => element.dataset.sku.toLowerCase() === sku.toLowerCase());

                        if (! line) {
                            this.error = true;
                            this.message = "@lang('operations::app.receiving.scan-not-on-po')".replace(':sku', sku);

                            return;
                        }

                        const input = line.querySelector('.fc-qty-received input');

                        input.value = Number(input.value || 0) + 1;

                        this.error = false;
                        this.message = sku + ': ' + input.value;
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
