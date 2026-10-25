@extends('operations::admin.pdf.layout', ['title' => trans('operations::app.purchase-orders.pdf-title', ['reference' => $purchaseOrder->reference])])

@section('content')
    <table class="grid">
        <tr>
            <td>
                <strong>@lang('operations::app.common.supplier')</strong><br>
                {{ $purchaseOrder->supplier?->name }}<br>
                {{ $purchaseOrder->supplier?->contact_name }}<br>
                {{ $purchaseOrder->supplier?->phone }} {{ $purchaseOrder->supplier?->email }}<br>
                {!! nl2br(e($purchaseOrder->supplier?->address)) !!}
            </td>
            <td>
                <strong>@lang('operations::app.purchase-orders.deliver-to')</strong><br>
                {{ $purchaseOrder->location?->name }}<br>
                {{ $purchaseOrder->location?->street }}, {{ $purchaseOrder->location?->city }}<br>
                @lang('operations::app.purchase-orders.order-date'): {{ $purchaseOrder->order_date?->toDateString() }}<br>
                @lang('operations::app.purchase-orders.expected-delivery'): {{ $purchaseOrder->expected_delivery_date?->toDateString() ?? '—' }}<br>
                @lang('operations::app.common.status'): {{ trans('operations::app.statuses.'.$purchaseOrder->status) }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>@lang('operations::app.common.sku')</th>
                <th>@lang('operations::app.common.product')</th>
                <th class="right">@lang('operations::app.common.qty')</th>
                <th class="right">@lang('operations::app.common.unit-cost')</th>
                <th class="right">@lang('operations::app.common.total')</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->name }}</td>
                    <td class="right">{{ $item->qty_ordered }}</td>
                    <td class="right">{{ core()->formatBasePrice((float) $item->unit_cost) }}</td>
                    <td class="right">{{ core()->formatBasePrice((float) $item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="right">@lang('operations::app.common.total')</th>
                <th class="right">{{ core()->formatBasePrice((float) $purchaseOrder->subtotal) }}</th>
            </tr>
        </tfoot>
    </table>

    @if ($purchaseOrder->notes)
        <p><strong>@lang('operations::app.common.notes'):</strong> {{ $purchaseOrder->notes }}</p>
    @endif

    <table class="grid signatures">
        <tr>
            <td><div class="line">@lang('operations::app.pdf.prepared-by'): {{ $purchaseOrder->creator?->name }}</div></td>
            <td><div class="line">@lang('operations::app.pdf.authorized-signature')</div></td>
        </tr>
    </table>
@endsection
