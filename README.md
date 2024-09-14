# Handle Twilio Webhooks in a Laravel application

![https://github.com/binary-cats/laravel-twilio-webhooks/actions](https://github.com/binary-cats/laravel-twilio-webhooks/workflows/Laravel/badge.svg)

[Twilio](https://twilio.com) can notify your application of various engagement events using webhooks. This package can help you handle those webhooks. 
Out of the box it will verify the Twilio signature of all incoming requests. All valid calls and messages will be logged to the database. 
You can easily define jobs or events that should be dispatched when specific events hit your app.

This package will not handle what should be done _after_ the webhook request has been validated and the right job or event is called. 
You should still code up any work (eg. what should happen) yourself.

Before using this package we highly recommend reading [the entire documentation on webhooks over at Twilio](https://www.twilio.com/docs).

This package is an adapted copy of an absolutely amazing [spatie/laravel-stripe-webhooks](https://github.com/spatie/laravel-stripe-webhooks)

## Installation

You can install the package via composer:

```bash
composer require binary-cats/laravel-twilio-webhooks
```

The service provider will automatically register itself.

You must publish the config file with:
```bash
php artisan vendor:publish --provider="BinaryCats\TwilioWebhooks\TwilioWebhooksServiceProvider" --tag="config"
```

This is the contents of the config file that will be published at `config/twilio-webhooks.php`:

```php
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
```

In the `signing_token` key of the config file you should add a valid token. You can find the secret used at [User Settings](https://twilio.com/user/account).

**N.B.** It is a far better security practice to generate an new api/key pair for each project you work which will protect your main api key/token pair from exposure.  

**You can skip migrating is you have already installed `Spatie\WebhookClient`**

Next, you must publish the migration with:
```bash
php artisan vendor:publish --provider="Spatie\WebhookClient\WebhookClientServiceProvider" --tag="webhook-client-migrations"
```

After migration has been published you can create the `webhook_calls` table by running the migrations:

```bash
php artisan migrate
```

### Routing
Finally, take care of the routing: Whatever callbacks you send to the application, you must configure at what url Twilio webhooks should hit your app. In the routes file of your app you must pass that route to `Route::twilioWebhooks()`:

I like to group functionality by domain, so I would suggest `webhooks/twilio.com` (especially if you plan to have more webhooks), but it is up to you.

```php
# routes\web.php
Route::twilioWebhooks('webhooks/twilio.com');
```

Behind the scenes this will register a `POST` route to a controller provided by this package. Because Twilio has no way of getting a csrf-token, you must add that route to the `except` array of the `VerifyCsrfToken` middleware:

```php
protected $except = [
    'webhooks/twilio.com',
];
```

## Usage

Twilio will send out webhooks for several event types, depending on the engagement type (voice, SMS, etc).

Twilio will sign all requests hitting the webhook url of your app. This package will automatically verify if the signature is valid. If it is not, the request was probably not sent by Twilio.

Unless something goes terribly wrong, this package will always respond with a `200` to webhook requests. All webhook requests with a valid signature will be logged in the `webhook_calls` table. The table has a `payload` column where the entire payload of the incoming webhook is saved.

If the signature is not valid, the request will not be logged in the `webhook_calls` table but a `BinaryCats\TwilioWebhooks\Exceptions\WebhookFailed` exception will be thrown.
If something goes wrong during the webhook request the thrown exception will be saved in the `exception` column. In that case the controller will send a `500` instead of `200`.

There are two ways this package enables you to handle webhook requests: you can opt to queue a job or listen to the events the package will fire.

**Please make sure your configured keys are lowercase, as the package will automatically ensure they are**

### Handling webhook requests using jobs
If you want to do something when a specific event type comes in you can define a job that does the work. Here's an example of such a job:

```php
<?php

namespace App\Jobs\TwilioWebhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\WebhookClient\Models\WebhookCall;

class HandleInitiated implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /** @var \Spatie\WebhookClient\Models\WebhookCall */
    public $webhookCall;

    public function __construct(WebhookCall $webhookCall)
    {
        $this->webhookCall = $webhookCall;
    }

    public function handle()
    {
        // do your work here

        // you can access the payload of the webhook call with `$this->webhookCall->payload`
    }
}
```

Spatie highly recommends that you make this job queueable, because this will minimize the response time of the webhook requests. This allows you to handle more Twilio webhook requests and avoid timeouts.

After having created your job you must register it at the `jobs` array in the `twilio-webhooks.php` config file.\
The key should be the name of twilio event type.\
The value should be the fully qualified classname.

```php
// config/twilio-webhooks.php

'jobs' => [
    'initiated' => \App\Jobs\TwilioWebhooks\HandleInitiated::class,
],
```

### Handling webhook requests using events

Instead of queueing jobs to perform some work when a webhook request comes in, you can opt to listen to the events this package will fire. Whenever a valid request hits your app, the package will fire a `twilio-webhooks::<name-of-the-event>` event.

The payload of the events will be the instance of `WebhookCall` that was created for the incoming request.

Let's take a look at how you can listen for such an event. In the `EventServiceProvider` you can register listeners.

```php
/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    'twilio-webhooks::initiated' => [
        App\Listeners\InitiatedCall:class,
    ],
];
```

Here's an example of such a listener:

```php
<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\WebhookClient\Models\WebhookCall;

class InitiatedCall implements ShouldQueue
{
    public function handle(WebhookCall $webhookCall)
    {
        // do your work here

        // you can access the payload of the webhook call with `$webhookCall->payload`
    }
}
```

Spatie highly recommends that you make the event listener queueable, as this will minimize the response time of the webhook requests. This allows you to handle more Twilio webhook requests and avoid timeouts.

The above example is only one way to handle events in Laravel. To learn the other options, read [the Laravel documentation on handling events](https://laravel.com/docs/9.x/events).

## Advanced usage

### Retry handling a webhook

All incoming webhook requests are written to the database. This is incredibly valuable when something goes wrong while handling a webhook call. You can easily retry processing the webhook call, after you've investigated and fixed the cause of failure, like this:

```php
use Spatie\WebhookClient\Models\WebhookCall;
use BinaryCats\TwilioWebhooks\ProcessTwilioWebhookJob;

$webhook = WebhookCall::find($id);

dispatch(new ProcessTwilioWebhookJob($webhook));
```

### Performing custom logic

You can add some custom logic that should be executed before and/or after the scheduling of the queued job by using your own job class. You can do this by specifying your own job class in the `process_webhook_job` key of the `twilio-webhooks` config file. The class should extend `BinaryCats\TwilioWebhooks\ProcessTwilioWebhookJob`.

Here's an example:

```php
use BinaryCats\TwilioWebhooks\ProcessTwilioWebhookJob;

class MyCustomTwilioWebhookJob extends ProcessTwilioWebhookJob
{
    public function handle()
    {
        // do some custom stuff before handling

        parent::handle();

        // do some custom stuff after handling
    }
}
```
### Handling multiple signing secrets

When needed might want to the package to handle multiple endpoints and secrets. Here's how to configure that behaviour.

If you are using the `Route::twilioWebhooks` macro, you can append the `configKey` as follows:

```php
Route::twilioWebhooks('webhooks/twilio.com/{configKey}');
```

Alternatively, if you are manually defining the route, you can add `configKey` like so:

```php
Route::post('webhooks/twilio.com/{configKey}', 'BinaryCats\TwilioWebhooks\TwilioWebhooksController');
```

If this route parameter is present verify middleware will look for the secret using a different config key, by appending the given the parameter value to the default config key. E.g. If Twilio posts to `webhooks/twilio.com/my-named-secret` you'd add a new config named `signing_token_my-named-secret`.

Example config might look like:

```php
// token for when Twilio posts to webhooks/twilio.com/account
'signing_token_account' => 'whsec_abc',
// secret for when Twilio posts to webhooks/twilio.com/my-alternative-token
'signing_token_my-alternative-secret' => 'whsec_123',
```

### About Twilio

[Twilio](https://www.twilio.com/) powers personalized interactions and trusted global communications to connect you with customers.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information about what has changed recently.

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email cyrill.kalita@gmail.com instead of using issue tracker.

## Postcardware

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.

## Credits

- [Cyrill Kalita](https://github.com/binary-cats)
- [All Contributors](../../contributors)

Big shout-out to [Spatie](https://spatie.be/) for their work, which is a huge inspiration.

## Support us

Binary Cats is a webdesign agency based in Illinois, US.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
