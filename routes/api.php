<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\BiteshipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedeemController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\RewardRedemptionController;
use App\Http\Controllers\UserManagementController;
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

Route::get('/test-email', function () {
    $response = Http::withHeaders([
        'accept' => 'application/json',
        'api-key' => env('BREVO_API_KEY'),
        'content-type' => 'application/json',
    ])->post('https://api.brevo.com/v3/smtp/email', [
                "sender" => [
                    "name" => "WawaNet",
                    "email" => "warungnyawarganet@gmail.com"
                ],
                "to" => [
                    [
                        "email" => "adamsamudera99@gmail.com",
                        "name" => "Test"
                    ]
                ],
                "subject" => "Test Email",
                "htmlContent" => "<h1>Email dari Laravel + Brevo API</h1>"
            ]);

    return $response->body();
});

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
Route::get('/home-products', [ProductController::class, 'homeProducts']);
Route::get('/product/{id}', [ProductController::class, 'getProduct']);
Route::get('/category/{id}', [CategoryController::class, 'getCategory']);
Route::get('/voucher/{id}', [VoucherController::class, 'getVoucher']);
Route::get('/products/latest', [ProductController::class, 'latest']);
Route::get('/products/best-seller', [ProductController::class, 'bestSeller']);

Route::get('/rewards', [RewardController::class, 'index']);
Route::get('/reward/{id}', [RewardController::class, 'show']);

Route::post('/midtrans/callback', [MidtransController::class, 'handle']);
Route::post('/biteship/webhook', [BiteshipController::class, 'handle']);
Route::post('/biteship/webhook-waybill', [BiteshipController::class, 'handleWaybill']);
Route::post('/biteship/webhook-price', [BiteshipController::class, 'handlePrice']);

Route::get('/app-version', [AppSettingController::class, 'index']);

Route::get('/regions/provinces', [RegionController::class, 'provinces']);
Route::get('/regions/cities/{provinceId}', [RegionController::class, 'cities']);


Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/me', [ProfileController::class, 'me']);

    Route::put('/profile/update', [ProfileController::class, 'update']);
    Route::put('/profile/photo/update', [ProfileController::class, 'updatePhoto']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::get('/addresses/{address}', [AddressController::class, 'show']);
    Route::post('/addresses/store', [AddressController::class, 'store']);
    Route::put('/addresses/update/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/delete/{address}', [AddressController::class, 'destroy']);

    Route::post('/category/store', [CategoryController::class, 'store']);
    Route::put('/category/update/{category}', [CategoryController::class, 'update']);
    Route::delete('/category/delete/{category}', [CategoryController::class, 'destroy']);

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/store', [CartController::class, 'store']);
    Route::put('/cart/update/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/delete/{cartItem}', [CartController::class, 'destroy']);

    Route::get('/vouchers', [VoucherController::class, 'index']);
    Route::get('/voucher/show/{voucher}', [VoucherController::class, 'show']);
    Route::post('/voucher/preview', [VoucherController::class, 'preview']);

    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::post('/checkout/order/{order}', [OrderController::class, 'retry']);
    Route::post('/order/{id}/cancel', [OrderController::class, 'cancel']);

    Route::get('/preview-shipping', [BiteshipController::class, 'previewShipping']);

    Route::get('/orders/by-number/{orderNumber}', [OrderController::class, 'showByNumber']);

    Route::get('/orders', [OrderController::class, 'orders']);
    Route::get('/orders/me', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/pack', [OrderController::class, 'pack']);



    Route::post('/rewards/{id}/redeem', [RedeemController::class, 'redeem']);
    Route::get('/my-redemptions', [RewardRedemptionController::class, 'index']);
    Route::get('/my-redemptions/{id}', [RewardRedemptionController::class, 'show']);


    Route::middleware(['auth:sanctum', 'role:admin,developer'])
        ->prefix('admin')
        ->group(function () {

            Route::put('/users/{user}/update', [UserManagementController::class, 'update']);
            Route::put('/users/photo/{user}/update', [UserManagementController::class, 'updatePhoto']);

            Route::get('/products', [ProductController::class, 'adminIndex']);
            Route::get('/product/{id}', [ProductController::class, 'getAdminProduct']);
            Route::post('/product/store', [ProductController::class, 'store']);
            Route::put('/product/update/{product}', [ProductController::class, 'update']);
            Route::delete('/product/delete/{product}', [ProductController::class, 'destroy']);

            Route::get('/vouchers', [VoucherController::class, 'adminIndex']);
            Route::get('/voucher/{id}', [VoucherController::class, 'getAdminVoucher']);
            Route::post('/voucher/store', [VoucherController::class, 'store']);
            Route::put('/voucher/update/{voucher}', [VoucherController::class, 'update']);
            Route::delete('/voucher/delete/{voucher}', [VoucherController::class, 'destroy']);

            Route::post('/reward/store', [RewardController::class, 'store']);
            Route::put('/reward/update/{id}', [RewardController::class, 'update']);
            Route::delete('/reward/delete/{id}', [RewardController::class, 'destroy']);

            Route::get('/redemptions', [RewardRedemptionController::class, 'adminShow']);
            Route::get('/redemptions/voucher', [RewardRedemptionController::class, 'adminVoucherReedem']);
            Route::get('/redemptions/hotel', [RewardRedemptionController::class, 'adminHotelReedem']);
            Route::get('/redemptions/product', [RewardRedemptionController::class, 'adminProductReedem']);

            Route::post('/redemptions/product/{id}/complete', [RewardRedemptionController::class, 'updateProduct']);
            Route::post('/redemptions/hotel/{id}/complete', [RewardRedemptionController::class, 'updateHotel']);


        });
});


