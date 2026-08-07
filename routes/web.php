<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FyMasterController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProgramMasterController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Login
Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
Route::get('/login', [AuthController::class, 'showLoginForm']);
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Mistaken "/.../login" URLs (e.g. /questionnaire/login) → real login
Route::get('/{section}/login', function () {
    return redirect()->route('login');
})->where('section', 'dashboard|program-master|fy-master|questionnaire|reports|changepassword');

// First-time / forced password change (before full login session)
Route::get('/changepassword', [PasswordController::class, 'showForm'])->name('changepassword');
Route::post('/changepassword', [PasswordController::class, 'update'])->name('changepassword.post');

// Protected Routes — no resource IDs in URLs
Route::middleware(['checklogin'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    Route::post('/dashboard/program', [DashboardController::class, 'selectProgram'])
        ->name('dashboard.program');

    Route::get('/program-master', [ProgramMasterController::class, 'index'])
        ->name('program-master');
    Route::get('/program-master/create', [ProgramMasterController::class, 'create'])
        ->name('program-master.create');
    Route::post('/program-master/store', [ProgramMasterController::class, 'store'])
        ->name('program-master.store');
    Route::post('/program-master/edit', [ProgramMasterController::class, 'edit'])
        ->name('program-master.edit');
    Route::post('/program-master/update', [ProgramMasterController::class, 'update'])
        ->name('program-master.update');

    Route::get('/fy-master', [FyMasterController::class, 'index'])
        ->name('fy-master');
    Route::get('/fy-master/create', [FyMasterController::class, 'create'])
        ->name('fy-master.create');
    Route::post('/fy-master/store', [FyMasterController::class, 'store'])
        ->name('fy-master.store');
    Route::post('/fy-master/edit', [FyMasterController::class, 'edit'])
        ->name('fy-master.edit');
    Route::post('/fy-master/update', [FyMasterController::class, 'update'])
        ->name('fy-master.update');

    Route::get('/questionnaire', [QuestionnaireController::class, 'index'])
        ->name('questionnaire');
    Route::get('/questionnaire/create', [QuestionnaireController::class, 'create'])
        ->name('questionnaire.create');
    Route::post('/questionnaire/store', [QuestionnaireController::class, 'store'])
        ->name('questionnaire.store');
    Route::post('/questionnaire/view', [QuestionnaireController::class, 'show'])
        ->name('questionnaire.show');
    Route::post('/questionnaire/edit', [QuestionnaireController::class, 'edit'])
        ->name('questionnaire.edit');
    Route::post('/questionnaire/update', [QuestionnaireController::class, 'update'])
        ->name('questionnaire.update');
    Route::post('/questionnaire/delete', [QuestionnaireController::class, 'destroy'])
        ->name('questionnaire.destroy');

    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports');
    Route::post('/reports/search', [ReportController::class, 'search'])
        ->name('reports.search');
    Route::post('/reports/clear', [ReportController::class, 'clear'])
        ->name('reports.clear');
});

// Unknown URLs: send logged-in users to dashboard, guests to login (no bare 404)
Route::fallback(function () {
    if (session('loggedin')) {
        return redirect()
            ->route('dashboard')
            ->with('error', 'The page you requested was not found.');
    }

    return redirect()
        ->route('login')
        ->with('error', 'The page you requested was not found.');
});
