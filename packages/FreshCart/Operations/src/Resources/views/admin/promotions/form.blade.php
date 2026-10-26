<x-admin::layouts>
    <x-slot:title>
        {{ $proposal->exists ? $proposal->reference : trans('operations::app.promotions.create') }}
    </x-slot>

    <form
        method="POST"
        action="{{ $proposal->exists ? route('admin.operations.promotions.update', $proposal->id) : route('admin.operations.promotions.store') }}"
    >
        @csrf

        @if ($proposal->exists)
            @method('PUT')
        @endif

        <x-operations::header
            :title="$proposal->exists ? $proposal->reference : trans('operations::app.promotions.create')"
            :back="route('admin.operations.promotions.index')"
        >
            <button type="submit" class="primary-button">@lang('operations::app.common.save-draft')</button>
        </x-operations::header>

        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                <x-operations::card :title="trans('operations::app.promotions.products')">
                    <x-operations::line-items :items="old('items', $products)" />

                    <p class="mt-2 text-xs text-gray-500">@lang('operations::app.promotions.products-help')</p>
                </x-operations::card>

                <x-operations::card :title="trans('operations::app.promotions.categories')">
                    @php($selected = collect(old('category_ids', $proposal->category_ids ?? []))->map(fn ($id) => (int) $id)->all())

                    <div class="grid max-h-72 gap-1 overflow-y-auto sm:grid-cols-2">
                        @forelse ($categoryOptions as $categoryId => $categoryName)
                            <label class="flex items-center gap-2 text-sm dark:text-gray-300">
                                <input type="checkbox" name="category_ids[]" value="{{ $categoryId }}" @checked(in_array($categoryId, $selected, true))>
                                <span v-pre>{{ $categoryName }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500">@lang('operations::app.common.empty')</p>
                        @endforelse
                    </div>
                </x-operations::card>
            </div>

            <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                <x-operations::card :title="trans('operations::app.common.general')">
                    <x-operations::field name="name" :label="trans('operations::app.promotions.name')" :value="$proposal->name" required />
                    <x-operations::field name="department" :label="trans('operations::app.promotions.department')" :value="$proposal->department" required />

                    <x-operations::field
                        name="discount_type"
                        type="select"
                        :label="trans('operations::app.promotions.discount-type')"
                        :options="['by_percent' => trans('operations::app.promotions.types.by_percent'), 'by_fixed' => trans('operations::app.promotions.types.by_fixed')]"
                        :value="$proposal->discount_type"
                        required
                    />

                    <x-operations::field name="discount_amount" type="number" step="0.01" min="0" :label="trans('operations::app.promotions.discount')" :value="$proposal->discount_amount" required />
                    <x-operations::field name="starts_on" type="date" :label="trans('operations::app.promotions.starts-on')" :value="$proposal->starts_on?->toDateString()" required />
                    <x-operations::field name="ends_on" type="date" :label="trans('operations::app.promotions.ends-on')" :value="$proposal->ends_on?->toDateString()" required />
                    <x-operations::field name="description" type="textarea" :label="trans('operations::app.promotions.description')" :value="$proposal->description" />
                </x-operations::card>
            </div>
        </div>
    </form>
</x-admin::layouts>
