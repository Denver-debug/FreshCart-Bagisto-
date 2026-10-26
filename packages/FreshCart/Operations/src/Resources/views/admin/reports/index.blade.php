<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.reports.title')
    </x-slot>

    <x-operations::header :title="trans('operations::app.reports.title')" />

    <div class="mt-3.5 grid gap-2.5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($groups as $permission => $reports)
            <x-operations::card :title="trans('operations::app.reports.groups.'.\Illuminate\Support\Str::afterLast($permission, '.'))">
                <ul class="grid gap-2 text-sm">
                    @foreach ($reports as $report)
                        <li>
                            <a class="font-semibold text-blue-600 hover:underline" href="{{ route('admin.operations.reports.'.$report) }}">@lang('operations::app.reports.names.'.$report)</a>
                            <p class="text-xs text-gray-500">@lang('operations::app.reports.descriptions.'.$report)</p>
                        </li>
                    @endforeach
                </ul>
            </x-operations::card>
        @empty
            <p class="text-sm text-gray-500">@lang('operations::app.reports.none')</p>
        @endforelse
    </div>
</x-admin::layouts>
