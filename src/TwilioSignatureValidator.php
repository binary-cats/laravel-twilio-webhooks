<?php

namespace BinaryCats\TwilioWebhooks;

use BinaryCats\TwilioWebhooks\Exceptions\WebhookFailed;
use Exception;
use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class TwilioSignatureValidator implements SignatureValidator
{
    /**
     * True if the signature has been validated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Spatie\WebhookClient\WebhookConfig  $config
     * @return bool
     */
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        if (! config('twilio-webhooks.verify_signature')) {
            return true;
        }

        $signature = $request->header($config->signatureHeaderName);
        $secret = $config->signingSecret;

        throw_if(empty($config->signingSecret), WebhookFailed::signingSecretNotSet());

        try {
            Webhook::constructEvent($request, $signature, $secret);
        } catch (Exception $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
