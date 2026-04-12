<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register']);

Route::middleware('auth')->group(function (): void {
	Route::middleware('role:Admin')->group(function (): void {
		Route::get('roles', [RoleController::class, 'index']);
		Route::post('roles', [RoleController::class, 'store']);
		Route::put('roles/{role}', [RoleController::class, 'update']);
		Route::delete('roles/{role}', [RoleController::class, 'destroy']);

		Route::get('users', [UserController::class, 'index']);
		Route::post('users', [UserController::class, 'store']);
		Route::put('users/{user}', [UserController::class, 'update']);
		Route::delete('users/{user}', [UserController::class, 'destroy']);

		Route::get('institutions', [InstitutionController::class, 'index']);
		Route::post('institutions', [InstitutionController::class, 'store']);
		Route::put('institutions/{institution}', [InstitutionController::class, 'update']);
		Route::delete('institutions/{institution}', [InstitutionController::class, 'destroy']);

		Route::get('periods', [PeriodController::class, 'index']);
		Route::post('periods', [PeriodController::class, 'store']);
		Route::put('periods/{period}', [PeriodController::class, 'update']);
		Route::delete('periods/{period}', [PeriodController::class, 'destroy']);
	});

	Route::get('logbooks', [LogbookController::class, 'index']);
	Route::post('logbooks', [LogbookController::class, 'store']);
	Route::get('logbooks/{logbook}', [LogbookController::class, 'show']);
	Route::put('logbooks/{logbook}', [LogbookController::class, 'update']);
	Route::delete('logbooks/{logbook}', [LogbookController::class, 'destroy']);

	Route::get('projects', [ProjectController::class, 'index']);
	Route::post('projects', [ProjectController::class, 'store']);
	Route::get('projects/{project}', [ProjectController::class, 'show']);
	Route::put('projects/{project}', [ProjectController::class, 'update']);
	Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus']);
	Route::post('projects/{project}/comments', [ProjectController::class, 'addComment']);
	Route::get('projects/{project}/activity', [ProjectController::class, 'activity']);
	Route::delete('projects/{project}', [ProjectController::class, 'destroy']);

	Route::get('reports/summary', [ReportController::class, 'summary']);
});
