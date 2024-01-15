<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DownloadController;
use Laravel\Nova\Http\Controllers\ForgotPasswordController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/home', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('vendor/nova/auth/login');
});

Route::get(
    '/download-job-file/{appraisalJobFile}',
    [DownloadController::class, 'downloadAppraisalJobFile'])
    ->name('download-job-file');

Route::get(
    '/download-rejected-job-file/{appraisalJobRejection}',
    [DownloadController::class, 'downloadRejectedAppraisalJobFile'])
    ->name('download-rejected-job-file');

Route::get('reset-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.reset');

Route::get('/pdf/appraiser-invoice/{appraiserId}/year/{year}/month/{month}', [\App\Http\Controllers\PDFController::class, 'appraiserInvoice']);
Route::get('/pdf/client-invoice/{clientId}/year/{year}/month/{month}', [\App\Http\Controllers\PDFController::class, 'clientInvoice']);