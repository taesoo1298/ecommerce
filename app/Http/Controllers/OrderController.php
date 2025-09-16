<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderCreateRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * OrderController 생성자
     *
     * @param OrderService $orderService
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * 주문 생성
     *
     * @param OrderCreateRequest $request
     * @return JsonResponse
     */
    public function store(OrderCreateRequest $request): JsonResponse
    {
        $orderData = $request->validated();

        $order = $this->orderService->createOrder($orderData);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => '주문 생성 중 오류가 발생했습니다.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => '주문이 생성되었습니다.',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * 주문 상세 조회
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderService->getOrder($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => '주문을 찾을 수 없습니다.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * 주문 취소
     *
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(int $id): JsonResponse
    {
        $result = $this->orderService->cancelOrder($id);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => '주문 취소 중 오류가 발생했습니다.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => '주문이 취소되었습니다.',
        ]);
    }
}
