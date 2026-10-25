@extends('operations::admin.pdf.layout', ['title' => trans('operations::app.disposals.pdf-title', ['reference' => $disposal->reference])])

@section('content')
    <table class="grid">
        <tr>
            <td>
                <strong>@lang('operations::app.common.location')</strong>: {{ $disposal->location?->name }}<br>
                <strong>@lang('operations::app.common.status')</strong>: {{ trans('operations::app.statuses.'.$disposal->status) }}<br>
                <strong>@lang('operations::app.common.requested-by')</strong>: {{ $disposal->requester?->name }}
            </td>
            <td>
                <strong>@lang('operations::app.disposals.method')</strong>: {{ $disposal->disposal_method ?? '—' }}<br>
                <strong>@lang('operations::app.disposals.completed-at')</strong>: {{ $disposal->completed_at?->format('d M Y H:i') ?? '—' }}<br>
                <strong>@lang('operations::app.disposals.evidence')</strong>: {{ $disposal->attachments->count() }}
            </td>
        </tr>
    </table>

    <p><strong>@lang('operations::app.common.reason')</strong>: {{ $disposal->reason }}</p>

    <table>
        <thead>
            <tr>
                <th>@lang('operations::app.common.sku')</th>
                <th>@lang('operations::app.common.product')</th>
                <th>@lang('operations::app.common.batch')</th>
                <th>@lang('operations::app.common.expiry')</th>
                <th class="right">@lang('operations::app.common.qty')</th>
                <th>@lang('operations::app.common.reason')</th>
                <th>@lang('operations::app.disposals.disposition')</th>
                <th class="right">@lang('operations::app.common.unit-cost')</th>
                <th class="right">@lang('operations::app.common.value')</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($disposal->items as $item)
                <tr>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->batch_no }}</td>
                    <td>{{ $item->expiry_date?->toDateString() }}</td>
                    <td class="right">{{ $item->qty }}</td>
                    <td>{{ trans('operations::app.disposals.reasons.'.$item->reason) }}</td>
                    <td>{{ trans('operations::app.disposals.dispositions.'.$item->disposition) }}</td>
                    <td class="right">{{ core()->formatBasePrice((float) $item->unit_cost) }}</td>
                    <td class="right">{{ core()->formatBasePrice((float) $item->line_value) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="8" class="right">@lang('operations::app.common.total')</th>
                <th class="right">{{ core()->formatBasePrice((float) $disposal->total_value) }}</th>
            </tr>
        </tfoot>
    </table>

    <h3>@lang('operations::app.common.history')</h3>

    <table>
        <thead>
            <tr>
                <th>@lang('operations::app.disposals.when')</th>
                <th>@lang('operations::app.disposals.action')</th>
                <th>@lang('operations::app.disposals.by')</th>
                <th>@lang('operations::app.common.remarks')</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($disposal->approvals as $entry)
                <tr>
                    <td>{{ $entry->created_at?->format('d M Y H:i') }}</td>
                    <td>{{ trans('operations::app.actions.'.$entry->action) }}{{ $entry->level > 1 ? ' (L'.$entry->level.')' : '' }}</td>
                    <td>{{ $entry->admin?->name }}</td>
                    <td>{{ $entry->remarks }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
