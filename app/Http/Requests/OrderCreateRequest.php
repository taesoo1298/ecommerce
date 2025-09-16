<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // 인증된 사용자만 주문 생성 가능하도록 설정하거나
        // API 토큰 인증을 사용하는 경우 true로 설정하고 미들웨어에서 처리
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', 'exists:coupons,code'],
            'payment_method' => ['required', 'string', Rule::in(['card', 'bank', 'virtual_account', 'mobile'])],
            'shipping_address' => ['required', 'string'],
            'shipping_city' => ['required', 'string'],
            'shipping_postal_code' => ['required', 'string'],
            'shipping_country' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => '고객 ID',
            'items' => '주문 상품',
            'items.*.product_id' => '상품 ID',
            'items.*.quantity' => '수량',
            'coupon_code' => '쿠폰 코드',
            'payment_method' => '결제 방법',
            'shipping_address' => '배송지 주소',
            'shipping_city' => '배송지 도시',
            'shipping_postal_code' => '배송지 우편번호',
            'shipping_country' => '배송지 국가',
            'notes' => '주문 메모',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => '고객 ID는 필수입니다.',
            'customer_id.exists' => '존재하지 않는 고객입니다.',
            'items.required' => '주문 상품은 필수입니다.',
            'items.min' => '최소 1개 이상의 상품을 주문해야 합니다.',
            'items.*.product_id.required' => '상품 ID는 필수입니다.',
            'items.*.product_id.exists' => '존재하지 않는 상품입니다.',
            'items.*.quantity.required' => '수량은 필수입니다.',
            'items.*.quantity.min' => '수량은 최소 1개 이상이어야 합니다.',
            'coupon_code.exists' => '존재하지 않는 쿠폰 코드입니다.',
            'payment_method.required' => '결제 방법은 필수입니다.',
            'payment_method.in' => '지원하지 않는 결제 방법입니다.',
            'shipping_address.required' => '배송지 주소는 필수입니다.',
            'shipping_city.required' => '배송지 도시는 필수입니다.',
            'shipping_postal_code.required' => '배송지 우편번호는 필수입니다.',
            'shipping_country.required' => '배송지 국가는 필수입니다.',
            'notes.max' => '주문 메모는 500자를 초과할 수 없습니다.',
        ];
    }
}
