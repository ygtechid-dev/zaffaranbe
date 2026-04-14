<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$healthCheck = function () use ($router) {
    $dbStatus = 'disconnected';
    try {
        app('db')->connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected: ' . $e->getMessage();
    }

    return response()->json([
        'app' => 'Naqupos Backend API',
        'version' => $router->app->version(),
        'status' => 'success',
        'database' => $dbStatus,
        'timestamp' => date('c'),
    ]);
};



$router->get('/', $healthCheck);
$router->get('/health', $healthCheck);

// Swagger Documentation Routes
$router->get('/api-docs', 'SwaggerController@docs');
$router->get('/spec', 'SwaggerController@spec');

// Fallback for payment redirect (DOKU/Midtrans) that accidentally lands on the backend
$router->get('/subscriptions', function (\Illuminate\Http\Request $request) {
    $adminUrl = env('ADMIN_URL', 'http://localhost:3091');
    // Prevent infinite redirect if ADMIN_URL is somehow misconfigured to the backend self
    if (str_contains($adminUrl, $request->getHost() . ':' . $request->getPort())) {
        $adminUrl = 'http://localhost:3091';
    }
    $query = $request->getQueryString();
    $redirectUrl = rtrim($adminUrl, '/') . '/subscriptions' . ($query ? '?' . $query : '');
    return redirect($redirectUrl);
});

// Test Email Route
$router->get('/test-email', function (\App\Services\EmailService $emailService) {
    $to = 'vikaputriariyanti@gmail.com';

    // Create Mock Booking Object
    $mockBooking = new \stdClass();
    $mockBooking->booking_date = \Carbon\Carbon::now()->addDay();
    $mockBooking->start_time = '10:00:00';
    $mockBooking->guest_name = 'Vika Putri (Test)';
    $mockBooking->booking_ref = 'BK-TEST-123';

    $mockTherapist = new \stdClass();
    $mockTherapist->name = 'Therapist Zafaran';
    $mockBooking->therapist = $mockTherapist;

    $mockService = new \stdClass();
    $mockService->name = 'Zafaran Signature Massage';
    $mockBooking->service = $mockService;

    $mockBooking->user = null; // Test guest flow

    // 1. Send New Booking Notification
    $success1 = $emailService->sendStaffBookingNotification($to, $mockBooking);

    // 2. Send Reschedule Notification (Update mock first)
    $mockBooking->booking_date = \Carbon\Carbon::now()->addDays(2);
    $mockBooking->start_time = '13:00:00';
    $success2 = $emailService->sendStaffRescheduleNotification($to, $mockBooking);

    return response()->json([
        'status' => ($success1 && $success2) ? 'success' : 'partial_success/failed',
        'details' => [
            'booking_notification' => $success1 ? 'Sent' : 'Failed',
            'reschedule_notification' => $success2 ? 'Sent' : 'Failed'
        ],
        'target' => $to
    ]);
});



/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
*/
$router->group(['prefix' => 'api/v1'], function () use ($router) {

    // Public Routes
    $router->group(['prefix' => 'auth'], function () use ($router) {
        $router->post('register', 'AuthController@register');
        $router->post('login', 'AuthController@login');
        $router->post('verify-otp', 'AuthController@verifyOtp');
        $router->post('resend-otp', 'AuthController@resendOtp');
        $router->post('forgot-password', 'AuthController@forgotPassword');
        $router->post('verify-reset-otp', 'AuthController@verifyResetOtp');
        $router->post('reset-password', 'AuthController@resetPassword');

        // Protected auth routes
        $router->group(['middleware' => 'auth'], function () use ($router) {
            $router->post('logout', 'AuthController@logout');
            $router->post('refresh', 'AuthController@refresh');
            $router->get('me', 'AuthController@me');
        });
    });

    // Payment Callback (Public)
    $router->post('payments/callback', 'PaymentController@callback');
    $router->post('payments/doku/callback', 'PaymentController@callback'); // Special alias for DOKU

    // Shared Payment Config
    $router->get('payment-configs', 'PaymentController@getConfig');

    // Public service listing
    $router->get('services', 'ServiceController@index');
    $router->get('services/{id}', 'ServiceController@show');

    // Public product listing
    $router->get('products', 'ProductController@index');
    $router->get('products/{id}', 'ProductController@show');

    // Public banner listing (active only)
    $router->get('banners', 'BannerController@index');
    $router->get('banners/{id}', 'BannerController@show');
    $router->post('banners/{id}/view', 'BannerController@incrementViews');
    $router->post('banners/{id}/click', 'BannerController@incrementClicks');

    // Public news listing
    $router->get('news', 'Admin\NewsController@index');

    // Public promo listing
    $router->get('promos', 'Admin\PromoController@index');

    // Public branches
    $router->get('branches', 'BranchController@index');
    $router->get('branches/{id}', 'BranchController@show');

    // Public cities & locations
    $router->get('cities', 'CityController@index');
    $router->get('provinces', 'LocationController@provinces');
    $router->get('regencies', 'LocationController@regencies');
    $router->get('districts', 'LocationController@districts');
    $router->get('villages', 'LocationController@villages');

    // Public Checker & Availability (No auth required)
    $router->group(['prefix' => 'availability'], function () use ($router) {
        $router->post('/therapist', 'BookingController@getTherapistAvailability');
        $router->post('/check', 'BookingController@checkAvailability');
    });

    /*
    |--------------------------------------------------------------------------
    | Customer Routes (Authenticated) - For Mobile App naqupos-salon-customer
    |--------------------------------------------------------------------------
    */
    $router->group(['middleware' => 'auth', 'prefix' => 'customer'], function () use ($router) {

        // Booking Management
        $router->group(['prefix' => 'bookings'], function () use ($router) {
            $router->get('/', 'BookingController@index');
            $router->post('/', 'BookingController@store');
            $router->get('/{id}', 'BookingController@show');
            $router->post('/{id}/cancel', 'BookingController@cancel');
            $router->post('/{id}/items/{itemId}/cancel', 'BookingController@cancelItem');
            $router->post('/{id}/refund-request', 'BookingController@requestRefund');
            $router->put('/{id}/reschedule', 'BookingController@reschedule');
            $router->post('/{id}/items/{itemId}/reschedule', 'BookingController@rescheduleItem');
        });

        // Payment
        $router->group(['prefix' => 'payments'], function () use ($router) {
            $router->get('/config', 'PaymentController@getConfig');
            $router->post('/initiate', 'PaymentController@initiate');
            $router->get('/status/{id}', 'PaymentController@status');
            $router->post('/mock-confirm', 'PaymentController@mockConfirm'); // For testing
        });

        // Feedback
        $router->group(['prefix' => 'feedbacks'], function () use ($router) {
            $router->get('/', 'FeedbackController@index');
            $router->post('/', 'FeedbackController@store');
            $router->get('/booking/{bookingId}', 'FeedbackController@getByBooking'); 
        });

        // User Profile
        $router->group(['prefix' => 'profile'], function () use ($router) {
            $router->get('/', 'ProfileController@show');
            $router->put('/', 'ProfileController@update');
            $router->put('/password', 'ProfileController@changePassword');
        });

        // Notifications
        $router->group(['prefix' => 'notifications'], function () use ($router) {
            $router->get('/', 'NotificationController@index');
            $router->get('/unread-count', 'NotificationController@unreadCount');
            $router->put('/{id}/read', 'NotificationController@markAsRead');
            $router->post('/mark-all-read', 'NotificationController@markAllAsRead');
            $router->delete('/{id}', 'NotificationController@destroy');
            $router->delete('/', 'NotificationController@clearAll');
        });

        // Saved Payment Methods
        $router->group(['prefix' => 'payment-methods'], function () use ($router) {
            $router->get('/', 'CustomerPaymentMethodController@index');
            $router->post('/', 'CustomerPaymentMethodController@store');
            $router->put('/{id}', 'CustomerPaymentMethodController@update');
            $router->put('/{id}/default', 'CustomerPaymentMethodController@setDefault');
            $router->delete('/{id}', 'CustomerPaymentMethodController@destroy');
        });

        // Transaction History
        $router->get('/history', 'BookingController@index');

        // Promos
        $router->post('/promos/validate', 'Admin\PromoController@validateCode');
    });

    /*
    |--------------------------------------------------------------------------
    | Cashier Routes - For POS System
    |--------------------------------------------------------------------------
    */
    $router->group(['middleware' => ['auth', 'role:cashier,admin,owner,super_admin'], 'prefix' => 'cashier'], function () use ($router) {

        // Shift Management
        $router->post('/shift/clock-in', 'CashierShiftController@clockIn');
        $router->post('/shift/clock-out', 'CashierShiftController@clockOut');
        $router->get('/shift/current', 'CashierShiftController@currentShift');
        $router->get('/shifts', 'CashierShiftController@index');
        $router->put('/shifts/{id}', 'CashierShiftController@update');

        // POS Transactions
        $router->group(['prefix' => 'transactions'], function () use ($router) {
            $router->get('/', 'TransactionController@index');
            $router->post('/', 'TransactionController@store');
            $router->get('/{id}', 'TransactionController@show');
            $router->post('/{id}/print', 'TransactionController@print');
        });

        // Today's Schedule
        $router->get('/schedule/today', 'CashierController@todaySchedule');

        // Get available therapists
        $router->get('/therapists/available', 'CashierController@getAvailableTherapists');

        // Booking check-in
        $router->post('/bookings/{id}/check-in', 'CashierController@checkIn');
        $router->post('/bookings/{id}/complete', 'CashierController@completeBooking');

        // Daily Reports
        $router->get('/reports/daily', 'CashierController@dailyReport');

        // Expenses (Petty Cash)
        $router->group(['prefix' => 'expenses'], function () use ($router) {
            $router->get('/categories', 'ExpenseController@getCategories');
            $router->post('/categories', 'ExpenseController@storeCategory');
            $router->put('/categories/{id}', 'ExpenseController@updateCategory');
            $router->delete('/categories/{id}', 'ExpenseController@deleteCategory');
            $router->post('/', 'ExpenseController@store');
            $router->get('/', 'ExpenseController@index');
            $router->put('/{id}', 'ExpenseController@update');
            $router->delete('/{id}', 'ExpenseController@destroy');
            $router->get('/{id}/download', 'ExpenseController@download');
        });

        // Payment Methods
        $router->get('/payment-methods', 'PaymentMethodController@index');
        $router->get('/payment-methods/all', 'PaymentMethodController@all');
        $router->post('/payment-methods', 'PaymentMethodController@store');
        $router->put('/payment-methods/{id}', 'PaymentMethodController@update');
        $router->delete('/payment-methods/{id}', 'PaymentMethodController@destroy');
        $router->post('/payment-methods/{id}/toggle', 'PaymentMethodController@toggleActive');

        // Bank Deposits
        $router->group(['prefix' => 'bank-deposits'], function () use ($router) {
            $router->get('/', 'BankDepositController@index');
            $router->post('/', 'BankDepositController@store');
            $router->get('/cash-balance', 'BankDepositController@getCashBalance');
            $router->post('/cash-balance/update', 'BankDepositController@updateCashBalance');
            $router->get('/stats', 'BankDepositController@getStats');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes - For Admin Dashboard naqupos-admin
    |--------------------------------------------------------------------------
    */
    $router->group(['middleware' => ['auth', 'role:admin,owner,super_admin'], 'prefix' => 'admin'], function () use ($router) {

        // Menus
        $router->get('/menus', 'Admin\\MenuController@index');

        // Dashboard
        $router->get('/dashboard', 'Admin\\DashboardController@index');
        $router->get('/dashboard/stats', 'Admin\\DashboardController@stats');
        $router->get('/dashboard/charts', 'Admin\\DashboardController@charts');

        // Booking Management (Admin)
        $router->group(['prefix' => 'bookings'], function () use ($router) {
            $router->get('/', 'Admin\\BookingController@index');
            $router->get('/{id}', 'Admin\\BookingController@show');
            $router->post('/', 'Admin\\BookingController@store');
            $router->put('/{id}', 'Admin\\BookingController@update');
            $router->put('/{id}/reschedule', 'Admin\\BookingController@reschedule');
            $router->put('/{id}/status', 'Admin\\BookingController@updateStatus');
            $router->post('/{id}/refund', 'Admin\\BookingController@processRefund');

            // Item Level Actions
            $router->post('/{id}/items/{itemId}/cancel', 'Admin\\BookingController@cancelItem');
            $router->post('/{id}/items/{itemId}/reschedule', 'Admin\\BookingController@rescheduleItem');
            $router->post('/{id}/items/{itemId}/complete', 'Admin\\BookingController@completeItem');
            $router->post('/{id}/items/{itemId}/refund', 'Admin\\BookingController@refundItem');
            $router->get('/{id}/agenda-logs', 'Admin\\BookingController@agendaLogs');
        });

        // Transactions (POS History)
        $router->group(['prefix' => 'transactions'], function () use ($router) {
            $router->get('/', 'TransactionController@index');
            $router->get('/{id}', 'TransactionController@show');
        });

        // Calendar Management
        $router->get('/calendar', 'Admin\\CalendarController@index');
        $router->get('/calendar/settings', 'Admin\\CalendarController@getSettings');
        $router->put('/calendar/settings', 'Admin\\CalendarController@updateSettings');
        $router->get('/calendar/therapists', 'Admin\\CalendarController@therapistSchedule');

        // Notification Settings
        $router->get('/notifications/settings', 'Admin\\NotificationSettingsController@show');
        $router->put('/notifications/settings', 'Admin\\NotificationSettingsController@update');

        // Closed Dates Management
        $router->group(['prefix' => 'closed-dates'], function () use ($router) {
            $router->get('/', 'Admin\\ClosedDateController@index');
            $router->post('/', 'Admin\\ClosedDateController@store');
            $router->delete('/{id}', 'Admin\\ClosedDateController@destroy');
        });

        // Customer Management (CRM)
        $router->group(['prefix' => 'customers'], function () use ($router) {
            $router->get('/', 'Admin\\CustomerController@index');
            $router->post('/', 'Admin\\CustomerController@store');
            $router->get('/{id}', 'Admin\\CustomerController@show');
            $router->get('/{id}/history', 'Admin\\CustomerController@bookingHistory');
            $router->put('/{id}', 'Admin\\CustomerController@update');
            $router->delete('/{id}', 'Admin\\CustomerController@destroy');
        });

        // Staff/Therapist Management
        $router->group(['prefix' => 'therapists'], function () use ($router) {
            $router->get('/', 'Admin\\TherapistController@index');
            $router->post('/', 'Admin\\TherapistController@store');
            $router->get('/{id}', 'Admin\\TherapistController@show');
            $router->put('/{id}', 'Admin\\TherapistController@update');
            $router->delete('/{id}', 'Admin\\TherapistController@destroy');
            $router->post('/{id}/photo', 'Admin\\TherapistController@uploadPhoto');

            // Therapist Schedules
            $router->get('/{id}/schedules', 'Admin\\TherapistController@schedules');
            $router->post('/{id}/schedules', 'Admin\\TherapistController@storeSchedule');
            $router->put('/schedules/{scheduleId}', 'Admin\\TherapistController@updateSchedule');
            $router->delete('/schedules/{scheduleId}', 'Admin\\TherapistController@deleteSchedule');

            // Therapist Commissions
            $router->get('/{id}/commissions', 'Admin\\TherapistController@getCommissions');
            $router->post('/{id}/commissions', 'Admin\\TherapistController@saveCommissions');
        });

        // Commission Management
        $router->group(['prefix' => 'commissions'], function () use ($router) {
            $router->get('/', 'Admin\\CommissionController@index');
        });

        // Service Management
        $router->group(['prefix' => 'services'], function () use ($router) {
            $router->get('/', 'ServiceController@index');
            $router->post('/', 'ServiceController@store');
            $router->post('/reorder', 'ServiceController@reorder');
            $router->get('/price-logs', 'ServiceController@allPriceLogs');
            $router->get('/{id}/price-logs', 'ServiceController@priceLogs');

            // Service Categories
            $router->get('/categories', 'Admin\\ServiceCategoryController@index');
            $router->post('/categories', 'Admin\\ServiceCategoryController@store');
            $router->post('/categories/reorder', 'Admin\\ServiceCategoryController@reorder');
            $router->post('/categories/{id}/image', 'Admin\\ServiceCategoryController@uploadImage');
            $router->put('/categories/{id}', 'Admin\\ServiceCategoryController@update');
            $router->delete('/categories/{id}', 'Admin\\ServiceCategoryController@destroy');

            $router->get('/{id}', 'ServiceController@show');
            $router->post('/{id}/image', 'ServiceController@uploadImage');
            $router->put('/{id}', 'ServiceController@update');
            $router->delete('/{id}', 'ServiceController@destroy');
        });

        // Facility Management
        $router->group(['prefix' => 'facilities'], function () use ($router) {
            $router->get('/', 'Admin\\FacilityController@index');
            $router->post('/', 'Admin\\FacilityController@store');
            $router->put('/{id}', 'Admin\\FacilityController@update');
            $router->delete('/{id}', 'Admin\\FacilityController@destroy');
        });

        // Product Management (Inventory)
        $router->group(['prefix' => 'products'], function () use ($router) {
            $router->get('/', 'ProductController@index');
            $router->post('/', 'ProductController@store');
            $router->get('/brands', 'BrandController@index'); // Added Brand Index
            $router->post('/brands', 'BrandController@store'); // Added Brand Store
            $router->put('/brands/{id}', 'BrandController@update'); // Added Brand Update
            $router->delete('/brands/{id}', 'BrandController@destroy'); // Added Brand Destroy

            $router->get('/categories', 'CategoryController@index'); // Added Category Index
            $router->post('/categories', 'CategoryController@store'); // Added Category Store
            $router->put('/categories/{id}', 'CategoryController@update'); // Added Category Update
            $router->delete('/categories/{id}', 'CategoryController@destroy'); // Added Category Destroy

            $router->get('/{id}', 'ProductController@show');
            $router->put('/{id}', 'ProductController@update');
            $router->delete('/{id}', 'ProductController@destroy');
            $router->post('/update-brand', 'ProductController@updateBrand');
            $router->post('/delete-brand', 'ProductController@deleteBrand');
            $router->post('/update-category', 'ProductController@updateCategory');
            $router->post('/delete-category', 'ProductController@deleteCategory');
        });

        // Asset Management
        // Asset Categories (Placed before assets group to ensure precedence)
        $router->get('assets/categories', 'Admin\\AssetCategoryController@index');
        $router->post('assets/categories', 'Admin\\AssetCategoryController@store');
        $router->put('assets/categories/{id}', 'Admin\\AssetCategoryController@update');
        $router->delete('assets/categories/{id}', 'Admin\\AssetCategoryController@destroy');

        // Asset Management
        $router->group(['prefix' => 'assets'], function () use ($router) {
            $router->get('/', 'Admin\\AssetController@index');
            $router->post('/', 'Admin\\AssetController@store');

            $router->get('/{id}', 'Admin\\AssetController@show');
            $router->put('/{id}', 'Admin\\AssetController@update');
            $router->delete('/{id}', 'Admin\\AssetController@destroy');
        });

        // Room Management
        $router->group(['prefix' => 'rooms'], function () use ($router) {
            $router->get('/', 'Admin\\RoomController@index');
            $router->post('/', 'Admin\\RoomController@store');
            $router->get('/{id}', 'Admin\\RoomController@show');
            $router->put('/{id}', 'Admin\\RoomController@update');
            $router->delete('/{id}', 'Admin\\RoomController@destroy');
        });

        // Tax Management
        $router->group(['prefix' => 'taxes'], function () use ($router) {
            $router->get('/', 'Admin\\TaxController@index');
            $router->post('/', 'Admin\\TaxController@store');
            $router->put('/{id}', 'Admin\\TaxController@update');
            $router->delete('/{id}', 'Admin\\TaxController@destroy');
        });

        // Discount Type Management
        $router->group(['prefix' => 'discounts'], function () use ($router) {
            $router->get('/', 'Admin\\DiscountTypeController@index');
            $router->post('/', 'Admin\\DiscountTypeController@store');
            $router->put('/{id}', 'Admin\\DiscountTypeController@update');
            $router->delete('/{id}', 'Admin\\DiscountTypeController@destroy');
        });

        // Branch Management
        $router->group(['prefix' => 'branches'], function () use ($router) {
            $router->get('/', 'Admin\\BranchController@index');
            $router->post('/', 'Admin\\BranchController@store');
            $router->get('/{id}', 'Admin\\BranchController@show');
            $router->put('/{id}', 'Admin\\BranchController@update');
            $router->delete('/{id}', 'Admin\\BranchController@destroy');
            $router->get('/{id}/facilities', 'Admin\\BranchController@facilities');
            $router->post('/{id}/facilities', 'Admin\\BranchController@updateFacilities');
        });

        // Supplier Management
        $router->group(['prefix' => 'suppliers'], function () use ($router) {
            $router->get('/', 'SupplierController@index');
            $router->post('/', 'SupplierController@store');
            $router->get('/{id}', 'SupplierController@show');
            $router->put('/{id}', 'SupplierController@update');
            $router->delete('/{id}', 'SupplierController@destroy');
        });

        // Stock Movement Logs
        $router->get('/stock-movements', 'StockMovementController@index');
        $router->post('/stock-movements', 'StockMovementController@store');

        // Banner Management
        $router->group(['prefix' => 'banners'], function () use ($router) {
            $router->get('/', 'BannerController@index');
            $router->post('/', 'BannerController@store');
            $router->post('/reorder', 'BannerController@reorder');
            $router->post('/{id}/image', 'BannerController@uploadImage');
            $router->get('/{id}', 'BannerController@show');
            $router->put('/{id}', 'BannerController@update');
            $router->delete('/{id}', 'BannerController@destroy');
        });

        // Reports
        $router->group(['prefix' => 'reports'], function () use ($router) {
            $router->get('/revenue', 'Admin\\ReportController@revenue');
            $router->get('/debug-data', 'Admin\\ReportController@debugData');
            $router->get('/profit-loss', 'Admin\\ReportController@profitLoss');
            $router->get('/bookings', 'Admin\\ReportController@bookings');
            $router->get('/therapist-performance', 'Admin\\ReportController@therapistPerformance');
            $router->get('/popular-services', 'Admin\\ReportController@popularServices');
            $router->get('/financial-summary', 'Admin\\ReportController@financialSummary');
            $router->get('/tax-summary', 'Admin\\ReportController@taxSummary');
            $router->get('/payment-summary', 'Admin\\ReportController@paymentSummary');
            $router->get('/payment-log', 'Admin\\ReportController@paymentLog');
            $router->get('/tips-summary', 'Admin\\ReportController@tipsSummary');
            $router->get('/discount-summary', 'Admin\\ReportController@discountSummary');
            $router->get('/unsettled-invoices', 'Admin\\ReportController@unsettledInvoices');
            $router->get('/cash-register-movement', 'Admin\\ReportController@cashRegisterMovement');
            $router->get('/sales-profit-loss', 'Admin\\ReportController@salesProfitLoss');
            $router->get('/cash-flow', 'Admin\\ReportController@cashFlow');
            $router->get('/dp-report', 'Admin\\ReportController@dpReport');

            // Sales Reports (Laporan Penjualan)
            $router->get('/sales/item', 'Admin\\SalesReportController@salesItem');
            $router->get('/sales/by-item', 'Admin\\SalesReportController@salesByItem');
            $router->get('/sales/by-item-detail', 'Admin\\SalesReportController@salesByItemDetail');
            $router->get('/sales/by-type', 'Admin\\SalesReportController@salesByType');
            $router->get('/sales/by-service', 'Admin\\SalesReportController@salesByService');
            $router->get('/sales/by-product', 'Admin\\SalesReportController@salesByProduct');
            $router->get('/sales/by-location', 'Admin\\SalesReportController@salesByLocation');
            $router->get('/sales/by-channel', 'Admin\\SalesReportController@salesByChannel');
            $router->get('/sales/by-customer', 'Admin\\SalesReportController@salesByCustomer');
            $router->get('/sales/by-staff-detailed', 'Admin\\SalesReportController@salesByStaffDetailed');
            $router->get('/sales/by-staff', 'Admin\\SalesReportController@salesByStaff');
            $router->get('/sales/by-hour', 'Admin\\SalesReportController@salesByHour');
            $router->get('/sales/by-hour-per-day', 'Admin\\SalesReportController@salesByHourPerDay');
            $router->get('/sales/per-day', 'Admin\\SalesReportController@salesPerDay');
            $router->get('/sales/per-month', 'Admin\\SalesReportController@salesPerMonth');
            $router->get('/sales/per-quarter', 'Admin\\SalesReportController@salesPerQuarter');
            $router->get('/sales/per-year', 'Admin\\SalesReportController@revenuePerYear');
            $router->get('/sales/log', 'Admin\\SalesReportController@salesLog');
            $router->get('/sales/item-by-date', 'Admin\\SalesReportController@salesItemByDate');
            $router->get('/sales/by-service-package', 'Admin\\SalesReportController@salesByServicePackage');
            $router->get('/sales/by-category', 'Admin\\SalesReportController@salesByServiceCategory');
            $router->get('/sales/by-service-variant', 'Admin\\SalesReportController@salesByServiceVariant');
            $router->get('/sales/by-product-variant', 'Admin\\SalesReportController@salesByProductVariant');
            $router->get('/sales/refund', 'Admin\\SalesReportController@salesRefund');
            $router->get('/sales/cancelled', 'Admin\\SalesReportController@salesCancelled');
            $router->get('/sales/by-service-detailed', 'Admin\\SalesReportController@salesByServiceDetailed');

            // Inventory Reports (Laporan Inventori)
            $router->get('/inventory/current-stock', 'Admin\\InventoryReportController@currentStock');
            $router->get('/inventory/product-performance', 'Admin\\InventoryReportController@productPerformance');
            $router->get('/inventory/stock-movement-log', 'Admin\\InventoryReportController@stockMovementLog');
            $router->get('/inventory/stock-calculation', 'Admin\\InventoryReportController@stockCalculation');
            $router->get('/inventory/product-consumption', 'Admin\\InventoryReportController@productConsumption');
            $router->get('/inventory/product-consumption/{productId}', 'Admin\\InventoryReportController@productConsumptionDetail');

            // Voucher Reports (Laporan Voucher)
            $router->get('/voucher/remaining-balance', 'Admin\\VoucherReportController@remainingBalance');
            $router->get('/voucher/sales', 'Admin\\VoucherReportController@voucherSales');
            $router->get('/voucher/usage', 'Admin\\VoucherReportController@voucherUsage');
            $router->get('/voucher/promo-codes', 'Admin\\VoucherReportController@promoCodes');
            $router->get('/voucher/promo-redemption', 'Admin\\VoucherReportController@promoRedemption');
            $router->get('/voucher/free-product-redemption', 'Admin\\VoucherReportController@freeProductRedemption');
            $router->get('/voucher/membership-expiry', 'Admin\\VoucherReportController@membershipExpiry');

            // Staff Reports (Laporan Staff)
            $router->get('/staff/attendance', 'Admin\\StaffReportController@attendance');
            $router->get('/staff/tips', 'Admin\\StaffReportController@tips');
            $router->get('/staff/commission-summary', 'Admin\\StaffReportController@commissionSummary');
            $router->get('/staff/commission-detailed', 'Admin\\StaffReportController@commissionDetailed');
            $router->get('/staff/commission-item-group', 'Admin\\StaffReportController@commissionItemGroup');

            // Agenda Reports (Laporan Agenda)
            $router->get('/agenda/kalkulasi', 'Admin\\AgendaReportController@kalkulasi');
            $router->get('/agenda/pembatalan', 'Admin\\AgendaReportController@pembatalan');
            $router->get('/agenda/detail', 'Admin\\AgendaReportController@detail');

            // Customer Reports (Laporan Pelanggan)
            $router->get('/customer/list', 'Admin\\CustomerReportController@daftar');
            $router->get('/customer/retention', 'Admin\\CustomerReportController@retensi');

            // Loyalty Reports (Poin Loyalitas)
            $router->get('/loyalty/free-items', 'Admin\LoyaltyReportController@freeItems');
            $router->get('/loyalty/available-points', 'Admin\LoyaltyReportController@availablePoints');
            $router->get('/loyalty/discounts', 'Admin\LoyaltyReportController@discounts');

            // New Loyalty Management Routes
            $router->get('/loyalty/transactions', 'Admin\LoyaltyController@transactions');
            $router->get('/loyalty/members', 'Admin\LoyaltyController@members');
            $router->get('/loyalty/stats', 'Admin\LoyaltyController@stats');
        });

        // Feedback Management
        $router->group(['prefix' => 'feedbacks'], function () use ($router) {
            $router->get('/', 'Admin\\FeedbackController@index');
            $router->post('/{id}/reply', 'Admin\\FeedbackController@reply');
        });

        // User Management (Admin/Cashier accounts)
        $router->group(['prefix' => 'users'], function () use ($router) {
            $router->get('/available-staff', 'Admin\\UserController@getAvailableStaff');
            $router->get('/', 'Admin\\UserController@index');
            $router->post('/', 'Admin\\UserController@store');
            $router->get('/{id}', 'Admin\\UserController@show');
            $router->put('/{id}', 'Admin\\UserController@update');
            $router->delete('/{id}', 'Admin\\UserController@destroy');
        });

        // Payment Configuration (Admin only)
        $router->group(['prefix' => 'payment-configs'], function () use ($router) {
            $router->get('/', 'Admin\\PaymentConfigController@index');
            $router->get('/branch/{branchId}', 'Admin\\PaymentConfigController@show');
            $router->post('/', 'Admin\\PaymentConfigController@store');
            $router->put('/{id}', 'Admin\\PaymentConfigController@update');
            $router->delete('/{id}', 'Admin\\PaymentConfigController@destroy');
            $router->post('/test-connection', 'Admin\\PaymentConfigController@testConnection');
        });

        // Company Settings
        $router->group(['prefix' => 'company'], function () use ($router) {
            $router->get('/settings', 'Admin\\CompanySettingsController@show');
            $router->put('/settings', 'Admin\\CompanySettingsController@update');
            $router->put('/intervals', 'Admin\\CompanySettingsController@updateIntervals');
        });

        // Queue Settings
        $router->group(['prefix' => 'queue'], function () use ($router) {
            $router->get('/settings', 'Admin\\QueueSettingsController@show');
            $router->put('/settings', 'Admin\\QueueSettingsController@update');
        });

        // Invoice Settings
    $router->group(['prefix' => 'invoice'], function () use ($router) {
    $router->get('/settings', 'Admin\\InvoiceSettingController@show');
    $router->put('/settings', 'Admin\\InvoiceSettingController@update');
    $router->post('/logo', 'Admin\\InvoiceSettingController@uploadLogo'); // ← tambah ini
});

        // Loyalty Settings
        $router->group(['prefix' => 'loyalty'], function () use ($router) {
            $router->get('/settings', 'Admin\\LoyaltyProgramSettingsController@show');
            $router->put('/settings', 'Admin\\LoyaltyProgramSettingsController@update');
        });

        // Cancellation Reasons
        $router->group(['prefix' => 'cancellation-reasons'], function () use ($router) {
            $router->get('/', 'Admin\\CancellationReasonController@index');
            $router->post('/', 'Admin\\CancellationReasonController@store');
            $router->put('/{id}', 'Admin\\CancellationReasonController@update');
            $router->delete('/{id}', 'Admin\\CancellationReasonController@destroy');
        });

        // Bank Accounts (Bank Payment Configs)
        $router->group(['prefix' => 'bank-accounts'], function () use ($router) {
            $router->get('/', 'Admin\\BankAccountController@index');
            $router->post('/', 'Admin\\BankAccountController@store');
            $router->delete('/{id}', 'Admin\\BankAccountController@destroy');
        });


        // Marketing - Promos/Vouchers
        $router->group(['prefix' => 'promos'], function () use ($router) {
            $router->get('/', 'Admin\\PromoController@index');
            $router->post('/', 'Admin\\PromoController@store');
            $router->get('/{id}', 'Admin\\PromoController@show');
            $router->put('/{id}', 'Admin\\PromoController@update');
            $router->delete('/{id}', 'Admin\\PromoController@destroy');
            $router->post('/validate', 'Admin\\PromoController@validateCode');
        });

        // Marketing - News/Articles
        $router->group(['prefix' => 'news'], function () use ($router) {
            $router->get('/', 'Admin\\NewsController@index');
            $router->post('/', 'Admin\\NewsController@store');
            $router->get('/{id}', 'Admin\\NewsController@show');
            $router->put('/{id}', 'Admin\\NewsController@update');
            $router->delete('/{id}', 'Admin\\NewsController@destroy');
        });

        // Marketing - News Categories
        $router->group(['prefix' => 'news-categories'], function () use ($router) {
            $router->get('/', 'Admin\\NewsCategoryController@index');
            $router->post('/', 'Admin\\NewsCategoryController@store');
            $router->put('/{id}', 'Admin\\NewsCategoryController@update');
            $router->delete('/{id}', 'Admin\\NewsCategoryController@destroy');
        });

        // Marketing - Campaign Types
        $router->group(['prefix' => 'campaign-types'], function () use ($router) {
            $router->get('/', 'Admin\\CampaignTypeController@index');
            $router->post('/', 'Admin\\CampaignTypeController@store');
            $router->put('/{id}', 'Admin\\CampaignTypeController@update');
            $router->delete('/{id}', 'Admin\\CampaignTypeController@destroy');
        });

        // Marketing - Campaigns
        $router->group(['prefix' => 'campaigns'], function () use ($router) {
            $router->get('/preview-audience', 'Admin\\CampaignController@previewAudience');
            $router->get('/', 'Admin\\CampaignController@index');
            $router->post('/', 'Admin\\CampaignController@store');
            $router->get('/{id}', 'Admin\\CampaignController@show');
            $router->put('/{id}', 'Admin\\CampaignController@update');
            $router->delete('/{id}', 'Admin\\CampaignController@destroy');
            $router->post('/{id}/toggle-status', 'Admin\\CampaignController@toggleStatus');
            $router->post('/{id}/image', 'Admin\\CampaignController@uploadImage');
        });

        // Marketing - Automation Rules
        $router->group(['prefix' => 'automations'], function () use ($router) {
            $router->get('/', 'Admin\\AutomationController@index');
            $router->post('/', 'Admin\\AutomationController@store');
            $router->get('/{id}', 'Admin\\AutomationController@show');
            $router->put('/{id}', 'Admin\\AutomationController@update');
            $router->delete('/{id}', 'Admin\\AutomationController@destroy');
            $router->post('/{id}/toggle', 'Admin\\AutomationController@toggleStatus');
        });

        // Audit Logs
        $router->group(['prefix' => 'audit-logs'], function () use ($router) {
            $router->get('/', 'Admin\\AuditLogController@index');
            $router->get('/modules', 'Admin\\AuditLogController@getModules');
            $router->get('/users', 'Admin\\AuditLogController@getUsers');
            $router->get('/export', 'Admin\\AuditLogController@export');
            $router->get('/{id}', 'Admin\\AuditLogController@show');
        });

        // Marketing - WhatsApp Manual Triggers
        $router->group(['prefix' => 'whatsapp'], function () use ($router) {
            $router->post('/promo', 'Admin\\WhatsAppController@sendPromo');
            $router->post('/birthday', 'Admin\\WhatsAppController@sendBirthday');
            $router->post('/send-text', 'Admin\\WhatsAppController@sendText');
        });

        // Role Management
        $router->group(['prefix' => 'roles'], function () use ($router) {
            $router->get('/', 'Admin\\RoleController@index');
            $router->get('/permissions', 'Admin\\RoleController@getPermissions');
            $router->post('/', 'Admin\\RoleController@store');
            $router->post('/seed-defaults', 'Admin\\RoleController@seedDefaults');
            $router->get('/{id}', 'Admin\\RoleController@show');
            $router->put('/{id}', 'Admin\\RoleController@update');
            $router->delete('/{id}', 'Admin\\RoleController@destroy');
        });

        // Subscription Management
        $router->group(['prefix' => 'subscriptions'], function () use ($router) {
            $router->get('/overview', 'Admin\\SubscriptionController@overview');
            $router->get('/plans', 'Admin\\SubscriptionController@getPlans');
            $router->put('/plans', 'Admin\\SubscriptionController@updatePlans');
            $router->post('/plans/seed', 'Admin\\SubscriptionController@seedPlans');
            $router->get('/status', 'Admin\\SubscriptionController@getStatus');
            $router->get('/history', 'Admin\\SubscriptionController@getHistory');
            $router->post('/subscribe', 'Admin\\SubscriptionController@subscribe');
            $router->post('/unsubscribe', 'Admin\\SubscriptionController@unsubscribe');
            $router->post('/{id}/confirm-payment', 'Admin\\SubscriptionController@confirmPayment');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Owner Routes - Additional routes with full access
    |--------------------------------------------------------------------------
    */
    $router->group(['middleware' => ['auth', 'role:owner,super_admin'], 'prefix' => 'owner'], function () use ($router) {
        // Financial Reports
        $router->get('/reports/financial-summary', 'Owner\\ReportController@financialSummary');
        $router->get('/reports/branch-comparison', 'Owner\\ReportController@branchComparison');

        // Audit Logs
        $router->get('/audit-logs', 'Owner\\AuditController@index');
    });
});

/*
|--------------------------------------------------------------------------
| OpenAPI Routes - For WhatsApp Bot Integration
|--------------------------------------------------------------------------
| These routes use Basic Authentication instead of JWT
| Credentials are configured in .env: OPENAPI_USERNAME and OPENAPI_PASSWORD
*/
$router->group(['prefix' => 'openapi', 'middleware' => 'basicauth'], function () use ($router) {
    $router->post('/register',                    'OpenApiController@register');
    $router->get('/availability',                 'OpenApiController@availability');
    $router->post('/reservations',                'OpenApiController@createReservation');
    $router->put('/reservations/{id}/reschedule', 'OpenApiController@reschedule');
    $router->post('/payments/initiate',           'OpenApiController@initiatePayment'); // ← TAMBAH INI, harus SEBELUM /payments
    $router->post('/payments',                    'OpenApiController@createPayment');
    $router->get('/payments/{id}/status',         'OpenApiController@checkPaymentStatus');
});

/*
|--------------------------------------------------------------------------
| Public API Routes (No Auth) - For Landing Page Integration
|--------------------------------------------------------------------------
*/
// Submit lead from landing page contact/demo form
$router->post('/api/v1/leads', 'LeadController@store');

// Get subscription plans (public - for pricing page)
$router->get('/api/v1/plans', 'Admin\SubscriptionController@getPlans');

// Self-service onboarding for new tenants
$router->post('/api/v1/onboarding/register', 'TenantOnboardingController@register');

/*
|--------------------------------------------------------------------------
| Admin CRM Routes - Lead Management (Requires Auth)
|--------------------------------------------------------------------------
*/
$router->group(['middleware' => ['auth', 'role:admin,owner,super_admin'], 'prefix' => 'api/v1/admin'], function () use ($router) {
    $router->get('/leads', 'LeadController@index');
    $router->get('/leads/stats', 'LeadController@stats');
    $router->get('/leads/{id}', 'LeadController@show');
    $router->put('/leads/{id}', 'LeadController@update');
    $router->delete('/leads/{id}', 'LeadController@destroy');
});


