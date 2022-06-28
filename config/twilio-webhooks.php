<?php

return [

    /*
     * Twilio will sign each webhook using a token: https://twilio.com/user/account.
     */
    'signing_token' => env('TWILIO_WEBHOOK_SECRET'),

    /*
     * You can define the job that should be run when a certain webhook hits your application
     * here. If Twilio event has a dot it will be replaced with an underscore `_`.
     *
     * You can find a list of Twilio webhook types here:
     * https://www.twilio.com/docs/usage/webhooks
     *
     * The package will automatically convert the keys to lowercase
     * Be cognisant of the fact that array keys are **case sensitive** in PHP
     */
    'jobs' => [
        // 'initiated' => \BinaryCats\TwilioWebhooks\Jobs\HandleInitiated::class,
    ],

    /*
     * The classname of the model to be used. The class should equal or extend
     * Spatie\WebhookClient\Models\WebhookCall
     */
    'model' => \Spatie\WebhookClient\Models\WebhookCall::class,

    /*
     * A class processing the job.
     * The class should extend BinaryCats\TwilioWebhooks\ProcessTwilioWebhookJob
     */
    'process_webhook_job' => \BinaryCats\TwilioWebhooks\ProcessTwilioWebhookJob::class,

    /*
     * When disabled, the package will not verify if the signature is valid.
     */
    'verify_signature' => env('TWILIO_SIGNATURE_VERIFY', true),
];
