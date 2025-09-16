<?php

use App\Http\Controllers\OrderController;
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

// 주문 API 라우트
Route::prefix('orders')->group(function () {
    // 주문 생성
    Route::post('/', [OrderController::class, 'store']);

    // 주문 조회
    Route::get('/{id}', [OrderController::class, 'show']);

    // 주문 취소
    Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
});
