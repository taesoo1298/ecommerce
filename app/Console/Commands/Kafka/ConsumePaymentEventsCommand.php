<?php

namespace App\Console\Commands\Kafka;

use App\Services\Kafka\KafkaConsumerService;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConsumePaymentEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:consume-payment-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '재고 차감 이벤트를 구독하여 결제 처리를 수행합니다.';

    /**
     * @var KafkaConsumerService
     */
    private $consumer;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * Create a new command instance.
     */
    public function __construct(PaymentService $paymentService)
    {
        parent::__construct();
        $this->paymentService = $paymentService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('결제 이벤트 구독 시작...');

        // 컨슈머 그룹 ID 가져오기
        $groupId = config('kafka.consumer_groups.payment_service');

        // 컨슈머 초기화
        $this->consumer = new KafkaConsumerService($groupId);

        // 토픽 구독
        $this->consumer->subscribe([config('kafka.topics.inventory_deducted')]);

        // 종료 시그널 처리
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGTERM, [$this, 'shutdown']);

        $this->info('재고 차감 이벤트 구독 중... Ctrl+C로 종료하세요.');

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

            $this->info("주문 ID {$data['order_id']}에 대한 결제 처리 중...");

            // 결제 처리 서비스 호출
            $result = $this->paymentService->processPayment($data['order_id']);

            if ($result['success']) {
                $this->info("주문 ID {$data['order_id']}의 결제 처리 완료: {$result['amount']}원");
            } else {
                $this->error("주문 ID {$data['order_id']}의 결제 처리 실패: {$result['message']}");
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
