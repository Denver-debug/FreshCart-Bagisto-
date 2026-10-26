{{--
    Product line editor shared by every operations document.

    The scan box accepts keyboard-wedge barcode scanners: a scanner "types" the SKU
    and presses Enter, which adds the product (or bumps its quantity). Typing two or
    more characters searches by SKU or name. Rows post as name[i][product_id],
    name[i][qty] and name[i][<extra field>]; the server re-validates every row.

    Props:
      name        input name prefix (default "items")
      items       initial rows: [{product_id, sku, name, qty, ...extra}]
      extra       extra numeric/text columns: [{key, label, type, step, min}]
      location    inventory source id used to show available stock (optional)
--}}
@props([
    'name' => 'items',
    'items' => [],
    'extra' => [],
    'location' => null,
    'locationField' => null,
])

<v-fc-line-items
    name="{{ $name }}"
    search-url="{{ route('admin.operations.products.search') }}"
    :initial-items="{{ json_encode(array_values($items)) }}"
    :extra-fields="{{ json_encode($extra) }}"
    :location-id="{{ json_encode($location) }}"
    location-field="{{ $locationField }}"
></v-fc-line-items>

<x-operations::error :name="$name" />

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-fc-line-items-template"
    >
        <div>
            <div class="relative mb-3">
                <label class="mb-1.5 block text-xs font-medium text-gray-800 dark:text-white">
                    @lang('operations::app.line-items.scan')
                </label>

                <input
                    type="text"
                    class="w-full rounded-md border px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    placeholder="@lang('operations::app.line-items.scan-placeholder')"
                    v-model="term"
                    @input="search"
                    @keydown.enter.prevent="scan"
                    autocomplete="off"
                    aria-label="@lang('operations::app.line-items.scan')"
                />

                <p
                    class="mt-1 text-xs text-gray-500"
                    v-if="isSearching"
                >
                    @lang('operations::app.line-items.searching')
                </p>

                <p
                    class="mt-1 text-xs text-red-600"
                    v-if="notFound"
                >
                    @lang('operations::app.line-items.not-found')
                </p>

                <ul
                    class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-md border bg-white shadow dark:border-gray-800 dark:bg-gray-900"
                    v-if="results.length"
                >
                    <li
                        class="cursor-pointer px-3 py-2 text-sm hover:bg-gray-100 dark:text-white dark:hover:bg-gray-800"
                        v-for="product in results"
                        :key="product.id"
                        @click="add(product)"
                    >
                        <span class="font-semibold">@{{ product.sku }}</span> — @{{ product.name }}

                        <span
                            class="text-xs text-gray-500"
                            v-if="product.available !== null && product.available !== undefined"
                        >
                            (@lang('operations::app.line-items.available'): @{{ product.available }})
                        </span>
                    </li>
                </ul>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-gray-50 text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        <tr>
                            <th class="px-2 py-2">@lang('operations::app.common.sku')</th>
                            <th class="px-2 py-2">@lang('operations::app.common.product')</th>
                            <th class="px-2 py-2" v-if="locationId || locationField">@lang('operations::app.line-items.available')</th>
                            <th class="px-2 py-2">@lang('operations::app.common.qty')</th>
                            <th class="px-2 py-2" v-for="field in extraFields" :key="field.key">@{{ field.label }}</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="! rows.length">
                            <td class="px-2 py-4 text-center text-gray-500" :colspan="5 + extraFields.length">
                                @lang('operations::app.line-items.empty')
                            </td>
                        </tr>

                        <tr
                            class="border-b dark:border-gray-800"
                            v-for="(row, index) in rows"
                            :key="row.uid"
                        >
                            <td class="px-2 py-2 font-semibold dark:text-white">
                                @{{ row.sku }}

                                <input type="hidden" :name="name + '[' + index + '][product_id]'" :value="row.product_id">
                                <input type="hidden" :name="name + '[' + index + '][sku]'" :value="row.sku">
                                <input type="hidden" :name="name + '[' + index + '][name]'" :value="row.name">
                            </td>

                            <td class="px-2 py-2 dark:text-gray-300">@{{ row.name }}</td>

                            <td class="px-2 py-2 dark:text-gray-300" v-if="locationId || locationField">
                                @{{ row.available ?? '—' }}
                            </td>

                            <td class="px-2 py-2">
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    class="w-24 rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                    :name="name + '[' + index + '][qty]'"
                                    v-model="row.qty"
                                    required
                                    :aria-label="'@lang('operations::app.common.qty') ' + row.sku"
                                />
                            </td>

                            <td class="px-2 py-2" v-for="field in extraFields" :key="field.key">
                                <input
                                    :type="field.type || 'number'"
                                    :step="field.step || 'any'"
                                    :min="field.min ?? null"
                                    class="w-32 rounded-md border px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                    :name="name + '[' + index + '][' + field.key + ']'"
                                    v-model="row[field.key]"
                                    :aria-label="field.label + ' ' + row.sku"
                                />
                            </td>

                            <td class="px-2 py-2 text-right">
                                <button
                                    type="button"
                                    class="icon-delete cursor-pointer rounded-md p-1.5 text-xl hover:bg-gray-200 dark:hover:bg-gray-800"
                                    @click="rows.splice(index, 1)"
                                    aria-label="@lang('operations::app.line-items.remove')"
                                ></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-fc-line-items', {
            template: '#v-fc-line-items-template',

            props: {
                name: { type: String, default: 'items' },
                searchUrl: { type: String, required: true },
                initialItems: { type: Array, default: () => [] },
                extraFields: { type: Array, default: () => [] },
                locationId: { default: null },
                locationField: { type: String, default: '' },
            },

            data() {
                return {
                    term: '',
                    results: [],
                    rows: this.initialItems.map((item) => ({ ...item, uid: this.uid() })),
                    isSearching: false,
                    notFound: false,
                    timer: null,
                };
            },

            mounted() {
                const field = this.locationSelect();

                if (field) {
                    field.addEventListener('change', () => this.refreshAvailability());
                }

                if (this.rows.length) {
                    this.refreshAvailability();
                }
            },

            methods: {
                uid() {
                    return Math.random().toString(36).slice(2);
                },

                locationSelect() {
                    return this.locationField ? document.querySelector('[name="' + this.locationField + '"]') : null;
                },

                currentLocation() {
                    const field = this.locationSelect();

                    return field ? field.value : this.locationId;
                },

                request(params) {
                    return this.$axios.get(this.searchUrl, {
                        params: { ...params, location_id: this.currentLocation() || undefined },
                    });
                },

                search() {
                    clearTimeout(this.timer);

                    this.notFound = false;

                    if (this.term.trim().length < 2) {
                        this.results = [];

                        return;
                    }

                    this.timer = setTimeout(() => {
                        this.isSearching = true;

                        this.request({ term: this.term })
                            .then((response) => this.results = response.data.data)
                            .finally(() => this.isSearching = false);
                    }, 250);
                },

                scan() {
                    const term = this.term.trim();

                    if (! term) {
                        return;
                    }

                    clearTimeout(this.timer);

                    this.request({ term, exact: 1 })
                        .then((response) => {
                            const products = response.data.data;

                            if (products.length === 1) {
                                this.add(products[0]);
                            } else if (products.length > 1) {
                                this.results = products;
                            } else {
                                this.notFound = true;
                            }
                        });
                },

                add(product) {
                    const existing = this.rows.find((row) => Number(row.product_id) === Number(product.id));

                    if (existing) {
                        existing.qty = Number(existing.qty || 0) + 1;
                    } else {
                        const row = {
                            uid: this.uid(),
                            product_id: product.id,
                            sku: product.sku,
                            name: product.name,
                            qty: 1,
                            available: product.available,
                        };

                        this.extraFields.forEach((field) => {
                            row[field.key] = field.default_from ? product[field.default_from] : (field.default ?? '');
                        });

                        this.rows.push(row);
                    }

                    this.term = '';
                    this.results = [];
                    this.notFound = false;
                },

                refreshAvailability() {
                    if (! this.currentLocation() || ! this.rows.length) {
                        return;
                    }

                    this.$axios.get(this.searchUrl, {
                        params: {
                            ids: this.rows.map((row) => row.product_id),
                            location_id: this.currentLocation(),
                        },
                    }).then((response) => {
                        const byId = {};

                        response.data.data.forEach((product) => byId[product.id] = product.available);

                        this.rows.forEach((row) => row.available = byId[row.product_id] ?? null);
                    });
                },
            },
        });
    </script>
@endPushOnce
