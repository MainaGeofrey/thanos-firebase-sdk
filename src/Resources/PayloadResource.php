<?php

namespace Firebase\Resources;

class PayloadResource
{
    private $message;

    public function __construct()
    {
        $this->message = [
            'notification' => [],
            'data' => [],
        ];
    }

    /**
     * Set notification title and body
     *
     * @param string $title
     * @param string $body
     * @return $this
     */
    public function setNotification(string $title, string $body)
    {
        $this->message['notification'] = [
            'title' => $title,
            'body' => $body
        ];

        return $this;
    }

    /**
     * Add custom data to the payload (converts nested arrays to JSON string)
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function addData(string $key, $value)
    {
        // Convert nested arrays or objects to JSON string
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        $this->message['data'][$key] = $value;

        return $this;
    }

    /**
     * Set the target device or devices (use device token or an array of tokens)
     *
     * @param string|array $token A single device token or an array of device tokens
     * @return $this
     */
    public function setTarget($token)
    {
        if (is_array($token)) {
            $this->message['registration_ids'] = $token;
        } else {
            $this->message['token'] = $token;
        }

        return $this;
    }

    /**
     * Specify the platform for the push notification (Web, Android, Apple)
     *
     * @param string $platform The platform to target ('web', 'android', 'apple')
     * @param array $platformData Customization data for the platform
     * @return $this
     */
    public function setPlatform(string $platform, array $platformData)
    {
        switch (strtolower($platform)) {
            case 'android':
                $this->message['android'] = ['notification' => $platformData];
                break;
            case 'apple':
                $this->message['apns'] = ['payload' => $platformData];
                break;
            case 'web':
                $this->message['webpush'] = ['notification' => $platformData];
                break;
            default:
                throw new \InvalidArgumentException('Unsupported platform type');
        }

        return $this;
    }

    /**
     * Get the complete payload for sending to specific devices or topics
     *
     * @return array
     */
    public function getPayload()
    {
        return ['message' => $this->message];
    }
}
