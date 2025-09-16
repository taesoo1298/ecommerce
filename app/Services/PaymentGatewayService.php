<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    /**
     * 결제 처리
     *
     * @param array $paymentData
     * @return array
     */
    public function processPayment(array $paymentData): array
    {
        // 실제 구현에서는 결제 게이트웨이 API와 통합
        try {
            // 개발 및 테스트 환경에서는 가짜 결제 응답 반환
            if (app()->environment('local', 'testing')) {
                return $this->mockPaymentResponse($paymentData);
            }

            // 실제 환경에서는 결제 게이트웨이 API 호출
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.payment_gateway.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.payment_gateway.endpoint'), [
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'],
                'order_id' => $paymentData['order_id'],
                'customer_id' => $paymentData['customer_id'],
                'payment_method' => $paymentData['payment_method'],
                'description' => '주문 #' . $paymentData['order_id'],
                'return_url' => route('payment.callback'),
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'transaction_id' => $result['transaction_id'],
                    'details' => $result,
                ];
            } else {
                Log::error('결제 게이트웨이 오류', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'request' => $paymentData,
                ]);

                return [
                    'success' => false,
                    'message' => '결제 처리 중 오류가 발생했습니다: ' . ($response->json()['message'] ?? '알 수 없는 오류'),
                ];
            }
        } catch (\Exception $e) {
            Log::error('결제 처리 예외', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $paymentData,
            ]);

            return [
                'success' => false,
                'message' => '결제 처리 중 오류가 발생했습니다: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 결제 취소
     *
     * @param string $transactionId
     * @param float|null $amount
     * @return array
     */
    public function refundPayment(string $transactionId, ?float $amount = null): array
    {
        try {
            // 개발 및 테스트 환경에서는 가짜 환불 응답 반환
            if (app()->environment('local', 'testing')) {
                return [
                    'success' => true,
                    'refund_id' => 'ref_' . uniqid(),
                    'details' => [
                        'status' => 'refunded',
                        'refunded_amount' => $amount,
                        'transaction_id' => $transactionId,
                    ],
                ];
            }

            // 실제 환경에서는 결제 게이트웨이 환불 API 호출
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.payment_gateway.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.payment_gateway.refund_endpoint'), [
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'refund_id' => $result['refund_id'],
                    'details' => $result,
                ];
            } else {
                Log::error('환불 처리 오류', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'transaction_id' => $transactionId,
                ]);

                return [
                    'success' => false,
                    'message' => '환불 처리 중 오류가 발생했습니다: ' . ($response->json()['message'] ?? '알 수 없는 오류'),
                ];
            }
        } catch (\Exception $e) {
            Log::error('환불 처리 예외', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => false,
                'message' => '환불 처리 중 오류가 발생했습니다: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 가짜 결제 응답 생성 (개발/테스트용)
     *
     * @param array $paymentData
     * @return array
     */
    private function mockPaymentResponse(array $paymentData): array
    {
        // 가상의 실패 시나리오 (1% 확률)
        if (rand(1, 100) === 1) {
            return [
                'success' => false,
                'message' => '결제가 거부되었습니다 (테스트 모드)',
            ];
        }

        // 성공 시나리오
        return [
            'success' => true,
            'transaction_id' => 'txn_' . uniqid(),
            'details' => [
                'status' => 'approved',
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'],
                'payment_method' => $paymentData['payment_method'],
                'order_id' => $paymentData['order_id'],
                'customer_id' => $paymentData['customer_id'],
                'created_at' => now()->toIso8601String(),
            ],
        ];
    }
}
