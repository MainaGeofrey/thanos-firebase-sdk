<?php

namespace Firebase\Services;

use Firebase\Config;
use Firebase\Constants\ApiConstants;
use Firebase\Exceptions\FirebaseException;
use Firebase\Helpers\Cached;
use Firebase\Helpers\Crypt;
use Firebase\Helpers\Signature;
use Firebase\Resources\CurlResource;

class OAuthService
{
    const CACHE_NAME = 'thanos_access_token';

    const RETRY_LIMIT = 2;

    private $config;
    private $payload;
    private $curl;
    private $signature;

    /**
     * @param Config $config
     * @param CurlResource $curl
     * @param Signature $signature
     */
    public function __construct($config, $curl, $signature)
    {
        $this->config = $config;

        $this->payload = $this->config->getCredentials() + [
            'grant_type' => 'client_credentials',
        ];

        $this->curl = $curl;
        $this->signature = $signature;
    }

    /**
     * @return string|null
     */
    public function getAccessToken()
    {
        $retryCount = 0;

        $cacheName = self::CACHE_NAME . '_' . hash('sha256', $this->config->getCredentials(true));

        while ($retryCount < self::RETRY_LIMIT) {
            try {
               // Cached::clearCache();
                $token = Cached::get(function () {
                    $response = $this->curl
                        ->setHeaders([
                            'thanos-signature: ' . $this->signature->getSignature($this->payload),
                        ])
                        ->post($this->config->getUrl() . ApiConstants::TOKEN_SLUG, http_build_query($this->payload));

                    if (!$response || $this->curl->code != 200) {
                        throw new FirebaseException('Access token generation failed, response: ' . $response);
                    }

                    $response = json_decode($response, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new FirebaseException('Failed to parse access token response: ' . json_last_error_msg());
                    }

                    if (!isset($response['data']['access_token'])) {
                        throw new FirebaseException('Access token not found in response');
                    }

                    return Crypt::encrypt($response['data']['access_token'], $this->getEncryptionKey());
                }, $cacheName);

                return Crypt::decrypt($token, $this->getEncryptionKey());
            } catch (\Exception $e) { // changed from \Throwable to \Exception
                $retryCount++;

                if ($retryCount >= self::RETRY_LIMIT) {
                    throw new FirebaseException('Failed to get access token from API: ' . $e->getMessage());
                }

                usleep(500000);
            }
        }

        return null;
    }

    /**
     * @return string
     */
    private function getEncryptionKey()
    {
        return md5($this->config->getCredentials(true));
    }
}
