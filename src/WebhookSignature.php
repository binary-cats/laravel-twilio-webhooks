<?php

namespace BinaryCats\TwilioWebhooks;

use Illuminate\Http\Request;
use Twilio\Security\RequestValidator;

final class WebhookSignature
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected Request $request;

    /**
     * @var string
     */
    protected string $signature;

    /**
     * @var string
     */
    protected string $secret;

    /**
     * @var \Twilio\Security\RequestValidator
     */
    protected RequestValidator $validator;

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $signature
     * @param string $secret
     */
    public function __construct(Request $request, string $signature, string $secret)
    {
        $this->request = $request;
        $this->signature = $signature;
        $this->secret = $secret;
        $this->validator = new RequestValidator($secret);
    }

    /**
     * Static accessor into the class constructor.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $signature
     * @param string $secret
     * @return static
     */
    public static function make(Request $request, string $signature, string $secret)
    {
        return new static($request, $signature, $secret);
    }

    /**
     * True if the signature is valid.
     *
     * @return bool
     */
    public function verify(): bool
    {
        // Extract the URL parameters from the URL
        $fullUrl = $this->request->fullUrl();
        $queryParams = [];
        parse_str(parse_url($fullUrl, PHP_URL_QUERY), $queryParams);

        // Remove each key found in the URL parameters from the request data
        $requestData = $this->request->all();
        foreach ($queryParams as $key => $value) {
            unset($requestData[$key]);
        }
        
        return $this->validator->validate($this->signature, $fullUrl, $requestData);
    }
}
