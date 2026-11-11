<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\ImpactController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VolunteerController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::view('/offline', 'offline')->name('offline');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/saved', ConfirmationController::class)->name('confirmation');

    // FR01 - Beneficiaries
    Route::middleware('can:beneficiaries.view')->group(function () {
        Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])->name('beneficiaries.index');
        Route::get('/beneficiaries/create', [BeneficiaryController::class, 'create'])->name('beneficiaries.create')->middleware('can:beneficiaries.manage');
        Route::post('/beneficiaries', [BeneficiaryController::class, 'store'])->name('beneficiaries.store')->middleware('can:beneficiaries.manage');
        Route::get('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'show'])->name('beneficiaries.show');
        Route::get('/beneficiaries/{beneficiary}/edit', [BeneficiaryController::class, 'edit'])->name('beneficiaries.edit')->middleware('can:beneficiaries.manage');
        Route::put('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'update'])->name('beneficiaries.update')->middleware('can:beneficiaries.manage');
    });

    // Donor registry
    Route::middleware('can:donors.view')->group(function () {
        Route::get('/donors', [DonorController::class, 'index'])->name('donors.index');
        Route::get('/donors/create', [DonorController::class, 'create'])->name('donors.create')->middleware('can:donors.manage');
        Route::post('/donors', [DonorController::class, 'store'])->name('donors.store')->middleware('can:donors.manage');
        Route::get('/donors/{donor}', [DonorController::class, 'show'])->name('donors.show');
        Route::get('/donors/{donor}/edit', [DonorController::class, 'edit'])->name('donors.edit')->middleware('can:donors.manage');
        Route::put('/donors/{donor}', [DonorController::class, 'update'])->name('donors.update')->middleware('can:donors.manage');
    });

    // FR02 / FR05 - Donations
    Route::middleware('can:donations.view')->group(function () {
        Route::get('/donations', [DonationController::class, 'index'])->name('donations.index');
        Route::get('/donations/create', [DonationController::class, 'create'])->name('donations.create')->middleware('can:donations.manage');
        Route::post('/donations', [DonationController::class, 'store'])->name('donations.store')->middleware('can:donations.manage');
        Route::get('/donations/{donation}', [DonationController::class, 'show'])->name('donations.show');
    });

    // FR03 - Distributions
    Route::middleware('can:distributions.view')->group(function () {
        Route::get('/distributions', [DistributionController::class, 'index'])->name('distributions.index');
        Route::get('/distributions/create', [DistributionController::class, 'create'])->name('distributions.create')->middleware('can:distributions.manage');
        Route::post('/distributions', [DistributionController::class, 'store'])->name('distributions.store')->middleware('can:distributions.manage');
        Route::get('/distributions/{distribution}', [DistributionController::class, 'show'])->name('distributions.show');
    });

    // Programs and activities
    Route::middleware('can:programs.view')->group(function () {
        Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::get('/programs/create', [ProgramController::class, 'create'])->name('programs.create')->middleware('can:programs.manage');
        Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store')->middleware('can:programs.manage');
        Route::get('/programs/{program}', [ProgramController::class, 'show'])->name('programs.show');
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->name('programs.edit')->middleware('can:programs.manage');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update')->middleware('can:programs.manage');

        Route::get('/activities/create', [ActivityController::class, 'create'])->name('activities.create')->middleware('can:programs.manage');
        Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store')->middleware('can:programs.manage');
        Route::get('/activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');
        Route::get('/activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit')->middleware('can:programs.manage');
        Route::put('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update')->middleware('can:programs.manage');
    });

    // FR04 - Volunteers and scheduling
    Route::middleware('can:volunteers.view')->group(function () {
        Route::get('/volunteers', [VolunteerController::class, 'index'])->name('volunteers.index');
        Route::get('/volunteers/create', [VolunteerController::class, 'create'])->name('volunteers.create')->middleware('can:volunteers.manage');
        Route::post('/volunteers', [VolunteerController::class, 'store'])->name('volunteers.store')->middleware('can:volunteers.manage');
        Route::get('/volunteers/{volunteer}', [VolunteerController::class, 'show'])->name('volunteers.show');
        Route::get('/volunteers/{volunteer}/edit', [VolunteerController::class, 'edit'])->name('volunteers.edit')->middleware('can:volunteers.manage');
        Route::put('/volunteers/{volunteer}', [VolunteerController::class, 'update'])->name('volunteers.update')->middleware('can:volunteers.manage');

        Route::middleware('can:volunteers.manage')->group(function () {
            Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
            Route::patch('/assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
        });
    });

    Route::middleware('can:schedule.own')->group(function () {
        Route::get('/my-schedule', [ScheduleController::class, 'index'])->name('schedule.index');
        Route::post('/my-schedule/{activity}', [ScheduleController::class, 'signUp'])->name('schedule.signup');
        Route::delete('/my-schedule/{assignment}', [ScheduleController::class, 'withdraw'])->name('schedule.withdraw');
    });

    // FR09 - In-app messaging
    Route::middleware('can:messages.use')->group(function () {
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{user}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    });

    // FR06 - Reports
    Route::middleware('can:reports.view')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // Donor portal (view only)
    Route::get('/impact', ImpactController::class)->name('impact')->middleware('can:impact.view');

    // Administration
    Route::middleware('can:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::get('/audit-log', AuditLogController::class)->name('audit.index')->middleware('can:audit.view');
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings/units', [UnitController::class, 'index'])->name('units.index');
        Route::post('/settings/units', [UnitController::class, 'store'])->name('units.store');
        Route::delete('/settings/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
    });
});
