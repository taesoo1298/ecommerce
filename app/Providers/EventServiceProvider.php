<?php

namespace App\Providers;

use App\Events\Order\CouponAppliedEvent;
use App\Events\Order\CouponFailedEvent;
use App\Events\Order\InventoryDeductedEvent;
use App\Events\Order\InventoryFailedEvent;
use App\Events\Order\OrderCreatedEvent;
use App\Events\Order\PaymentCompletedEvent;
use App\Events\Order\PaymentFailedEvent;
use App\Listeners\Order\HandleOrderFailureListener;
use App\Listeners\Order\ProcessCouponListener;
use App\Listeners\Order\ProcessInventoryListener;
use App\Listeners\Order\ProcessPaymentListener;
use App\Listeners\Order\SendOrderNotificationListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // 주문 이벤트 리스너 등록
        OrderCreatedEvent::class => [
            ProcessCouponListener::class,
        ],

        CouponAppliedEvent::class => [
            ProcessInventoryListener::class,
        ],

        InventoryDeductedEvent::class => [
            ProcessPaymentListener::class,
        ],

        PaymentCompletedEvent::class => [
            SendOrderNotificationListener::class,
        ],

        // 실패 이벤트 리스너 등록
        PaymentFailedEvent::class => [
            HandleOrderFailureListener::class.'@handlePaymentFailed',
        ],

        InventoryFailedEvent::class => [
            HandleOrderFailureListener::class.'@handleInventoryFailed',
        ],

        CouponFailedEvent::class => [
            HandleOrderFailureListener::class.'@handleCouponFailed',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
