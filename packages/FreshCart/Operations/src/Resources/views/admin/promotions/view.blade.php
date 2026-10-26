<x-admin::layouts>
    <x-slot:title>
        {{ $proposal->reference }}
    </x-slot>

    @php($isOwner = $proposal->requested_by === auth()->guard('admin')->id())

    <x-operations::header :title="$proposal->reference" :status="$proposal->status" :back="route('admin.operations.promotions.index')">
        @if ($proposal->status === 'draft' && $isOwner)
            <a href="{{ route('admin.operations.promotions.edit', $proposal->id) }}" class="secondary-button">@lang('operations::app.common.edit')</a>

            <x-operations::action
                :action="route('admin.operations.promotions.submit', $proposal->id)"
                :label="trans('operations::app.common.submit')"
                :confirm="trans('operations::app.common.confirm-submit')"
                variant="primary"
            />
        @endif

        @if (in_array($proposal->status, ['draft', 'submitted', 'scheduled', 'active']) && ($isOwner || bouncer()->hasPermission('operations.promotions.approve')))
            <x-operations::action
                :action="route('admin.operations.promotions.cancel', $proposal->id)"
                :label="trans('operations::app.common.cancel')"
                :confirm="trans('operations::app.promotions.confirm-cancel')"
            />
        @endif
    </x-operations::header>

    <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
        <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
            <x-operations::card :title="trans('operations::app.promotions.targets')">
                <p class="mb-2 text-xs font-semibold text-gray-500">@lang('operations::app.promotions.products')</p>

                <ul class="mb-3 grid gap-1 text-sm dark:text-gray-300" v-pre>
                    @forelse ($products as $product)
                        <li>{{ $product->sku }} — {{ $product->name }}</li>
                    @empty
                        <li class="text-gray-500">—</li>
                    @endforelse
                </ul>

                <p class="mb-2 text-xs font-semibold text-gray-500">@lang('operations::app.promotions.categories')</p>

                <p class="text-sm dark:text-gray-300" v-pre>{{ $categories->implode(', ') ?: '—' }}</p>
            </x-operations::card>

            @if ($performance)
                <x-operations::card :title="trans('operations::app.promotions.performance')">
                    <p class="mb-3 text-xs text-gray-500">@lang('operations::app.promotions.performance-info')</p>

                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (['during', 'before'] as $period)
                            <div class="rounded border p-3 dark:border-gray-800">
                                <p class="text-xs text-gray-500">@lang('operations::app.promotions.period-'.$period) ({{ $performance[$period]['from'] }} – {{ $performance[$period]['to'] }})</p>
                                <p class="text-lg font-semibold dark:text-white">{{ $performance[$period]['units'] }} @lang('operations::app.promotions.units')</p>
                                <p class="text-sm dark:text-gray-300">{{ core()->formatBasePrice($performance[$period]['revenue']) }} · {{ $performance[$period]['orders'] }} @lang('operations::app.promotions.orders')</p>
                            </div>
                        @endforeach

                        <div class="rounded border p-3 dark:border-gray-800">
                            <p class="text-xs text-gray-500">@lang('operations::app.promotions.uplift')</p>
                            <p class="text-lg font-semibold dark:text-white">{{ $performance['uplift_units'] === null ? '—' : $performance['uplift_units'].'%' }}</p>
                        </div>
                    </div>
                </x-operations::card>
            @endif
        </div>

        <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
            <x-operations::card :title="trans('operations::app.common.general')">
                <dl class="grid gap-2 text-sm dark:text-gray-300" v-pre>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.promotions.name')</dt><dd class="font-semibold">{{ $proposal->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.promotions.department')</dt><dd>{{ $proposal->department }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.promotions.discount')</dt><dd>{{ $proposal->discount_type === 'by_percent' ? (float) $proposal->discount_amount.'%' : core()->formatBasePrice((float) $proposal->discount_amount) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.promotions.window')</dt><dd>{{ $proposal->starts_on->toDateString() }} → {{ $proposal->ends_on->toDateString() }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.requested-by')</dt><dd>{{ $proposal->requester?->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.common.approvals')</dt><dd>{{ $proposal->approvals_received }} / {{ $proposal->approval_level_required }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.promotions.catalog-rule')</dt><dd>{{ $proposal->catalogRule ? '#'.$proposal->catalogRule->id.' · '.($proposal->catalogRule->status ? trans('operations::app.common.active') : trans('operations::app.common.inactive')) : '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">@lang('operations::app.promotions.description')</dt><dd class="whitespace-pre-line">{{ $proposal->description ?: '—' }}</dd></div>
                </dl>
            </x-operations::card>

            <x-operations::decision
                :document="$proposal"
                approve-route="admin.operations.promotions.approve"
                reject-route="admin.operations.promotions.reject"
            />

            <x-operations::history :document="$proposal" />
        </div>
    </div>
</x-admin::layouts>
