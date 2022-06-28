<?php

namespace Tests;

use Spatie\WebhookClient\Models\WebhookCall;

class DummyJob
{
    /**
     * @var \Spatie\WebhookClient\Models\WebhookCall
     */
    public WebhookCall $webhookCall;

    /**
     * @param  \Spatie\WebhookClient\Models\WebhookCall  $webhookCall
     */
    public function __construct(WebhookCall $webhookCall)
    {
        $this->webhookCall = $webhookCall;
    }

    /**
     * @return void
     */
    public function handle()
    {
        cache()->put('dummyjob', $this->webhookCall);
    }
}
