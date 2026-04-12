<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEvaluationLabController;
use App\Http\Controllers\Admin\AdminInstitutionController;
use App\Http\Controllers\Admin\AdminPeriodController;
use App\Http\Controllers\Admin\AdminProjectController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuthSessionController;
use App\Http\Controllers\Intern\InternDashboardController;
use App\Http\Controllers\Intern\InternLogbookController;
use App\Http\Controllers\Intern\InternProjectBoardController;
use App\Http\Controllers\ProfilePasswordController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', function () {
        $user = Auth::user();

        if ($user instanceof User && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user instanceof User && $user->hasRole('Intern')) {
            return redirect()->route('intern.dashboard');
        }

        return redirect()->route('projects.board');
    })->name('dashboard');

    Route::get('/intern/dashboard', [InternDashboardController::class, 'index'])
        ->middleware('role:Intern')
        ->name('intern.dashboard');

    Route::get('/logbook', [InternLogbookController::class, 'index'])->name('logbook.form');
    Route::post('/logbook', [InternLogbookController::class, 'store'])->name('logbook.store');
    Route::get('/logbook/task-resume', [InternLogbookController::class, 'taskResume'])->name('logbook.task-resume');

    Route::get('/profile/password', [ProfilePasswordController::class, 'edit'])->name('profile.password.edit');
    Route::put('/profile/password', [ProfilePasswordController::class, 'update'])->name('profile.password.update');

    Route::get('/projects/board', [InternProjectBoardController::class, 'index'])->name('projects.board');
    Route::post('/projects/tasks', [InternProjectBoardController::class, 'storeTask'])->name('projects.tasks.store');
    Route::patch('/projects/{project}/status', [InternProjectBoardController::class, 'setStatus'])->name('projects.status');
    Route::patch('/projects/{project}/reassign', [InternProjectBoardController::class, 'reassign'])->name('projects.reassign');
    Route::patch('/projects/{project}/advance', [InternProjectBoardController::class, 'advance'])->name('projects.advance');
    Route::post('/projects/{project}/comment', [InternProjectBoardController::class, 'addComment'])->name('projects.comment');

    Route::post('/logout', [AuthSessionController::class, 'destroy'])->name('logout');

    Route::middleware('role:Admin')->prefix('admin')->as('admin.')->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/roles', [AdminRoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [AdminRoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [AdminRoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [AdminRoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/institutions', [AdminInstitutionController::class, 'index'])->name('institutions.index');
        Route::post('/institutions', [AdminInstitutionController::class, 'store'])->name('institutions.store');
        Route::put('/institutions/{institution}', [AdminInstitutionController::class, 'update'])->name('institutions.update');
        Route::delete('/institutions/{institution}', [AdminInstitutionController::class, 'destroy'])->name('institutions.destroy');

        Route::get('/periods', [AdminPeriodController::class, 'index'])->name('periods.index');
        Route::post('/periods', [AdminPeriodController::class, 'store'])->name('periods.store');
        Route::post('/periods/global-holidays/sync', [AdminPeriodController::class, 'syncGlobalHolidays'])->name('periods.global-holidays.sync');
        Route::get('/periods/new-users-csv', [AdminPeriodController::class, 'downloadNewUsersCsv'])->name('periods.new-users-csv');
        Route::put('/periods/{period}', [AdminPeriodController::class, 'update'])->name('periods.update');
        Route::delete('/periods/{period}', [AdminPeriodController::class, 'destroy'])->name('periods.destroy');

        Route::get('/projects', [AdminProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [AdminProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{projectSpec}', [AdminProjectController::class, 'show'])->name('projects.show');
        Route::post('/projects/{projectSpec}/backlogs', [AdminProjectController::class, 'storeBacklog'])->name('projects.backlogs.store');
        Route::put('/projects/{projectSpec}/backlogs/{backlog}', [AdminProjectController::class, 'updateBacklog'])->name('projects.backlogs.update');
        Route::delete('/projects/{projectSpec}/backlogs/{backlog}', [AdminProjectController::class, 'destroyBacklog'])->name('projects.backlogs.destroy');
        Route::patch('/projects/{projectSpec}/activate-sprint', [AdminProjectController::class, 'activateSprint'])->name('projects.activate-sprint');
        Route::put('/projects/{projectSpec}', [AdminProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{projectSpec}', [AdminProjectController::class, 'destroy'])->name('projects.destroy');

        Route::get('/evaluation-lab', [AdminEvaluationLabController::class, 'index'])->name('evaluation-lab.index');
    });
});
