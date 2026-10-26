<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.locations.title')
    </x-slot>

    <form method="POST" action="{{ route('admin.operations.locations.update') }}">
        @csrf
        @method('PUT')

        <x-operations::header :title="trans('operations::app.locations.title')">
            <button type="submit" class="primary-button">@lang('operations::app.common.save')</button>
        </x-operations::header>

        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            @lang('operations::app.locations.info')
        </p>

        <div class="mt-3.5 grid gap-2.5">
            <x-operations::card :title="trans('operations::app.locations.types')">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-2">@lang('operations::app.locations.source')</th>
                                <th class="px-2 py-2">@lang('operations::app.locations.type')</th>
                                <th class="px-2 py-2">@lang('operations::app.locations.central')</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($sources as $source)
                                <tr class="border-b dark:border-gray-800">
                                    <td class="px-2 py-2 dark:text-white" v-pre>
                                        {{ $source->name }} <span class="text-xs text-gray-500">({{ $source->code }})</span>

                                        @unless ($source->status)
                                            <span class="label-closed">@lang('operations::app.common.inactive')</span>
                                        @endunless
                                    </td>

                                    <td class="px-2 py-2">
                                        <select
                                            name="locations[{{ $source->id }}][type]"
                                            class="rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                            aria-label="@lang('operations::app.locations.type')"
                                        >
                                            @foreach (['branch', 'warehouse'] as $type)
                                                <option value="{{ $type }}" @selected(($source->type ?? 'branch') === $type)>
                                                    @lang('operations::app.locations.types-'.$type)
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td class="px-2 py-2">
                                        <input
                                            type="radio"
                                            name="central"
                                            value="{{ $source->id }}"
                                            @checked($source->is_central)
                                            aria-label="@lang('operations::app.locations.central')"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-operations::card>

            <x-operations::card :title="trans('operations::app.locations.assignments')">
                <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.locations.assignments-info')</p>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-gray-600 dark:border-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-2">@lang('operations::app.locations.user')</th>

                                @foreach ($sources as $source)
                                    <th class="px-2 py-2" v-pre>{{ $source->name }}</th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($admins as $admin)
                                <tr class="border-b dark:border-gray-800">
                                    <td class="px-2 py-2 dark:text-white" v-pre>
                                        {{ $admin->name }}
                                        <span class="block text-xs text-gray-500">{{ $admin->role?->name }}</span>
                                    </td>

                                    @foreach ($sources as $source)
                                        <td class="px-2 py-2">
                                            <input
                                                type="checkbox"
                                                name="assignments[{{ $admin->id }}][]"
                                                value="{{ $source->id }}"
                                                @checked(in_array($source->id, $assignments[$admin->id] ?? [], true))
                                                aria-label="{{ $admin->name }} – {{ $source->name }}"
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-operations::card>
        </div>
    </form>
</x-admin::layouts>
