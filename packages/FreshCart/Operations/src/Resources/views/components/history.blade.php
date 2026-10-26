@props(['document'])

<x-operations::card :title="trans('operations::app.common.history')">
    @php($entries = $document->approvals()->with('admin')->get())

    @if ($entries->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">@lang('operations::app.common.no-history')</p>
    @else
        <ol class="grid gap-3" v-pre>
            @foreach ($entries as $entry)
                <li class="border-b pb-2 text-sm last:border-0 dark:border-gray-800">
                    <p class="font-semibold text-gray-800 dark:text-white">
                        {{ trans('operations::app.actions.'.$entry->action) }}
                        @if ($entry->level > 1)
                            ({{ trans('operations::app.common.level', ['level' => $entry->level]) }})
                        @endif
                    </p>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $entry->admin?->name ?? trans('operations::app.common.system') }}
                        · {{ core()->formatDate($entry->created_at, 'd M Y H:i') }}
                    </p>

                    @if ($entry->remarks)
                        <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $entry->remarks }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</x-operations::card>
