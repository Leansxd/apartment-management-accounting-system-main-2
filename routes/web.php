<?php

use App\Http\Controllers\ApartmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuesController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Siteler (apartman / rezidans)
    Route::get('/sites', [ApartmentController::class, 'index'])->name('sites.index');
    Route::post('/sites', [ApartmentController::class, 'store'])->name('sites.store');

    // Kayıtlı kullanıcılar: admin yalnızca kendi sitelerinin sakinlerini görür, rolleri süper admin yönetir
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::post('/users', [UserController::class, 'store'])->name('users.store')->can('super-admin');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role')->can('super-admin');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->can('super-admin');

    // Emlak portföyü
    Route::resource('properties', PropertyController::class)->except('show');

    // --- Siteye ait her şey: yetki kontrolü + kayıtların o siteye ait olduğu (scopeBindings) ---
    Route::prefix('sites/{apartment}')->name('site.')->middleware('can:manage,apartment')->scopeBindings()->group(function () {
        Route::get('/', [DashboardController::class, 'site'])->name('dashboard');

        // Ayarlar
        Route::get('/settings', [ApartmentController::class, 'edit'])->name('settings');
        Route::put('/settings', [ApartmentController::class, 'update'])->name('settings.update');
        Route::post('/managers', [ApartmentController::class, 'addManager'])->name('managers.store');
        Route::delete('/managers/{user}', [ApartmentController::class, 'removeManager'])->name('managers.destroy')->withoutScopedBindings();
        Route::post('/reset', [ApartmentController::class, 'reset'])->name('reset');
        Route::delete('/', [ApartmentController::class, 'destroy'])->name('destroy');
        Route::get('/audit', [ApartmentController::class, 'audit'])->name('audit');

        // Yapı: blok, daire, grup
        Route::get('/units', [StructureController::class, 'index'])->name('units.index');
        Route::post('/blocks', [StructureController::class, 'storeBlock'])->name('blocks.store');
        Route::delete('/blocks/{block}', [StructureController::class, 'destroyBlock'])->name('blocks.destroy');
        Route::post('/units', [StructureController::class, 'storeUnit'])->name('units.store');
        Route::post('/units/bulk', [StructureController::class, 'bulkUnits'])->name('units.bulk');
        Route::put('/units/{unit}', [StructureController::class, 'updateUnit'])->name('units.update');
        Route::delete('/units/{unit}', [StructureController::class, 'destroyUnit'])->name('units.destroy');
        Route::post('/groups', [StructureController::class, 'storeGroup'])->name('groups.store');
        Route::delete('/groups/{unitGroup}', [StructureController::class, 'destroyGroup'])->name('groups.destroy');

        // Üyeler (sakinler)
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::post('/members/{member}/invite', [MemberController::class, 'regenerateInvite'])->name('members.invite');
        Route::post('/members/{member}/unlink', [MemberController::class, 'unlink'])->name('members.unlink');
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        // Finans
        Route::get('/finance', [FinanceController::class, 'overview'])->name('finance');
        Route::get('/debts', [FinanceController::class, 'debts'])->name('debts.index');
        Route::post('/debts', [FinanceController::class, 'storeDebt'])->name('debts.store');
        Route::delete('/debts/{debt}', [FinanceController::class, 'destroyDebt'])->name('debts.destroy');
        Route::get('/collections', [FinanceController::class, 'collections'])->name('collections.index');
        Route::post('/collections', [FinanceController::class, 'collect'])->name('collections.store');
        Route::get('/cashboxes', [FinanceController::class, 'cashboxes'])->name('cashboxes.index');
        Route::post('/cashboxes', [FinanceController::class, 'storeCashbox'])->name('cashboxes.store');
        Route::put('/cashboxes/{cashbox}', [FinanceController::class, 'updateCashbox'])->name('cashboxes.update');
        Route::post('/transactions', [FinanceController::class, 'storeTransaction'])->name('transactions.store');
        Route::post('/transfers', [FinanceController::class, 'transfer'])->name('transfers.store');
        Route::get('/ledger', [FinanceController::class, 'ledger'])->name('ledger');
        Route::get('/income-expense', [FinanceController::class, 'incomeExpense'])->name('income-expense');
        Route::post('/categories', [FinanceController::class, 'storeCategory'])->name('categories.store');
        Route::get('/statements', [FinanceController::class, 'statement'])->name('statements');

        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::get('/vendors', [ExpenseController::class, 'vendors'])->name('vendors.index');
        Route::post('/vendors', [ExpenseController::class, 'storeVendor'])->name('vendors.store');
        Route::put('/vendors/{vendor}', [ExpenseController::class, 'updateVendor'])->name('vendors.update');
        Route::delete('/vendors/{vendor}', [ExpenseController::class, 'destroyVendor'])->name('vendors.destroy');

        Route::get('/dues', [DuesController::class, 'index'])->name('dues.index');
        Route::post('/dues', [DuesController::class, 'store'])->name('dues.store');
        Route::patch('/dues/{duesDefinition}', [DuesController::class, 'toggle'])->name('dues.toggle');
        Route::delete('/dues/{duesDefinition}', [DuesController::class, 'destroy'])->name('dues.destroy');
        Route::post('/dues/run', [DuesController::class, 'run'])->name('dues.run');

        // Operasyon
        Route::get('/tasks', [OperationsController::class, 'tasks'])->name('tasks.index');
        Route::post('/tasks', [OperationsController::class, 'storeTask'])->name('tasks.store');
        Route::patch('/tasks/{task}', [OperationsController::class, 'updateTask'])->name('tasks.update');
        Route::delete('/tasks/{task}', [OperationsController::class, 'destroyTask'])->name('tasks.destroy');
        Route::get('/staff', [OperationsController::class, 'staff'])->name('staff.index');
        Route::post('/staff', [OperationsController::class, 'storeStaff'])->name('staff.store');
        Route::put('/staff/{staff}', [OperationsController::class, 'updateStaff'])->name('staff.update');
        Route::delete('/staff/{staff}', [OperationsController::class, 'destroyStaff'])->name('staff.destroy');
        Route::get('/inventory', [OperationsController::class, 'inventory'])->name('inventory.index');
        Route::post('/inventory', [OperationsController::class, 'storeItem'])->name('inventory.store');
        Route::patch('/inventory/{inventoryItem}', [OperationsController::class, 'adjustItem'])->name('inventory.adjust');
        Route::delete('/inventory/{inventoryItem}', [OperationsController::class, 'destroyItem'])->name('inventory.destroy');
        Route::get('/meters', [OperationsController::class, 'meters'])->name('meters.index');
        Route::post('/meters', [OperationsController::class, 'storeMeter'])->name('meters.store');
        Route::post('/meters/{meter}/readings', [OperationsController::class, 'storeReading'])->name('meters.readings');
        Route::delete('/meters/{meter}', [OperationsController::class, 'destroyMeter'])->name('meters.destroy');

        Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
        Route::post('/security', [SecurityController::class, 'store'])->name('security.store');
        Route::patch('/security/{securityEntry}', [SecurityController::class, 'update'])->name('security.update');
        Route::delete('/security/{securityEntry}', [SecurityController::class, 'destroy'])->name('security.destroy');

        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::patch('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');

        // İletişim: duyuru, anket, karar defteri, bildirim, hatırlatıcı
        Route::get('/announcements', [CommunicationController::class, 'announcements'])->name('announcements.index');
        Route::post('/announcements', [CommunicationController::class, 'storeAnnouncement'])->name('announcements.store');
        Route::patch('/announcements/{announcement}', [CommunicationController::class, 'toggleAnnouncement'])->name('announcements.toggle');
        Route::delete('/announcements/{announcement}', [CommunicationController::class, 'destroyAnnouncement'])->name('announcements.destroy');
        Route::get('/polls', [CommunicationController::class, 'polls'])->name('polls.index');
        Route::post('/polls', [CommunicationController::class, 'storePoll'])->name('polls.store');
        Route::patch('/polls/{poll}', [CommunicationController::class, 'togglePoll'])->name('polls.toggle');
        Route::delete('/polls/{poll}', [CommunicationController::class, 'destroyPoll'])->name('polls.destroy');
        Route::get('/decisions', [CommunicationController::class, 'decisions'])->name('decisions.index');
        Route::post('/decisions', [CommunicationController::class, 'storeDecision'])->name('decisions.store');
        Route::delete('/decisions/{decision}', [CommunicationController::class, 'destroyDecision'])->name('decisions.destroy');
        Route::get('/notifications', [CommunicationController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications', [CommunicationController::class, 'sendNotification'])->name('notifications.store');
        Route::get('/reminders', [CommunicationController::class, 'reminders'])->name('reminders.index');
        Route::post('/reminders', [CommunicationController::class, 'sendReminders'])->name('reminders.send');

        // Raporlar (yazdır / PDF ve Excel uyumlu CSV)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show')
            ->whereIn('type', array_keys(ReportController::TYPES));
    });
});
