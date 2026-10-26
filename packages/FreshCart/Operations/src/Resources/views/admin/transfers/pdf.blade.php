@extends('operations::admin.pdf.layout', ['title' => trans('operations::app.transfers.pdf-title', ['reference' => $transfer->reference])])

@section('content')
    <table class="grid">
        <tr>
            <td>
                <strong>@lang('operations::app.common.source')</strong>: {{ $transfer->sourceLocation?->name }}<br>
                <strong>@lang('operations::app.common.destination')</strong>: {{ $transfer->destinationLocation?->name }}<br>
                <strong>@lang('operations::app.transfers.type')</strong>: {{ trans('operations::app.transfers.types.'.$transfer->type) }}<br>
                <strong>@lang('operations::app.common.status')</strong>: {{ trans('operations::app.statuses.'.$transfer->status) }}<br>
                <strong>@lang('operations::app.transfers.scheduled-dispatch')</strong>: {{ $transfer->scheduled_dispatch_at?->format('d M Y H:i') ?? '—' }}<br>
                <strong>@lang('operations::app.transfers.driver')</strong>: {{ $transfer->driver_name ?? '—' }} {{ $transfer->vehicle }}
            </td>
            <td class="right">
                <img src="data:image/svg+xml;base64,{{ $qr }}" width="110" height="110" alt="QR">
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>@lang('operations::app.common.sku')</th>
                <th>@lang('operations::app.common.product')</th>
                <th class="right">@lang('operations::app.stock-requests.requested')</th>
                <th class="right">@lang('operations::app.transfers.picked-qty')</th>
                <th class="right">@lang('operations::app.stock-requests.dispatched')</th>
                <th>@lang('operations::app.transfers.check')</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transfer->items as $item)
                <tr>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->name }}</td>
                    <td class="right">{{ $item->qty_requested }}</td>
                    <td class="right">{{ $item->qty_picked ?: '' }}</td>
                    <td class="right">{{ $item->qty_dispatched ?: '' }}</td>
                    <td>&#9744;</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="grid signatures">
        <tr>
            <td><div class="line">@lang('operations::app.pdf.released-by')</div></td>
            <td><div class="line">@lang('operations::app.pdf.received-by')</div></td>
        </tr>
    </table>
@endsection
