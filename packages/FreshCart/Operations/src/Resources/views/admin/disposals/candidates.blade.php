<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.disposals.review-list')
    </x-slot>

    <x-operations::header :title="trans('operations::app.disposals.review-list')" :back="route('admin.operations.disposals.index')">
        @if (bouncer()->hasPermission('operations.disposals.create'))
            <x-operations::action :action="route('admin.operations.candidates.refresh')" :label="trans('operations::app.disposals.refresh')" />
        @endif
    </x-operations::header>

    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
        @lang('operations::app.disposals.criteria-info', $criteria)
    </p>

    <form method="GET" class="mt-3 flex flex-wrap items-end gap-2">
        <x-operations::field
            class="!mb-0"
            name="criterion"
            type="select"
            :label="trans('operations::app.disposals.criterion')"
            :options="collect(\FreshCart\Operations\Models\RetirementCandidate::CRITERIA)->mapWithKeys(fn ($c) => [$c => trans('operations::app.disposals.criteria.'.$c)])->all()"
            :value="$filters['criterion'] ?? ''"
            :placeholder="trans('operations::app.common.all')"
        />

        <x-operations::field class="!mb-0" name="location_id" type="select" :label="trans('operations::app.common.location')" :options="$locationOptions" :value="$filters['location_id'] ?? ''" :placeholder="trans('operations::app.common.all')" />

        <button type="submit" class="secondary-button">@lang('operations::app.common.filter')</button>
    </form>

    <form method="POST" action="{{ route('admin.operations.candidates.nominate') }}" class="mt-3">
        @csrf

        <x-operations::card>
            <x-operations::error name="candidate_ids" />

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        <tr>
                            <th class="px-2 py-2"></th>
                            <th class="px-2 py-2">@lang('operations::app.disposals.criterion')</th>
                            <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                            <th class="px-2 py-2">@lang('operations::app.common.location')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.common.qty')</th>
                            <th class="px-2 py-2 text-right">@lang('operations::app.disposals.estimated-value')</th>
                            <th class="px-2 py-2">@lang('operations::app.disposals.evidence-basis')</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($candidates as $candidate)
                            <tr class="border-b dark:border-gray-800 dark:text-gray-300">
                                <td class="px-2 py-2">
                                    <input type="checkbox" name="candidate_ids[]" value="{{ $candidate->id }}" aria-label="{{ $candidate->product?->sku }}">
                                </td>
                                <td class="px-2 py-2">@lang('operations::app.disposals.criteria.'.$candidate->criterion)</td>
                                <td class="px-2 py-2 font-semibold" v-pre>{{ $candidate->product?->sku }}</td>
                                <td class="px-2 py-2" v-pre>{{ $candidate->location?->name }}</td>
                                <td class="px-2 py-2 text-right">{{ $candidate->qty }}</td>
                                <td class="px-2 py-2 text-right">{{ $candidate->estimated_value !== null ? core()->formatBasePrice((float) $candidate->estimated_value) : trans('operations::app.common.unvalued') }}</td>
                                <td class="px-2 py-2 text-xs" v-pre>
                                    @switch($candidate->criterion)
                                        @case('expired')
                                        @case('near_expiry')
                                            {{ trans('operations::app.disposals.basis-expiry', ['batch' => $candidate->details['batch_no'] ?? '—', 'date' => $candidate->details['expiry_date'] ?? '—', 'days' => $candidate->details['days_to_expiry'] ?? '—']) }}
                                            @break
                                        @case('held_stock')
                                            {{ trans('operations::app.disposals.basis-held', ['bucket' => trans('operations::app.buckets.'.($candidate->details['bucket'] ?? 'damaged')), 'since' => $candidate->details['held_since'] ?? '—']) }}
                                            @break
                                        @default
                                            {{ trans('operations::app.disposals.basis-slow', ['units' => $candidate->details['units_sold'] ?? 0, 'days' => $candidate->details['window_days'] ?? '—']) }}
                                    @endswitch
                                </td>
                                <td class="px-2 py-2 text-right">
                                    @if (bouncer()->hasPermission('operations.disposals.create'))
                                        <button
                                            type="submit"
                                            form="fc-dismiss-{{ $candidate->id }}"
                                            class="text-xs text-blue-600 hover:underline"
                                        >
                                            @lang('operations::app.disposals.dismiss')
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-2 py-6 text-center text-gray-500">@lang('operations::app.disposals.no-candidates')</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $candidates->links() }}</div>

            @if ($candidates->isNotEmpty() && bouncer()->hasPermission('operations.disposals.create'))
                <div class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
                    <x-operations::field class="!mb-0" name="reason" :label="trans('operations::app.disposals.nomination-reason')" required />
                    <button type="submit" class="primary-button">@lang('operations::app.disposals.nominate')</button>
                </div>
            @endif
        </x-operations::card>
    </form>

    @foreach ($candidates as $candidate)
        <form id="fc-dismiss-{{ $candidate->id }}" method="POST" action="{{ route('admin.operations.candidates.dismiss', $candidate->id) }}" class="hidden">
            @csrf
        </form>
    @endforeach
</x-admin::layouts>
