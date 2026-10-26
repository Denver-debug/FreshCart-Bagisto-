{{--
    Approval panel, kept apart from editing actions. Rendered only when the server
    says the current admin may take the next decision; the routes re-check everything.
--}}
@props(['document', 'approveRoute', 'rejectRoute', 'pendingStatus' => 'submitted'])

@if (app(\FreshCart\Operations\Services\ApprovalService::class)->canDecide($document, $pendingStatus))
    <x-operations::card :title="trans('operations::app.common.decision')" class="border border-blue-200 dark:border-blue-900">
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
            @lang('operations::app.common.decision-info', [
                'level' => $document->approvals_received + 1,
                'required' => $document->approval_level_required,
            ])
        </p>

        <form method="POST" action="{{ route($approveRoute, $document->id) }}">
            @csrf

            <x-operations::field
                name="remarks"
                type="textarea"
                :label="trans('operations::app.common.remarks')"
            />

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="primary-button"
                    @click="$emitter.emit('open-confirm-modal', { message: '{{ e(addslashes(trans('operations::app.common.confirm-approve'))) }}', agree: () => $event.target.closest('form').requestSubmit() })"
                >
                    @lang('operations::app.common.approve')
                </button>

                <button
                    type="button"
                    class="secondary-button"
                    @click="$emitter.emit('open-confirm-modal', { message: '{{ e(addslashes(trans('operations::app.common.confirm-reject'))) }}', agree: () => { const form = $event.target.closest('form'); form.action = '{{ route($rejectRoute, $document->id) }}'; form.requestSubmit(); } })"
                >
                    @lang('operations::app.common.reject')
                </button>
            </div>
        </form>
    </x-operations::card>
@endif
