<?php

namespace BinaryCats\TwilioWebhooks;

use BinaryCats\TwilioWebhooks\Exceptions\WebhookFailed;
use Illuminate\Http\Request;

class Webhook
{
    /**
     * Validate and raise an appropriate event.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $signature
     * @param string $secret
     * @return \BinaryCats\TwilioWebhooks\Event
     * @throws \BinaryCats\TwilioWebhooks\Exceptions\WebhookFailed
     */
    public static function constructEvent(Request $request, string $signature, string $secret): Event
    {
        if (! WebhookSignature::make($request, $signature, $secret)->verify()) {
            throw WebhookFailed::invalidSignature();
        }

        return Event::constructFrom($request->all());
    }
}
