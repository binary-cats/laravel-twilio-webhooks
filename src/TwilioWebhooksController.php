<?php

namespace BinaryCats\TwilioWebhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookProcessor;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;

class TwilioWebhooksController
{
    /**
     * Invoke controller method.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $configKey
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, string $configKey = null)
    {
        $webhookConfig = new WebhookConfig([
            'name' => 'twilio',
            'signing_secret' => ($configKey) ?
                config('twilio-webhooks.signing_token_'.$configKey) :
                config('twilio-webhooks.signing_token'),
            'signature_header_name' => 'X-Twilio-Signature',
            'signature_validator' => TwilioSignatureValidator::class,
            'webhook_profile' => ProcessEverythingWebhookProfile::class,
            'webhook_model' => config('twilio-webhooks.model'),
            'process_webhook_job' => config('twilio-webhooks.process_webhook_job'),
        ]);

        return (new WebhookProcessor($request, $webhookConfig))->process();
    }
}
