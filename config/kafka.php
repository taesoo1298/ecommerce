<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kafka Broker 설정
    |--------------------------------------------------------------------------
    |
    | Kafka 브로커 서버 목록을 지정합니다.
    | 다중 브로커를 사용하는 경우 쉼표로 구분하여 나열합니다.
    |
    */
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),

    /*
    |--------------------------------------------------------------------------
    | 토픽 설정
    |--------------------------------------------------------------------------
    |
    | 사용할 Kafka 토픽 이름을 지정합니다.
    |
    */
    'topics' => [
        'order_created' => env('KAFKA_TOPIC_ORDER_CREATED', 'order.created'),
        'coupon_applied' => env('KAFKA_TOPIC_COUPON_APPLIED', 'order.coupon.applied'),
        'coupon_failed' => env('KAFKA_TOPIC_COUPON_FAILED', 'order.coupon.failed'),
        'inventory_deducted' => env('KAFKA_TOPIC_INVENTORY_DEDUCTED', 'order.inventory.deducted'),
        'inventory_failed' => env('KAFKA_TOPIC_INVENTORY_FAILED', 'order.inventory.failed'),
        'payment_completed' => env('KAFKA_TOPIC_PAYMENT_COMPLETED', 'order.payment.completed'),
        'payment_failed' => env('KAFKA_TOPIC_PAYMENT_FAILED', 'order.payment.failed'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 컨슈머 그룹 설정
    |--------------------------------------------------------------------------
    |
    | 각 서비스별 Kafka 컨슈머 그룹 ID를 지정합니다.
    |
    */
    'consumer_groups' => [
        'coupon_service' => env('KAFKA_GROUP_COUPON_SERVICE', 'ecommerce.coupon.service'),
        'inventory_service' => env('KAFKA_GROUP_INVENTORY_SERVICE', 'ecommerce.inventory.service'),
        'payment_service' => env('KAFKA_GROUP_PAYMENT_SERVICE', 'ecommerce.payment.service'),
        'notification_service' => env('KAFKA_GROUP_NOTIFICATION_SERVICE', 'ecommerce.notification.service'),
    ],
];
