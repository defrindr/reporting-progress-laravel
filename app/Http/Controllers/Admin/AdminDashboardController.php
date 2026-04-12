<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Spatie\Permission\Models\Role;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'roles' => Role::count(),
                'institutions' => Institution::count(),
                'periods' => Period::count(),
                'projects' => Project::count(),
                'logbooks' => Logbook::count(),
            ],
        ]);
    }
}
