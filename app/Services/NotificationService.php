<?php

namespace App\Services;

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
}
