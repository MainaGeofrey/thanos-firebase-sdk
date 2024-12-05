<?php

namespace Firebase;

use Firebase\Exceptions\FirebaseException;

class Config
{
    private $data = [];

    const MANDATORY_FIREBASE_KEYS = [
        'type',
        'project_id',
        'private_key_id',
        'private_key',
        'client_email',
        'client_id',
        'auth_uri',
        'token_uri',
        'auth_provider_x509_cert_url',
        'client_x509_cert_url'
    ];

    /**
     * @param array $firebaseCredentials
     * @throws FirebaseException
     */
    public function __construct(array $firebaseCredentials)
    {
        if (empty($firebaseCredentials) || !is_array($firebaseCredentials)) {
            throw new FirebaseException("Invalid Firebase credentials provided. It must be a non-empty array.");
        }

        $this->data = $firebaseCredentials;

        $this->validate();
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->data;
    }

    /**
     * @return void
     * @throws FirebaseException
     */
    private function validate()
    {
        foreach (self::MANDATORY_FIREBASE_KEYS as $key) {
            if (!isset($this->data[$key]) || empty($this->data[$key])) {
                throw new FirebaseException("Mandatory field `$key` is missing in the Firebase credentials");
            }
        }
    }

    /**
     * @return string
     */
    public function getProjectId()
    {
        return $this->get('project_id');
    }

    /**
     * @return string
     */
    public function getClientEmail()
    {
        return $this->get('client_email');
    }

    /**
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->get('private_key');
    }

    /**
     * @return array
     */
    public function getHttpHeaders()
    {
        return isset($this->data['http_headers']) ? $this->data['http_headers'] : [];
    }
}
