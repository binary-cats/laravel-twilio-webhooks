<?php

namespace Tests;

use BinaryCats\TwilioWebhooks\Exceptions\WebhookFailed;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\WebhookClient\Models\WebhookCall;
use Twilio\Security\RequestValidator;

class IntegrationTest extends TestCase
{
    protected string $url;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        Route::twilioWebhooks('twilio-webhooks');
        Route::twilioWebhooks('twilio-webhooks/{configKey}');

        $this->url = 'https://localhost/twilio-webhooks';

        config(['twilio-webhooks.jobs' => ['initiated' => DummyJob::class]]);

        cache()->clear();
    }

    /** @test */
    public function it_can_handle_a_valid_request()
    {
        $payload = [
            'CallStatus' => 'initiated',
            'key' => 'value'
        ];

        $headers = ['X-Twilio-Signature' => $this->determineTwilioSignature($payload, $this->url)];

        $x = $this
            ->postJson($this->url, $payload, $headers)
            ->assertSuccessful();

        $this->assertCount(1, WebhookCall::get());

        $webhookCall = WebhookCall::first();

        $this->assertEquals('initiated', $webhookCall->payload['CallStatus']);
        $this->assertEquals($payload, $webhookCall->payload);
        $this->assertNull($webhookCall->exception);

        Event::assertDispatched('twilio-webhooks::initiated', function ($event, $eventPayload) use ($webhookCall) {
            $this->assertInstanceOf(WebhookCall::class, $eventPayload);
            $this->assertEquals($webhookCall->id, $eventPayload->id);

            return true;
        });

        $this->assertEquals($webhookCall->id, cache('dummyjob')->id);
    }

    /** @test */
    public function it_can_handle_a_valid_request_even_with_wrong_case()
    {
        $payload = [
            'CallStatus' => 'Initiated',
            'key' => 'value',
        ];

        $headers = ['X-Twilio-Signature' => $this->determineTwilioSignature($payload, $this->url)];

        $this
            ->postJson($this->url, $payload, $headers)
            ->assertSuccessful();

        $this->assertCount(1, WebhookCall::get());

        $webhookCall = WebhookCall::first();

        $this->assertNull($webhookCall->exception);

        Event::assertDispatched('twilio-webhooks::initiated', function ($event, $eventPayload) use ($webhookCall) {
            $this->assertInstanceOf(WebhookCall::class, $eventPayload);
            $this->assertEquals($webhookCall->id, $eventPayload->id);

            return true;
        });

        $this->assertEquals($webhookCall->id, cache('dummyjob')->id);
    }

    public function in_will_ignore_empty_reququest()
    {
        $payload = [];

        $headers = ['X-Twilio-Signature' => $this->determineTwilioSignature($payload, $this->url)];

        $this
            ->postJson($this->url, $payload, $headers)
            ->assertStatus(422);

        $this->assertCount(0, WebhookCall::get());

        Event::assertNotDispatched('twilio-webhooks::initiated');

        $this->assertNull(cache('dummyjob'));
    }

    public function in_will_ignore_unsinged_reququest()
    {
        $payload = [
            'CallStatus' => 'initiated',
            'key' => 'value',
        ];

        $this
            ->postJson($this->url, $payload)
            ->assertStatus(422);

        $this->assertCount(0, WebhookCall::get());

        Event::assertNotDispatched('twilio-webhooks::initiated');

        $this->assertNull(cache('dummyjob'));
    }

    /** @test */
    public function a_request_with_an_invalid_signature_wont_be_logged()
    {
        $payload = [
            'CallStatus' => 'Initiated',
            'key' => 'value',
        ];

        $headers = ['X-Twilio-Signature' => 'invalid-signature'];

        $this
            ->postJson($this->url, $payload, $headers)
            ->assertStatus(500);

        $this->assertCount(0, WebhookCall::get());

        Event::assertNotDispatched('twilio-webhooks::initiated');

        $this->assertNull(cache('dummyjob'));
    }

    /** @test */
    public function a_request_with_an_invalid_payload_will_be_logged_but_events_and_jobs_will_not_be_dispatched()
    {
        $payload = ['invalid_payload'];

        $headers = ['X-Twilio-Signature' => $this->determineTwilioSignature($payload, $this->url)];

        $this
            ->postJson($this->url, $payload, $headers)
            ->assertStatus(400);

        $this->assertCount(1, WebhookCall::get());

        $webhookCall = WebhookCall::first();

        $this->assertFalse(isset($webhookCall->payload['CallStatus']));
        $this->assertEquals(['invalid_payload'], $webhookCall->payload);

        $this->assertEquals('Webhook call id `1` did not contain a type. Valid Twilio webhook calls should contain a type.', $webhookCall->exception['message']);

        Event::assertNotDispatched('twilio-webhooks::initiated');

        $this->assertNull(cache('dummyjob'));
    }

    /** @test * */
    public function a_request_with_a_config_key_will_use_the_correct_signing_token()
    {
        config()->set('twilio-webhooks.signing_token', 'secret1');
        config()->set('twilio-webhooks.signing_token_somekey', 'secret2');

        $payload = [
            'CallStatus' => 'initiated',
            'key' => 'value',
        ];

        $headers = ['X-Twilio-Signature' => $this->determineTwilioSignature($payload, "$this->url/somekey", 'somekey')];

        $this
            ->postJson("$this->url/somekey", $payload, $headers)
            ->assertSuccessful();
    }

    /** @test */
    public function an_invalid_signature_value_generates_a_500_error()
    {
        $payload = [
            'CallStatus' => 'initiated',
            'key' => 'value',
        ];

        $headers = ['X-Twilio-Signature' => 'invalid-signature'];

        $this
            ->postJson($this->url, $payload, $headers)
            ->assertStatus(500);

        $this->assertCount(0, WebhookCall::get());

        Event::assertNotDispatched('twilio-webhooks::initiated');

        $this->assertNull(cache('dummyjob'));
    }

    /** @test */
    public function it_will_skip_validation_of_signature_when_instructed()
    {
        config(['twilio-webhooks.verify_signature' => false]);

        $payload = [
            'CallStatus' => 'Initiated',
            'key' => 'value',
        ];

        $headers = ['X-Twilio-Signature' => 'invalid-signature'];

        $this
            ->postJson($this->url, $payload, $headers)
            ->assertSuccessful();

        $this->assertCount(1, WebhookCall::get());

        $webhookCall = WebhookCall::first();

        $this->assertNull($webhookCall->exception);

        Event::assertDispatched('twilio-webhooks::initiated', function ($event, $eventPayload) use ($webhookCall) {
            $this->assertInstanceOf(WebhookCall::class, $eventPayload);
            $this->assertEquals($webhookCall->id, $eventPayload->id);

            return true;
        });

        $this->assertEquals($webhookCall->id, cache('dummyjob')->id);
    }

    /** @test */
    public function it_will_throw_exception_when_token_is_not_set()
    {
        config(['twilio-webhooks.signing_token' => null]);

        $payload = [
            'CallStatus' => 'Initiated',
            'key' => 'value',
        ];

        $headers = ['X-Twilio-Signature' => 'invalid-signature'];

        $this
            ->postJson($this->url, $payload, $headers)
            ->assertStatus(400);
    }

    /**
     * @param array $payload
     * @param string $url
     * @param string|null $configKey
     * @return string
     */
    protected function determineTwilioSignature(array $payload, string $url, string $configKey = null): string
    {
        $secret = ($configKey) ?
            config("twilio-webhooks.signing_token_{$configKey}") :
            config('twilio-webhooks.signing_token');

        return (new RequestValidator($secret))->computeSignature($url, $payload);
    }
}
