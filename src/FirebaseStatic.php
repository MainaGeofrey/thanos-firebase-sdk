<?php

namespace Firebase;

use Firebase\Exceptions\FirebaseException;
use Firebase\Helpers\EasyAccess;
use Firebase\Helpers\Signature;
use Firebase\Resources\CurlResource;
use Firebase\Services\OAuthService;



class FirebaseStatic
{
    private static array $pool = [];
    private static Config $config;
    private static CurlResource $curl;
    private static Signature $signature;
    private static OAuthService $oauth;

    /**
     * @param mixed $config
     * @throws FirebaseException
     */
    public static function init($config): void
    {
        try {
            self::initResources($config);
            self::initServices();

            if (empty(self::$pool)) {
                $reflection = new \ReflectionClass(self::class);

                foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                    $property->setAccessible(true);
                    if ($object = $property->getValue()) {
                        self::$pool[$property->getName()] = $object;
                    }
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
     */
    private static function initResources($config): void
    {
        self::$config = self::$pool['config'] ?? new Config($config);
        self::$curl = self::$pool['curl'] ?? new CurlResource(self::$config);
        self::$signature = self::$pool['signature'] ?? new Signature(self::$config->get('client_secret'));
    }

    /**
     * @return void
     */
    private static function initServices(): void
    {
        self::$oauth = self::$pool['oauth'] ?? new OAuthService(self::$config, self::$curl, self::$signature);
        $token = self::$oauth->getAccessToken();

    }

    /**
     * @throws FirebaseException
     * @return void
     */
    private static function checkInitialized(): void
    {
        if (empty(self::$pool)) {
            throw new FirebaseException('Firebase Thanos SDK is not initialized, please call static method init() first');
        }
    }
}
