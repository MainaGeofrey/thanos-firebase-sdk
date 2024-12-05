<?php


namespace Firebase\Services;

use Firebase\Resources\CurlResource;

class PushNotificationService
{
    private $curl;
    private $accessToken;

    /**
     * Constructor for PushNotificationService
     *
     * @param CurlResource $curl An instance of the CurlResource class
     * @param string $accessToken The access token for authenticating requests
     */
    public function __construct(CurlResource $curl, string $accessToken)
    {
        $this->curl = $curl;
        $this->accessToken = $accessToken;
    }

    /**
     * Send a push notification
     *
     * @param string $url The URL to send the request to
     * @param array $payload The data to be sent in the push notification
     * @return string The response from the server
     */
    public function sendNotification(string $url, array $payload)
    {
        // Set the Authorization header with the access token
        $this->curl->setHeaders([
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);

        // Send the POST request with the payload
        return $this->curl->post($url, $payload);
    }
}
