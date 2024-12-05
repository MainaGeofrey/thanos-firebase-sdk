<?php

namespace Firebase;

use Firebase\Exceptions\FirebaseException;
use Firebase\Helpers\EasyAccess;
use Firebase\Helpers\Signature;
use Firebase\Resources\CurlResource;
use Firebase\Services\OAuthService;

class Firebase
{
    private static array $pool = [];

    //
    // Configuration and resources
    //
    private Config $config;
    private CurlResource $curl;
    private Signature $signature;

    //
    // Services
    //
    private OAuthService $oauth;
    /**
     * @param mixed $config
     */
    public function __construct($config)
    {
        try {
            $this->initResources($config);
            $this->initServices();

            if (empty(self::$pool)) {
                foreach (get_object_vars($this) as $key => $value) {
                    self::$pool[$key] = $value;
                }
            }
        } catch (\Throwable $e) {
            self::$pool = [];

            throw new FirebaseException('Firebase Thanos SDK initialization failed: ' . $e->getMessage());
        }
    }



    /**
     * @param mixed $config
     * @return void
     * @throws FirebaseException
     */
    public function initResources($config): void
    {
        $this->config = self::$pool['config'] ?? new Config($config);
        $this->curl = self::$pool['curl'] ?? new CurlResource($this->config);
        $this->signature = self::$pool['signature'] ?? new Signature($this->config->get('client_secret'));
    }
    /**
     * @return void
     * @throws FirebaseException
     */
    public function initServices(): void
    {
        $this->oauth = self::$pool['oauth'] ?? new OAuthService($this->config, $this->curl, $this->signature);
        $token = $this->oauth->getAccessToken();


    }


}
