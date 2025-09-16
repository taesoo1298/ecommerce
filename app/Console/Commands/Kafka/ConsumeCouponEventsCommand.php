<?php

namespace App\Console\Commands\Kafka;

use App\Services\Kafka\KafkaConsumerService;
use App\Services\CouponService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConsumeCouponEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:consume-coupon-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '주문 생성 이벤트를 구독하여 쿠폰 처리를 수행합니다.';

    /**
     * @var KafkaConsumerService
     */
    private $consumer;

    /**
     * @var CouponService
     */
    private $couponService;

    /**
     * Create a new command instance.
     */
    public function __construct(CouponService $couponService)
    {
        parent::__construct();
        $this->couponService = $couponService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('쿠폰 이벤트 구독 시작...');

        // 컨슈머 그룹 ID 가져오기
        $groupId = config('kafka.consumer_groups.coupon_service');

        // 컨슈머 초기화
        $this->consumer = new KafkaConsumerService($groupId);

        // 토픽 구독
        $this->consumer->subscribe([config('kafka.topics.order_created')]);

        // 종료 시그널 처리
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGTERM, [$this, 'shutdown']);

        $this->info('주문 생성 이벤트 구독 중... Ctrl+C로 종료하세요.');

        // 메시지 처리 루프
        while (true) {
            $message = $this->consumer->consume(10000);

            if ($message === null) {
                continue;
            }

            $this->processMessage($message);
        }
    }

    /**
     * 메시지 처리
     */
    private function processMessage($message)
    {
        try {
            $data = json_decode($message->payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON 파싱 오류: ' . json_last_error_msg());
            }

            $this->info("주문 ID {$data['order_id']}에 대한 쿠폰 처리 중...");

            // 쿠폰 처리 서비스 호출
            $result = $this->couponService->processCoupon($data['order_id']);

            if ($result['success']) {
                $this->info("주문 ID {$data['order_id']}의 쿠폰 처리 완료: 할인액 {$result['discount_amount']}원");
            } else {
                $this->error("주문 ID {$data['order_id']}의 쿠폰 처리 실패: {$result['message']}");
            }

            // 메시지 커밋
            $this->consumer->commit($message);

        } catch (\Exception $e) {
            $this->error('메시지 처리 중 오류 발생: ' . $e->getMessage());
            Log::error('Kafka 메시지 처리 중 오류', [
                'message' => $message->payload ?? null,
                'exception' => $e->getMessage(),
            ]);

            // 오류가 있어도 메시지 커밋 (중복 처리 방지)
            // 실제 운영 환경에서는 DLQ(Dead Letter Queue)로 보내는 것이 좋음
            $this->consumer->commit($message);
        }
    }

    /**
     * 안전하게 종료
     */
    public function shutdown()
    {
        $this->info('종료 요청 수신. 안전하게 종료합니다...');
        $this->consumer->unsubscribe();
        $this->consumer->close();
        exit(0);
    }
}
