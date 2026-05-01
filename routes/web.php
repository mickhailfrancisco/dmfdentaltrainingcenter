<?php

use App\Http\Controllers\BankTransferController;
use App\Http\Controllers\BankTransferProofController;
use App\Http\Controllers\EnrollmentBalanceController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\PaymentLinkController;
use App\Http\Controllers\PaymongoWebhookController;
use App\Http\Controllers\ResumeCheckoutController;
use Illuminate\Support\Facades\Route;

Route::controller(EnrollmentController::class)->group(function () {
    Route::get('/', 'landing')->name('home');
    Route::get('/enroll', 'form')->name('enroll.form');
    Route::post('/enroll', 'store')->name('enroll.store');
    Route::get('/enroll/payment', 'payment')->name('enroll.payment');
    Route::post('/enroll/pay', 'pay')->name('enroll.pay');
    Route::get('/enroll/success', 'success')->name('enroll.success');
    Route::get('/enroll/cancel', 'cancel')->name('enroll.cancel');
});

Route::get('/enroll/balance/{reference_number}', [EnrollmentBalanceController::class, 'show'])
    ->middleware('signed')
    ->name('enroll.balance');

Route::post('/enroll/balance/{reference_number}/pay', [EnrollmentBalanceController::class, 'pay'])
    ->middleware('signed')
    ->name('enroll.balance.pay');

Route::get('/enroll/bank-transfer/{reference_number}/{purpose}', [BankTransferController::class, 'show'])
    ->middleware('signed')
    ->name('enroll.bank-transfer.show');

Route::post('/enroll/bank-transfer/{reference_number}/{purpose}', [BankTransferController::class, 'submit'])
    ->middleware('signed')
    ->name('enroll.bank-transfer.submit');

Route::get('/admin/bank-transfer-submissions/{submission}/proof/{slot?}', [BankTransferProofController::class, 'show'])
    ->middleware('auth')
    ->name('admin.bank-transfer-submissions.proof');

Route::get('/enroll/checkout/{reference_number}', [ResumeCheckoutController::class, 'show'])
    ->middleware('signed')
    ->name('enroll.checkout');

Route::post('/enroll/checkout/{reference_number}/pay', [ResumeCheckoutController::class, 'pay'])
    ->middleware('signed')
    ->name('enroll.checkout.pay');

Route::get('/enroll/pay-link/{reference_number}/{purpose}/{payment_method}', [PaymentLinkController::class, 'redirect'])
    ->middleware('signed')
    ->name('enroll.pay-link');

Route::post('/webhooks/paymongo', [PaymongoWebhookController::class, 'handle'])
    ->name('webhooks.paymongo');

// Admin panel root redirect
Route::redirect('/admin', '/admin/enrollments');
