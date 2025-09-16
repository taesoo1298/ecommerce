<?php

namespace App\Services\Kafka;

use Illuminate\Support\Facades\Log;
use RdKafka\Conf;
use RdKafka\Producer;

class KafkaProducerService
{
    /**
     * @var Producer
     */
    private $producer;

    /**
     * @var string
     */
    private $brokers;

    /**
     * KafkaProducerService 생성자
     */
    public function __construct()
    {
        $this->brokers = config('kafka.brokers');
        $this->initProducer();
    }

    /**
     * Kafka Producer 초기화
     */
    private function initProducer(): void
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', $this->brokers);
        $conf->set('socket.timeout.ms', 50);
        $conf->set('queue.buffering.max.messages', 1000000);
        $conf->set('message.send.max.retries', 3);
        $conf->set('retry.backoff.ms', 200);

        // 메시지 전송 완료 콜백 함수 설정
        $conf->setDrMsgCb(function ($kafka, $message) {
            if ($message->err) {
                Log::error('Kafka 메시지 전송 실패', [
                    'error' => rd_kafka_err2str($message->err),
                    'topic' => $message->topic_name,
                    'partition' => $message->partition,
                    'offset' => $message->offset,
                ]);
            }
        });

        // 오류 콜백 함수 설정
        $conf->setErrorCb(function ($kafka, $err, $reason) {
            Log::error('Kafka 오류 발생', [
                'error_code' => $err,
                'reason' => $reason,
            ]);
        });

        $this->producer = new Producer($conf);
    }

    /**
     * 메시지 발행
     *
     * @param string $topic 토픽 이름
     * @param mixed $message 발행할 메시지 (자동으로 JSON으로 변환됨)
     * @param string|null $key 메시지 키 (옵션)
     * @param int $partition 파티션 번호 (기본값: RD_KAFKA_PARTITION_UA)
     * @return bool
     */
    public function publish(string $topic, $message, ?string $key = null, int $partition = RD_KAFKA_PARTITION_UA): bool
    {
        try {
            // 토픽 객체 생성
            $topic = $this->producer->newTopic($topic);

            // 메시지를 JSON으로 직렬화
            $payload = json_encode($message);
            if ($payload === false) {
                throw new \Exception('메시지 JSON 직렬화 실패');
            }

            // 메시지 발행
            $topic->produce($partition, 0, $payload, $key);

            // 큐에 있는 메시지 처리
            $this->producer->poll(0);

            return true;
        } catch (\Exception $e) {
            Log::error('Kafka 메시지 발행 실패', [
                'topic' => $topic,
                'message' => $message,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 모든 메시지가 전송될 때까지 대기
     *
     * @param int $timeout_ms 타임아웃 (밀리초)
     * @return void
     */
    public function flush(int $timeout_ms = 1000): void
    {
        $this->producer->flush($timeout_ms);
    }
}
