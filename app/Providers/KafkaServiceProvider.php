<?php

namespace App\Providers;

use App\Adapters\KafkaEventAdapter;
use App\Services\Kafka\KafkaProducerService;
use App\Services\Kafka\OrderEventPublisherService;
use Illuminate\Support\ServiceProvider;

class KafkaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Kafka Producer 서비스 등록
        $this->app->singleton(KafkaProducerService::class, function ($app) {
            return new KafkaProducerService();
        });

        // 주문 이벤트 발행 서비스 등록
        $this->app->singleton(OrderEventPublisherService::class, function ($app) {
            return new OrderEventPublisherService(
                $app->make(KafkaProducerService::class)
            );
        });

        // Kafka 이벤트 어댑터 등록
        $this->app->singleton(KafkaEventAdapter::class, function ($app) {
            return new KafkaEventAdapter(
                $app->make(OrderEventPublisherService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Kafka 이벤트 어댑터 설정
        $eventAdapter = $this->app->make(KafkaEventAdapter::class);
        $eventAdapter->subscribe($this->app['events']);
    }
}
