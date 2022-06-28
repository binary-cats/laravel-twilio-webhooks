<?php

namespace Tests;

use BinaryCats\TwilioWebhooks\Exceptions\WebhookFailed;
use BinaryCats\TwilioWebhooks\ProcessTwilioWebhookJob;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookClient\Models\WebhookCall;

class TwilioWebhookCallTest extends TestCase
{
    /** @var \BinaryCats\TwilioWebhooks\ProcessTwilioWebhookJob */
    public $processTwilioWebhookJob;

    /** @var \Spatie\WebhookClient\Models\WebhookCall */
    public $webhookCall;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        config(['twilio-webhooks.jobs' => ['initiated' => DummyJob::class]]);

        $this->webhookCall = WebhookCall::create([
            'name' => 'twilio',
            'payload' => [
                'CallStatus' => 'initiated',
                'key' => 'value',
            ],
            'url' => '/webhooks/twilio.com',
        ]);

        $this->processTwilioWebhookJob = new ProcessTwilioWebhookJob($this->webhookCall);
    }

    /** @test */
    public function it_will_fire_off_the_configured_job()
    {
        $this->processTwilioWebhookJob->handle();

        $this->assertEquals($this->webhookCall->id, cache('dummyjob')->id);
    }

    /** @test */
    public function it_will_not_dispatch_a_job_for_another_type()
    {
        config(['twilio-webhooks.jobs' => ['another_type' => DummyJob::class]]);

        $this->processTwilioWebhookJob->handle();

        $this->assertNull(cache('dummyjob'));
    }

    /** @test */
    public function it_will_not_dispatch_jobs_when_no_jobs_are_configured()
    {
        config(['twilio-webhooks.jobs' => []]);

        $this->processTwilioWebhookJob->handle();

        $this->assertNull(cache('dummyjob'));
    }

    /** @test */
    public function it_will_dispatch_events_even_when_no_corresponding_job_is_configured()
    {
        config(['twilio-webhooks.jobs' => ['another_type' => DummyJob::class]]);

        $this->processTwilioWebhookJob->handle();

        $webhookCall = $this->webhookCall;

        Event::assertDispatched("twilio-webhooks::{$webhookCall->payload['CallStatus']}", function ($event, $eventPayload) use ($webhookCall) {
            $this->assertInstanceOf(WebhookCall::class, $eventPayload);
            $this->assertEquals($webhookCall->id, $eventPayload->id);

            return true;
        });

        $this->assertNull(cache('dummyjob'));
    }

    /** @test */
    public function it_will_throw_exception_when_job_is_configutred_but_not_set()
    {
        config(['twilio-webhooks.jobs' => ['initiated' => 'Job\Does\Not\ExistClass']]);

        $this->expectException(WebhookFailed::class);
        $this->expectExceptionMessage("Could not process webhook id `{$this->webhookCall->getKey()}` of type `initiated` because the configured jobclass `Job\Does\Not\ExistClass` does not exist.");

        $this->processTwilioWebhookJob->handle();
    }

    /** @test */
    public function it_will_change_the_key_for_the_job()
    {
        $job = new ProcessTwilioWebhookJob($this->webhookCall);

        $this->assertEquals('CallStatus', $job->getKey());
        $this->assertEquals($job, $job->setKey('SmsStatus'));
        $this->assertEquals('SmsStatus', $job->getKey());
    }
}
