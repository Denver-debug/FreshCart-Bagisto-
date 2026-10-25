<x-operations::card :title="trans('operations::app.dashboard.sales')">
    <div class="mb-3 grid grid-cols-3 gap-2 text-sm dark:text-gray-300">
        <div><p class="text-xs text-gray-500">@lang('operations::app.reports.summary.orders')</p><p class="font-semibold">{{ $sales['summary']['orders'] }}</p></div>
        <div><p class="text-xs text-gray-500">@lang('operations::app.reports.summary.revenue')</p><p class="font-semibold">{{ core()->formatBasePrice($sales['summary']['revenue']) }}</p></div>
        <div><p class="text-xs text-gray-500">@lang('operations::app.reports.summary.unshipped')</p><p class="font-semibold">{{ $sales['summary']['unshipped'] }}</p></div>
    </div>

    <p class="mb-1 text-xs font-semibold text-gray-500">@lang('operations::app.dashboard.by-branch')</p>

    @forelse ($sales['by_branch'] as $row)
        <p class="text-sm dark:text-gray-300" v-pre>{{ $row->location }}: {{ (int) $row->units }} · {{ core()->formatBasePrice((float) $row->revenue) }}</p>
    @empty
        <p class="text-sm text-gray-500">@lang('operations::app.dashboard.no-sales')</p>
    @endforelse

    @php($max = max(1, collect($sales['daily'])->max('revenue')))

    @if ($sales['daily'])
        <p class="mb-1 mt-3 text-xs font-semibold text-gray-500">@lang('operations::app.dashboard.trend')</p>

        <div class="grid gap-1">
            @foreach ($sales['daily'] as $day)
                <div class="flex items-center gap-2 text-xs dark:text-gray-300">
                    <span class="w-20 shrink-0">{{ $day->day }}</span>
                    <span class="h-2 rounded bg-blue-600" style="width: {{ max(2, round($day->revenue / $max * 100)) }}%"></span>
                    <span class="shrink-0">{{ core()->formatBasePrice((float) $day->revenue) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <p class="mt-2 text-xs text-gray-500">@lang('operations::app.reports.notes.branch_sales')</p>
</x-operations::card>
