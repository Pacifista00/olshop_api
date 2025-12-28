<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MidtransController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/product/{id}', [ProductController::class, 'getProduct']);
Route::get('/products/latest', [ProductController::class, 'latest']);
Route::get('/products/best-seller', [ProductController::class, 'bestSeller']);

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses/store', [AddressController::class, 'store']);
    Route::put('/addresses/update/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/delete/{address}', [AddressController::class, 'destroy']);

    Route::post('/category/store', [CategoryController::class, 'store']);
    Route::put('/category/update/{category}', [CategoryController::class, 'update']);
    Route::delete('/category/delete/{category}', [CategoryController::class, 'destroy']);

    Route::post('/product/store', [ProductController::class, 'store']);
    Route::put('/product/update/{product}', [ProductController::class, 'update']);
    Route::delete('/product/delete/{product}', [ProductController::class, 'destroy']);

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/store', [CartController::class, 'store']);
    Route::put('/cart/update/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/delete/{cartItem}', [CartController::class, 'destroy']);

    Route::post('/voucher/store', [VoucherController::class, 'store']);
    Route::put('/voucher/update/{voucher}', [VoucherController::class, 'update']);
    Route::delete('/voucher/delete/{voucher}', [VoucherController::class, 'destroy']);
    Route::get('/voucher/show/{voucher}', [VoucherController::class, 'show']);
    Route::post('/voucher/preview', [VoucherController::class, 'preview']);

    Route::post('/checkout', [OrderController::class, 'checkout']);

    Route::post('/midtrans/webhook', [MidtransController::class, 'handle']);
});



Route::get('/vouchers', [VoucherController::class, 'index']);
