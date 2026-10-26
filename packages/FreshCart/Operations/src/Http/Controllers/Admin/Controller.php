<?php

namespace FreshCart\Operations\Http\Controllers\Admin;

use FreshCart\Operations\Exceptions\OperationsException;
use FreshCart\Operations\Support\LocationScope;
use Illuminate\Http\RedirectResponse;
use Webkul\Admin\Http\Controllers\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * Run a workflow action, turning business-rule violations into a flash error
     * instead of a 500 so the user can correct the input.
     */
    protected function attempt(callable $action, string $successMessage, ?callable $redirect = null): RedirectResponse
    {
        try {
            $result = $action();
        } catch (OperationsException $e) {
            session()->flash('error', $e->getMessage());

            return back()->withInput();
        }

        session()->flash('success', $successMessage);

        return $redirect ? $redirect($result) : back();
    }

    protected function locations(): LocationScope
    {
        return app(LocationScope::class);
    }

    protected function adminId(): int
    {
        return (int) auth()->guard('admin')->id();
    }
}
