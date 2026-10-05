<?php

use App\Contracts\PaymentGateway;
use App\Exceptions\UnsupportedCurrencyException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\RazorpayGateway;
use App\Support\Payments\WebhookSigner;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function razorpay(): RazorpayGateway
{
    config([
        'services.razorpay.key' => 'rzp_test_key',
        'services.razorpay.secret' => 'rzp_test_secret',
        'services.razorpay.webhook_secret' => 'whsec_test',
        'services.razorpay.base_url' => 'https://api.razorpay.test/v1',
    ]);

    return app(RazorpayGateway::class);
}

it('creates an order with the booking amount and basic auth', function () {
    Http::fake([
        '*/orders' => Http::response([
            'id' => 'order_ABC123',
            'amount' => 52500,
            'currency' => 'INR',
            'status' => 'created',
        ]),
    ]);

    $booking = Booking::factory()->hold()->create(['price_minor' => 52500]);

    $order = razorpay()->createOrder($booking, 52500, 'INR');

    expect($order->id)->toBe('order_ABC123')
        ->and($order->amountMinor)->toBe(52500)
        ->and($order->currency)->toBe('INR');

    Http::assertSent(function ($request) use ($booking) {
        return $request->url() === 'https://api.razorpay.test/v1/orders'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_key:rzp_test_secret'))
            && $request['amount'] === 52500
            && $request['receipt'] === 'booking_'.$booking->id
            && $request['notes']['booking_id'] === (string) $booking->id;
    });
});

it('reads a payment back from the provider', function () {
    Http::fake([
        '*/payments/pay_XYZ' => Http::response([
            'id' => 'pay_XYZ',
            'status' => 'captured',
            'amount' => 70000,
            'currency' => 'INR',
            'method' => 'card',
        ]),
    ]);

    $payment = razorpay()->fetchPayment('pay_XYZ');

    expect($payment->id)->toBe('pay_XYZ')
        ->and($payment->isCaptured())->toBeTrue()
        ->and($payment->amountMinor)->toBe(70000)
        ->and($payment->method)->toBe('card');
});

it('refunds a captured payment through the provider', function () {
    Http::fake([
        '*/payments/pay_XYZ/refund' => Http::response([
            'id' => 'rfnd_123',
            'status' => 'processed',
            'amount' => 35000,
        ]),
    ]);

    $scenario = paidBookingScenario();
    $payment = $scenario['payment'];
    $payment->forceFill(['gateway_payment_id' => 'pay_XYZ'])->save();

    $refund = razorpay()->refund($payment, 35000);

    expect($refund->id)->toBe('rfnd_123')
        ->and($refund->isProcessed())->toBeTrue()
        ->and($refund->amountMinor)->toBe(35000);

    Http::assertSent(fn ($request) => $request['amount'] === 35000 && $request['speed'] === 'normal');
});

it('surfaces provider errors instead of failing silently', function () {
    Http::fake(['*/orders' => Http::response(['error' => ['description' => 'Bad request']], 400)]);

    $booking = Booking::factory()->hold()->create();

    expect(fn () => razorpay()->createOrder($booking, 50000, 'INR'))
        ->toThrow(RequestException::class);
});

it('verifies webhook signatures over the raw payload', function () {
    $gateway = razorpay();
    $payload = '{"event":"payment.captured"}';
    $signature = WebhookSigner::sign($payload, 'whsec_test');

    expect($gateway->verifyWebhookSignature($payload, $signature))->toBeTrue()
        ->and($gateway->verifyWebhookSignature($payload, 'deadbeef'))->toBeFalse()
        ->and($gateway->verifyWebhookSignature($payload, null))->toBeFalse()
        ->and($gateway->verifyWebhookSignature($payload.' ', $signature))->toBeFalse();
});

it('normalises a razorpay capture webhook into a gateway event', function () {
    $body = [
        'event' => 'payment.captured',
        'payload' => [
            'payment' => [
                'entity' => [
                    'id' => 'pay_ABC',
                    'order_id' => 'order_ABC',
                    'amount' => 70000,
                    'currency' => 'INR',
                    'status' => 'captured',
                    'method' => 'netbanking',
                ],
            ],
        ],
    ];

    $event = razorpay()->parseWebhook((string) json_encode($body), ['x-razorpay-event-id' => 'evt_1']);

    expect($event->id)->toBe('evt_1')
        ->and($event->type)->toBe('payment.captured')
        ->and($event->paymentId)->toBe('pay_ABC')
        ->and($event->orderId)->toBe('order_ABC')
        ->and($event->amountMinor)->toBe(70000)
        ->and($event->isCapture())->toBeTrue()
        ->and($event->isFailure())->toBeFalse();
});

it('falls back to a payload hash when the provider sends no event id', function () {
    $body = ['event' => 'payment.failed', 'payload' => ['payment' => ['entity' => ['id' => 'pay_1', 'status' => 'failed']]]];

    $event = razorpay()->parseWebhook((string) json_encode($body));

    expect($event->id)->toHaveLength(64)
        ->and($event->isFailure())->toBeTrue();
});

it('gives the checkout widget the order it needs without leaking the secret', function () {
    $scenario = paidBookingScenario();
    $scenario['payment']->forceFill(['currency' => RazorpayGateway::SUPPORTED_CURRENCY])->save();

    $payload = razorpay()->checkoutPayload($scenario['payment']->refresh());

    expect($payload['key'])->toBe('rzp_test_key')
        ->and($payload['order_id'])->toBe($scenario['payment']->gateway_order_id)
        ->and($payload['amount'])->toBe($scenario['payment']->amount_minor)
        ->and($payload['currency'])->toBe('INR')
        ->and($payload['callback_url'])->toContain('/payments/'.$scenario['payment']->id.'/return')
        ->and($payload)->not->toHaveKey('secret');
});

it('refuses to open a Razorpay order or widget in a currency it cannot settle', function () {
    Http::preventStrayRequests();

    $booking = Booking::factory()->hold()->create(['currency' => 'LKR']);
    $payment = $booking->payments()->create(Payment::factory()->pending()->raw([
        'booking_id' => $booking->id,
        'student_id' => $booking->student_id,
        'currency' => 'LKR',
    ]));

    $gateway = razorpay();

    expect(fn () => $gateway->createOrder($booking, $booking->price_minor, 'LKR'))
        ->toThrow(UnsupportedCurrencyException::class);

    expect(fn () => $gateway->checkoutPayload($payment->refresh()))
        ->toThrow(UnsupportedCurrencyException::class);

    // The offline gateway has no such limit — it mirrors whatever the booking says.
    $order = app(FakePaymentGateway::class)->createOrder($booking, $booking->price_minor, 'LKR');

    expect($order->currency)->toBe('LKR');
});

it('keeps the offline gateway off the network', function () {
    Http::preventStrayRequests();

    $gateway = app(FakePaymentGateway::class);
    $booking = Booking::factory()->hold()->create();

    $order = $gateway->createOrder($booking, 50000, 'INR');

    expect($order->id)->toStartWith('order_fake_')
        ->and($gateway->name())->toBe('fake');
});

it('binds the gateway from configuration', function () {
    expect(config('studylikepro.payments.gateway'))->toBe('fake')
        ->and(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);

    config(['studylikepro.payments.gateway' => 'razorpay']);

    expect(app()->make(PaymentGateway::class))->toBeInstanceOf(RazorpayGateway::class);
});
