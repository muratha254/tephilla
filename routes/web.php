<?php

use App\Http\Controllers\FleetAvailabilityController;
use App\Http\Controllers\FleetCustomerController;
use App\Http\Controllers\FleetCustomerPaymentController;
use App\Http\Controllers\FleetDashboardController;
use App\Http\Controllers\FleetDriverController;
use App\Http\Controllers\FleetIncidentReportController;
use App\Http\Controllers\FleetMechanicController;
use App\Http\Controllers\FleetMaintenanceController;
use App\Http\Controllers\FleetPmsSchedulerController;
use App\Http\Controllers\FleetReminderController;
use App\Http\Controllers\FleetReportController;
use App\Http\Controllers\FleetGeofenceController;
use App\Http\Controllers\FleetLiveTrackingController;
use App\Http\Controllers\FleetTrackingPlaybackController;
use App\Http\Controllers\FleetAccountCategoryController;
use App\Http\Controllers\FleetFuelVendorController;
use App\Http\Controllers\FleetEmployeeController;
use App\Http\Controllers\FleetFuelController;
use App\Http\Controllers\FleetStockController;
use App\Http\Controllers\FleetSettingsController;
use App\Http\Controllers\FleetSmartDispatchController;
use App\Http\Controllers\FleetTyreController;
use App\Http\Controllers\FleetTripController;
use App\Http\Controllers\FleetTripLookupController;
use App\Http\Controllers\FleetVehicleController;
use App\Http\Controllers\FleetVehicleGroupController;
use App\Http\Controllers\FleetVehicleLookupController;
use App\Http\Controllers\FleetVehicleVendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/login', function () {
    return view('auth.login');
})->middleware('guest')->name('login');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [FleetDashboardController::class, 'index'])->name('dashboard');
    Route::get('/availability', [FleetAvailabilityController::class, 'index'])->name('availability');
    Route::get('/vehicles', [FleetVehicleController::class, 'index'])->name('vehicles.index');
    Route::get('/vehicles/create', [FleetVehicleController::class, 'create'])->name('vehicles.create');
    Route::post('/vehicles/types/quick', [FleetVehicleLookupController::class, 'storeVehicleTypeQuick'])->name('vehicles.types.store-quick');
    Route::post('/vehicles/manufacturers/quick', [FleetVehicleLookupController::class, 'storeManufacturerQuick'])->name('vehicles.manufacturers.store-quick');
    Route::post('/vehicles/models/quick', [FleetVehicleLookupController::class, 'storeModelQuick'])->name('vehicles.models.store-quick');
    Route::post('/vehicles', [FleetVehicleController::class, 'store'])->name('vehicles.store');
    Route::get('/vehicles/{vehicle}', [FleetVehicleController::class, 'show'])->name('vehicles.show');
    Route::post('/vehicles/{vehicle}/fuel-refills', [FleetVehicleController::class, 'storeFuelRefill'])->name('vehicles.fuel-refills.store');
    Route::delete('/vehicles/{vehicle}/fuel-refills/{fuelRefill}', [FleetVehicleController::class, 'destroyFuelRefill'])->name('vehicles.fuel-refills.destroy');
    Route::get('/vehicles/{vehicle}/edit', [FleetVehicleController::class, 'edit'])->name('vehicles.edit');
    Route::put('/vehicles/{vehicle}', [FleetVehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('/vehicles/{vehicle}', [FleetVehicleController::class, 'destroy'])->name('vehicles.destroy');

    Route::get('/vehicle-groups', [FleetVehicleGroupController::class, 'index'])->name('vehicle-groups.index');
    Route::get('/vehicle-groups/create', [FleetVehicleGroupController::class, 'create'])->name('vehicle-groups.create');
    Route::post('/vehicle-groups', [FleetVehicleGroupController::class, 'store'])->name('vehicle-groups.store');
    Route::get('/vehicle-groups/{vehicleGroup}/edit', [FleetVehicleGroupController::class, 'edit'])->name('vehicle-groups.edit');
    Route::put('/vehicle-groups/{vehicleGroup}', [FleetVehicleGroupController::class, 'update'])->name('vehicle-groups.update');
    Route::delete('/vehicle-groups/{vehicleGroup}', [FleetVehicleGroupController::class, 'destroy'])->name('vehicle-groups.destroy');

    Route::get('/vehicle-vendors', [FleetVehicleVendorController::class, 'index'])->name('vehicle-vendors.index');
    Route::get('/vehicle-vendors/export/pdf', [FleetVehicleVendorController::class, 'exportPdf'])->name('vehicle-vendors.export-pdf');
    Route::get('/vehicle-vendors/create', [FleetVehicleVendorController::class, 'create'])->name('vehicle-vendors.create');
    Route::post('/vehicle-vendors', [FleetVehicleVendorController::class, 'store'])->name('vehicle-vendors.store');
    Route::get('/vehicle-vendors/{vehicleVendor}/edit', [FleetVehicleVendorController::class, 'edit'])->name('vehicle-vendors.edit');
    Route::put('/vehicle-vendors/{vehicleVendor}', [FleetVehicleVendorController::class, 'update'])->name('vehicle-vendors.update');
    Route::delete('/vehicle-vendors/{vehicleVendor}', [FleetVehicleVendorController::class, 'destroy'])->name('vehicle-vendors.destroy');

    Route::get('/drivers', [FleetDriverController::class, 'index'])->name('drivers.index');
    Route::get('/drivers/performance', [FleetDriverController::class, 'performance'])->name('drivers.performance');
    Route::get('/drivers/create', [FleetDriverController::class, 'create'])->name('drivers.create');
    Route::post('/drivers', [FleetDriverController::class, 'store'])->name('drivers.store');
    Route::get('/drivers/{driver}', [FleetDriverController::class, 'show'])->name('drivers.show');
    Route::get('/drivers/{driver}/edit', [FleetDriverController::class, 'edit'])->name('drivers.edit');
    Route::put('/drivers/{driver}', [FleetDriverController::class, 'update'])->name('drivers.update');
    Route::delete('/drivers/{driver}', [FleetDriverController::class, 'destroy'])->name('drivers.destroy');

    Route::get('/customers', [FleetCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [FleetCustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [FleetCustomerController::class, 'store'])->name('customers.store');
    Route::post('/customers/quick', [FleetCustomerController::class, 'storeQuick'])->name('customers.store-quick');
    Route::get('/customers/{customer}', [FleetCustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/edit', [FleetCustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [FleetCustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [FleetCustomerController::class, 'destroy'])->name('customers.destroy');

    Route::get('/payments/export/pdf', [FleetCustomerPaymentController::class, 'exportReportPdf'])->name('payments.export-pdf');
    Route::get('/payments/history', [FleetCustomerPaymentController::class, 'history'])->name('payments.history');
    Route::get('/payments', [FleetCustomerPaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [FleetCustomerPaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/customers/{customer}/trips', [FleetCustomerPaymentController::class, 'customerTrips'])->name('payments.customer-trips');
    Route::get('/payments/customers/{customer}/history', [FleetCustomerPaymentController::class, 'customerHistory'])->name('payments.customer-history');
    Route::get('/payments/{payment}/receipt/pdf', [FleetCustomerPaymentController::class, 'receiptPdf'])->name('payments.receipt-pdf');
    Route::get('/payments/{payment}/receipt/print', [FleetCustomerPaymentController::class, 'receiptPrint'])->name('payments.receipt-print');
    Route::delete('/payments/{payment}', [FleetCustomerPaymentController::class, 'destroy'])->name('payments.destroy');

    Route::get('/employees', [FleetEmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [FleetEmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [FleetEmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}/edit', [FleetEmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [FleetEmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{employee}', [FleetEmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::get('/settings', [FleetSettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/general', [FleetSettingsController::class, 'general'])->name('settings.general');
    Route::put('/settings/general', [FleetSettingsController::class, 'updateGeneral'])->name('settings.general.update');
    Route::get('/settings/smtp', [FleetSettingsController::class, 'smtp'])->name('settings.smtp');
    Route::put('/settings/smtp', [FleetSettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
    Route::get('/settings/{section}', [FleetSettingsController::class, 'placeholder'])->name('settings.placeholder');

    Route::get('/maintenance', [FleetMaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/maintenance/create', [FleetMaintenanceController::class, 'create'])->name('maintenance.create');
    Route::post('/maintenance', [FleetMaintenanceController::class, 'store'])->name('maintenance.store');
    Route::get('/maintenance/pms', [FleetPmsSchedulerController::class, 'index'])->name('maintenance.pms.index');
    Route::post('/maintenance/pms', [FleetPmsSchedulerController::class, 'store'])->name('maintenance.pms.store');
    Route::post('/maintenance/pms/check', [FleetPmsSchedulerController::class, 'check'])->name('maintenance.pms.check');
    Route::delete('/maintenance/pms/{pmsRule}', [FleetPmsSchedulerController::class, 'destroy'])->name('maintenance.pms.destroy');
    Route::get('/maintenance/cost-analytics', [FleetMaintenanceController::class, 'costAnalytics'])->name('maintenance.cost-analytics');

    Route::get('/incidents', [FleetIncidentReportController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/create', [FleetIncidentReportController::class, 'create'])->name('incidents.create');
    Route::post('/incidents', [FleetIncidentReportController::class, 'store'])->name('incidents.store');
    Route::post('/incidents/{incident}/convert', [FleetIncidentReportController::class, 'convertToMaintenance'])->name('incidents.convert');
    Route::delete('/incidents/{incident}', [FleetIncidentReportController::class, 'destroy'])->name('incidents.destroy');

    Route::get('/tyres', [FleetTyreController::class, 'index'])->name('tyres.index');
    Route::post('/tyres', [FleetTyreController::class, 'store'])->name('tyres.store');
    Route::delete('/tyres/{tyre}', [FleetTyreController::class, 'destroy'])->name('tyres.destroy');

    Route::get('/mechanics', [FleetMechanicController::class, 'index'])->name('mechanics.index');
    Route::get('/mechanics/create', [FleetMechanicController::class, 'create'])->name('mechanics.create');
    Route::post('/mechanics', [FleetMechanicController::class, 'store'])->name('mechanics.store');
    Route::get('/mechanics/{mechanic}/edit', [FleetMechanicController::class, 'edit'])->name('mechanics.edit');
    Route::put('/mechanics/{mechanic}', [FleetMechanicController::class, 'update'])->name('mechanics.update');
    Route::delete('/mechanics/{mechanic}', [FleetMechanicController::class, 'destroy'])->name('mechanics.destroy');

    Route::get('/stock', [FleetStockController::class, 'index'])->name('stock.index');
    Route::get('/stock/create', [FleetStockController::class, 'create'])->name('stock.create');
    Route::post('/stock', [FleetStockController::class, 'store'])->name('stock.store');
    Route::get('/stock/purchases', [FleetStockController::class, 'purchases'])->name('stock.purchases');
    Route::get('/stock/{stock}/edit', [FleetStockController::class, 'edit'])->name('stock.edit');
    Route::put('/stock/{stock}', [FleetStockController::class, 'update'])->name('stock.update');
    Route::delete('/stock/{stock}', [FleetStockController::class, 'destroy'])->name('stock.destroy');
    Route::post('/stock/{stock}/adjust', [FleetStockController::class, 'adjust'])->name('stock.adjust');
    Route::get('/stock/{stock}/history', [FleetStockController::class, 'history'])->name('stock.history');

    Route::get('/fuel', [FleetFuelController::class, 'index'])->name('fuel.index');
    Route::get('/fuel/create', [FleetFuelController::class, 'create'])->name('fuel.create');
    Route::post('/fuel', [FleetFuelController::class, 'store'])->name('fuel.store');
    Route::get('/fuel/{fuel}/edit', [FleetFuelController::class, 'edit'])->name('fuel.edit');
    Route::put('/fuel/{fuel}', [FleetFuelController::class, 'update'])->name('fuel.update');
    Route::delete('/fuel/{fuel}', [FleetFuelController::class, 'destroy'])->name('fuel.destroy');

    Route::get('/fuel-vendors', [FleetFuelVendorController::class, 'index'])->name('fuel-vendors.index');
    Route::post('/fuel-vendors', [FleetFuelVendorController::class, 'store'])->name('fuel-vendors.store');
    Route::delete('/fuel-vendors/{fuelVendor}', [FleetFuelVendorController::class, 'destroy'])->name('fuel-vendors.destroy');
    Route::get('/fuel-vendors/export/pdf', [FleetFuelVendorController::class, 'exportPdf'])->name('fuel-vendors.export-pdf');

    Route::get('/reminders', [FleetReminderController::class, 'index'])->name('reminders.index');
    Route::get('/reminders/create', [FleetReminderController::class, 'create'])->name('reminders.create');
    Route::post('/reminders', [FleetReminderController::class, 'store'])->name('reminders.store');
    Route::get('/reminders/{reminder}/edit', [FleetReminderController::class, 'edit'])->name('reminders.edit');
    Route::put('/reminders/{reminder}', [FleetReminderController::class, 'update'])->name('reminders.update');
    Route::patch('/reminders/{reminder}/status', [FleetReminderController::class, 'updateStatus'])->name('reminders.update-status');
    Route::delete('/reminders/{reminder}', [FleetReminderController::class, 'destroy'])->name('reminders.destroy');

    Route::get('/account-categories', [FleetAccountCategoryController::class, 'index'])->name('account-categories.index');
    Route::post('/account-categories', [FleetAccountCategoryController::class, 'store'])->name('account-categories.store');
    Route::delete('/account-categories/{accountCategory}', [FleetAccountCategoryController::class, 'destroy'])->name('account-categories.destroy');

    Route::get('/tracking/playback', [FleetTrackingPlaybackController::class, 'index'])->name('tracking.playback');
    Route::get('/tracking/playback/trips', [FleetTrackingPlaybackController::class, 'trips'])->name('tracking.playback.trips');
    Route::get('/tracking/playback/trips/{trip}/route', [FleetTrackingPlaybackController::class, 'route'])->name('tracking.playback.route');

    Route::get('/tracking/live', [FleetLiveTrackingController::class, 'index'])->name('tracking.live');
    Route::get('/tracking/live/feed', [FleetLiveTrackingController::class, 'feed'])->name('tracking.live.feed');

    Route::get('/geofences/geocode', [FleetGeofenceController::class, 'geocode'])->name('geofences.geocode');
    Route::get('/geofences/create', [FleetGeofenceController::class, 'create'])->name('geofences.create');
    Route::get('/geofences', [FleetGeofenceController::class, 'index'])->name('geofences.index');
    Route::post('/geofences', [FleetGeofenceController::class, 'store'])->name('geofences.store');
    Route::delete('/geofences/{geofence}', [FleetGeofenceController::class, 'destroy'])->name('geofences.destroy');

    Route::get('/reports/booking', [FleetReportController::class, 'booking'])->name('reports.booking');
    Route::get('/reports/booking/export/pdf', [FleetReportController::class, 'exportBookingPdf'])->name('reports.booking.export-pdf');
    Route::get('/reports/income', [FleetReportController::class, 'income'])->name('reports.income');
    Route::get('/reports/income/export/pdf', [FleetReportController::class, 'exportIncomePdf'])->name('reports.income.export-pdf');
    Route::get('/reports/fuel', [FleetReportController::class, 'fuel'])->name('reports.fuel');
    Route::get('/reports/fuel/export/pdf', [FleetReportController::class, 'exportFuelPdf'])->name('reports.fuel.export-pdf');
    Route::get('/reports/driver', [FleetReportController::class, 'driver'])->name('reports.driver');
    Route::get('/reports/driver/export/pdf', [FleetReportController::class, 'exportDriverPdf'])->name('reports.driver.export-pdf');
    Route::get('/reports/reminders', [FleetReportController::class, 'reminders'])->name('reports.reminders');
    Route::get('/reports/reminders/export/pdf', [FleetReportController::class, 'exportRemindersPdf'])->name('reports.reminders.export-pdf');
    Route::get('/reports/maintenance', [FleetReportController::class, 'maintenance'])->name('reports.maintenance');
    Route::get('/reports/maintenance/export/pdf', [FleetReportController::class, 'exportMaintenancePdf'])->name('reports.maintenance.export-pdf');

    Route::get('/maintenance/{maintenance}', [FleetMaintenanceController::class, 'show'])->name('maintenance.show');
    Route::get('/maintenance/{maintenance}/print', [FleetMaintenanceController::class, 'print'])->name('maintenance.print');
    Route::get('/maintenance/{maintenance}/edit', [FleetMaintenanceController::class, 'edit'])->name('maintenance.edit');
    Route::put('/maintenance/{maintenance}', [FleetMaintenanceController::class, 'update'])->name('maintenance.update');
    Route::patch('/maintenance/{maintenance}/status', [FleetMaintenanceController::class, 'updateStatus'])->name('maintenance.update-status');
    Route::delete('/maintenance/{maintenance}', [FleetMaintenanceController::class, 'destroy'])->name('maintenance.destroy');

    Route::post('/trips/types/quick', [FleetTripLookupController::class, 'storeTripTypeQuick'])->name('trips.types.store-quick');
    Route::get('/trips/smart-dispatch', [FleetSmartDispatchController::class, 'index'])->name('trips.smart-dispatch');
    Route::post('/trips/smart-dispatch/map-markers', [FleetSmartDispatchController::class, 'mapMarkers'])->name('trips.smart-dispatch.map-markers');
    Route::post('/trips/smart-dispatch/optimize', [FleetSmartDispatchController::class, 'optimize'])->name('trips.smart-dispatch.optimize');
    Route::post('/trips/smart-dispatch/confirm', [FleetSmartDispatchController::class, 'confirm'])->name('trips.smart-dispatch.confirm');
    Route::get('/trips', [FleetTripController::class, 'index'])->name('trips.index');
    Route::get('/trips/create', [FleetTripController::class, 'create'])->name('trips.create');
    Route::post('/trips', [FleetTripController::class, 'store'])->name('trips.store');
    Route::get('/trips/{trip}/live-map/data', [FleetTripController::class, 'liveMapData'])->name('trips.live-map-data');
    Route::get('/trips/{trip}/invoice/pdf', [FleetTripController::class, 'invoicePdf'])->name('trips.invoice-pdf');
    Route::get('/trips/{trip}/invoice/edit', [FleetTripController::class, 'editInvoice'])->name('trips.invoice.edit');
    Route::put('/trips/{trip}/invoice', [FleetTripController::class, 'updateInvoice'])->name('trips.invoice.update');
    Route::get('/trips/{trip}/expenses/pdf', [FleetTripController::class, 'expensePdf'])->name('trips.expense-pdf');
    Route::get('/trips/{trip}/expenses', [FleetTripController::class, 'expenses'])->name('trips.expenses');
    Route::post('/trips/{trip}/expenses', [FleetTripController::class, 'storeExpense'])->name('trips.expenses.store');
    Route::delete('/trips/{trip}/expenses/{expense}', [FleetTripController::class, 'destroyExpense'])->name('trips.expenses.destroy');
    Route::get('/trips/{trip}/payments', [FleetTripController::class, 'payments'])->name('trips.payments');
    Route::post('/trips/{trip}/payments', [FleetTripController::class, 'storePayment'])->name('trips.payments.store');
    Route::delete('/trips/{trip}/payments/{payment}', [FleetTripController::class, 'destroyPayment'])->name('trips.payments.destroy');
    Route::get('/trips/{trip}', [FleetTripController::class, 'show'])->name('trips.show');
    Route::get('/trips/{trip}/edit', [FleetTripController::class, 'edit'])->name('trips.edit');
    Route::patch('/trips/{trip}/status', [FleetTripController::class, 'updateStatus'])->name('trips.update-status');
    Route::put('/trips/{trip}', [FleetTripController::class, 'update'])->name('trips.update');
    Route::delete('/trips/{trip}', [FleetTripController::class, 'destroy'])->name('trips.destroy');
});
