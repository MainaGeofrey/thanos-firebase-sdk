<?php

namespace Firebase\Services;

use Firebase\Config;
use Firebase\Constants\ApiConstants;
use Firebase\Exceptions\FirebaseException;
use Firebase\Helpers\Cached;
use Firebase\Helpers\Crypt;
use Firebase\Resources\CurlResource;

class OAuthService
{
    const CACHE_NAME = 'thanos_access_token';

    const RETRY_LIMIT = 2;

    private $config;
    private $payload;
    private $curl;

    /**
     * @param Config $config
     * @param CurlResource $curl
     */
    public function __construct(Config $config, CurlResource $curl)
    {
        $this->config = $config;
        $this->curl = $curl;
    }

    /**
     * @return string|null
     */
    public function getAccessToken()
    {
        $retryCount = 0;

        $cacheName = self::CACHE_NAME . '_' . hash('sha256', json_encode($this->config->getConfig()));

        while ($retryCount < self::RETRY_LIMIT) {
            try {
               // Cached::clearCache();
                $token = Cached::get(function () {
                    $jwt = $this->generateJWT();

                    $payload = [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $jwt
                    ];

   
                    $response = $this->curl
                        ->setHeaders([])
                        ->post($this->config->get('token_uri'), http_build_query($payload));

                    if (!$response || $this->curl->code != 200) {
                        throw new FirebaseException('Access token generation failed, response: ' . $response);
                    }

                    $response = json_decode($response, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new FirebaseException('Failed to parse access token response: ' . json_last_error_msg());
                    }

                    if (!isset($response['access_token'])) {
                        throw new FirebaseException('Access token not found in response');
                    }

                    return Crypt::encrypt($response['access_token'], $this->getEncryptionKey());
                }, $cacheName);

                return Crypt::decrypt($token, $this->getEncryptionKey());
            } catch (\Exception $e) {
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
     * Generate a JWT for the assertion parameter
     */
    private function generateJWT()
    {
        $now = time();
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];

        $payload = [
            'iss' => $this->config->getClientEmail(),
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $this->config->get('token_uri'),
            'iat' => $now,
            'exp' => $now + 3600  // Token valid for 1 hour
        ];

        // Encode the header and payload
        $base64UrlHeader = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $base64UrlPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        // Concatenate the header and payload
        $unsignedToken = $base64UrlHeader . '.' . $base64UrlPayload;

        // Sign the token using the private key
        $privateKey = $this->config->getPrivateKey();
       // echo $privateKey;
        openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // Base64Url encode the signature
        $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        // Final JWT
        return $unsignedToken . '.' . $base64UrlSignature;
    }

    /**
     * @return string
     */
    private function getEncryptionKey()
    {
        return md5($this->config->getClientEmail() . $this->config->getPrivateKey());
    }
}
