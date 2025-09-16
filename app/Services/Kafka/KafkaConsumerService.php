<?php

namespace App\Services\Kafka;

use Illuminate\Support\Facades\Log;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;

class KafkaConsumerService
{
    /**
     * @var KafkaConsumer
     */
    private $consumer;

    /**
     * @var string
     */
    private $brokers;

    /**
     * @var string
     */
    private $groupId;

    /**
     * KafkaConsumerService 생성자
     *
     * @param string $groupId 컨슈머 그룹 ID
     */
    public function __construct(string $groupId)
    {
        $this->brokers = config('kafka.brokers');
        $this->groupId = $groupId;
        $this->initConsumer();
    }

    /**
     * Kafka Consumer 초기화
     */
    private function initConsumer(): void
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', $this->brokers);
        $conf->set('group.id', $this->groupId);
        $conf->set('auto.offset.reset', 'earliest'); // 처음부터 소비
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.commit.interval.ms', '1000');

        // 오류 콜백 함수 설정
        $conf->setErrorCb(function ($kafka, $err, $reason) {
            Log::error('Kafka 컨슈머 오류 발생', [
                'error_code' => $err,
                'reason' => $reason,
            ]);
        });

        $this->consumer = new KafkaConsumer($conf);
    }

    /**
     * 지정된 토픽을 구독
     *
     * @param array $topics 구독할 토픽 배열
     * @return void
     */
    public function subscribe(array $topics): void
    {
        $this->consumer->subscribe($topics);
    }

    /**
     * 메시지 소비
     *
     * @param int $timeout_ms 메시지 대기 타임아웃 (밀리초)
     * @return Message|null
     */
    public function consume(int $timeout_ms = 1000): ?Message
    {
        $message = $this->consumer->consume($timeout_ms);

        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                return $message;

            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                return null;

            default:
                Log::error('Kafka 메시지 소비 중 오류 발생', [
                    'error' => rd_kafka_err2str($message->err),
                ]);
                return null;
        }
    }

    /**
     * 메시지 커밋
     *
     * @param Message|null $message 커밋할 메시지 (null인 경우 마지막으로 소비한 모든 오프셋 커밋)
     * @return void
     */
    public function commit(?Message $message = null): void
    {
        if ($message === null) {
            $this->consumer->commit();
        } else {
            $this->consumer->commitMessage($message);
        }
    }

    /**
     * 구독 해제
     *
     * @return void
     */
    public function unsubscribe(): void
    {
        $this->consumer->unsubscribe();
    }

    /**
     * 컨슈머 종료
     *
     * @return void
     */
    public function close(): void
    {
        $this->consumer->close();
    }
}
