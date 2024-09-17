<?php

namespace BinaryCats\TwilioWebhooks;

use BinaryCats\TwilioWebhooks\Exceptions\WebhookFailed;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessTwilioWebhookJob extends ProcessWebhookJob
{
    /**
     * Name of the payload keys to contain the type of event.
     *
     * @var array
     */
    protected $keys = ['CallStatus', 'SmsStatus'];

    /**
     * The current key being used.
     *
     * @var string
     */
    protected $key = 'CallStatus'; // Default to 'CallStatus'

    /**
     * Handle the process.
     *
     * @return void
     */
    public function handle()
    {
        $type = null;
        foreach ($this->keys as $key) {
            $type = Arr::get($this->webhookCall, "payload.{$key}");
            if ($type) {
                $this->key = $key;
                break;
            }
        }

        if (! $type) {
            throw WebhookFailed::missingType($this->webhookCall);
        }

        event($this->determineEventKey($type), $this->webhookCall);

        $jobClass = $this->determineJobClass($type);

        if ('' === $jobClass) {
            return;
        }

        if (! class_exists($jobClass)) {
            throw WebhookFailed::jobClassDoesNotExist($jobClass, $this->webhookCall);
        }

        dispatch(new $jobClass($this->webhookCall));
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setKey(string $key)
    {
        if (in_array($key, $this->keys)) {
            $this->key = $key;
        }

        return $this;
    }

    /**
     * @param  string  $eventType
     * @return string
     */
    protected function determineJobClass(string $eventType): string
    {
        return config($this->determineJobConfigKey($eventType), '');
    }

    /**
     * @param  string  $eventType
     * @return string
     */
    protected function determineJobConfigKey(string $eventType): string
    {
        return Str::of($eventType)
            ->replace('.', '_')
            ->prepend('twilio-webhooks.jobs.')
            ->lower();
    }

    /**
     * @param  string  $eventType
     * @return string
     */
    protected function determineEventKey(string $eventType): string
    {
        return Str::of($eventType)
            ->prepend('twilio-webhooks::')
            ->lower();
    }
}
