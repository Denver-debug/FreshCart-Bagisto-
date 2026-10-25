<x-admin::layouts>
    <x-slot:title>
        @lang('operations::app.price-changes.create')
    </x-slot>

    <form method="POST" action="{{ route('admin.operations.price_changes.store') }}" enctype="multipart/form-data">
        @csrf

        <x-operations::header :title="trans('operations::app.price-changes.create')" :back="route('admin.operations.price_changes.index')">
            <a href="{{ route('admin.operations.price_changes.template') }}" class="secondary-button">@lang('operations::app.price-changes.download-template')</a>
            <button type="submit" class="primary-button">@lang('operations::app.price-changes.validate-upload')</button>
        </x-operations::header>

        <x-operations::card class="mt-3.5 max-w-2xl">
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">@lang('operations::app.price-changes.info')</p>

            <x-operations::field name="name" :label="trans('operations::app.promotions.name')" required />
            <x-operations::field name="effective_at" type="datetime-local" :label="trans('operations::app.price-changes.effective-at')" :value="now()->format('Y-m-d\TH:i')" required />

            <label class="mb-1.5 block text-xs font-medium text-gray-800 dark:text-white required" for="fc-price-file">@lang('operations::app.price-changes.file')</label>
            <input id="fc-price-file" type="file" name="file" accept=".csv,text/csv" required class="text-sm dark:text-gray-300">
            <x-operations::error name="file" />

            @if (session('import_errors'))
                <div class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-gray-900 dark:text-red-400" role="alert">
                    <p class="mb-1 font-semibold">@lang('operations::app.price-changes.fix-rows')</p>

                    <ul class="list-disc ltr:pl-5 rtl:pr-5" v-pre>
                        @foreach (session('import_errors') as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-operations::card>
    </form>
</x-admin::layouts>
