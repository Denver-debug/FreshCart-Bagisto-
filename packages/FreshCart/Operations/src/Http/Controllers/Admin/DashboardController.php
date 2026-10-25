<?php

namespace FreshCart\Operations\Http\Controllers\Admin;

use FreshCart\Operations\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(): View
    {
        $filters = request()->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        return view('operations::admin.dashboard.index', $this->dashboard->build($filters) + ['filters' => $filters]);
    }
}
