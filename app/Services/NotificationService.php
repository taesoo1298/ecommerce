<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * 이메일 발송
     *
     * @param string $to 수신자 이메일
     * @param string $subject 제목
     * @param string $content 내용
     * @return array
     */
    public function sendEmail(string $to, string $subject, string $content): array
    {
        try {
            // 개발 및 테스트 환경에서는 이메일 전송 생략
            if (app()->environment('local', 'testing')) {
                Log::info('이메일 전송 생략 (개발 모드)', [
                    'to' => $to,
                    'subject' => $subject,
                ]);
                return [
                    'success' => true,
                    'message' => '이메일 전송이 시뮬레이션 되었습니다 (개발 모드)',
                ];
            }

            // Laravel의 내장 메일 기능 사용
            Mail::raw($content, function ($message) use ($to, $subject) {
                $message->to($to)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return [
                'success' => true,
                'message' => '이메일이 성공적으로 전송되었습니다.',
            ];
        } catch (\Exception $e) {
            Log::error('이메일 전송 오류', [
                'to' => $to,
                'subject' => $subject,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => '이메일 전송 중 오류가 발생했습니다: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * SMS 발송
     *
     * @param string $to 수신자 전화번호
     * @param string $content 내용
     * @return array
     */
    public function sendSms(string $to, string $content): array
    {
        try {
            // 개발 및 테스트 환경에서는 SMS 전송 생략
            if (app()->environment('local', 'testing')) {
                Log::info('SMS 전송 생략 (개발 모드)', [
                    'to' => $to,
                    'content' => $content,
                ]);
                return [
                    'success' => true,
                    'message' => 'SMS 전송이 시뮬레이션 되었습니다 (개발 모드)',
                ];
            }

            // 실제 환경에서는 SMS 서비스 API 호출
            // 여기서는 예시로 외부 SMS API를 호출하는 방식을 보여줍니다.
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.sms.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.sms.endpoint'), [
                'to' => $to,
                'message' => $content,
                'from' => config('services.sms.sender'),
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'SMS가 성공적으로 전송되었습니다.',
                    'details' => $response->json(),
                ];
            } else {
                Log::error('SMS 전송 API 오류', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'to' => $to,
                ]);

                return [
                    'success' => false,
                    'message' => 'SMS 전송 중 오류가 발생했습니다: ' . ($response->json()['message'] ?? '알 수 없는 오류'),
                ];
            }
        } catch (\Exception $e) {
            Log::error('SMS 전송 오류', [
                'to' => $to,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS 전송 중 오류가 발생했습니다: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 주문 실패 알림 전송
     *
     * @param \App\Models\Order $order 주문 객체
     * @param string $failureType 실패 유형
     * @param string $errorMessage 오류 메시지
     * @return array 전송 결과
     */
    public function sendFailureNotification($order, string $failureType, string $errorMessage): array
    {
        $customer = $order->customer;

        // 이메일 알림 전송
        $subject = "주문 처리 실패 안내 - 주문번호: {$order->order_number}";
        $content = $this->buildFailureEmailContent($order, $failureType, $errorMessage);
        $emailResult = $this->sendEmail($customer->email, $subject, $content);

        // SMS 알림 전송 (고객이 전화번호가 있는 경우)
        $smsResult = ['success' => false, 'message' => '전화번호 없음'];
        if ($customer->phone) {
            $smsContent = "[주문 실패] 주문번호: {$order->order_number}, 실패 사유: {$failureType}. 자세한 내용은 이메일을 확인해주세요.";
            $smsResult = $this->sendSms($customer->phone, $smsContent);
        }

        // 알림 로그 저장
        if ($emailResult['success']) {
            Notification::create([
                'order_id' => $order->id,
                'type' => 'email',
                'recipient' => $customer->email,
                'subject' => $subject,
                'content' => $content,
                'is_sent' => true,
                'sent_at' => now(),
            ]);
        }

        if ($customer->phone && $smsResult['success']) {
            Notification::create([
                'order_id' => $order->id,
                'type' => 'sms',
                'recipient' => $customer->phone,
                'content' => $smsContent,
                'is_sent' => true,
                'sent_at' => now(),
            ]);
        }

        return [
            'email' => $emailResult,
            'sms' => $smsResult
        ];
    }

    /**
     * 실패 이메일 내용 생성
     *
     * @param \App\Models\Order $order 주문 객체
     * @param string $failureType 실패 유형
     * @param string $errorMessage 오류 메시지
     * @return string 이메일 내용
     */
    private function buildFailureEmailContent($order, string $failureType, string $errorMessage): string
    {
        $content = "안녕하세요, {$order->customer->name}님.\n\n";
        $content .= "주문 처리 중 오류가 발생했습니다.\n\n";
        $content .= "주문 번호: {$order->order_number}\n";
        $content .= "실패 유형: {$failureType}\n";
        $content .= "오류 내용: {$errorMessage}\n\n";

        $content .= "주문 상품:\n";
        foreach ($order->items as $item) {
            $content .= "- {$item->product->name}: {$item->quantity}개, {$item->price}원/개, 소계: {$item->total}원\n";
        }

        $content .= "\n이 문제를 해결하기 위해 고객센터에 문의해 주세요.";
        $content .= "\n감사합니다.";

        return $content;
    }
}
