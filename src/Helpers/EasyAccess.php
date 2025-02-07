<?php

namespace Firebase\Helpers;

// Suppressing deprecated warnings for PHP >= 8.0 on dynamically setting properties
error_reporting(E_ALL ^ (E_DEPRECATED));

use Firebase\Exceptions\FirebaseException;

class EasyAccess implements \ArrayAccess, \Countable
{
    private static $STRING_KEY = "stringData";

    /**
     * @param mixed $data
     * @throws FirebaseException
     */
    public function __construct($data = [])
    {
        if (is_string($data)) {
            $json = json_decode($data);

            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $json;
                unset($json);
            }
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        if (is_string($data)) {
            return $this->offsetSet(self::$STRING_KEY, $data);
        }

        if (!is_array($data)) {
            throw new FirebaseException("Provided data is not a valid array/object!");
        }

        foreach ($data as $key => $value) {
            // recursively set objects
            if (is_array($value) || is_object($value)) {
                $value = new self($value);
            }

            $this->offsetSet($key, $value);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->offsetExists(self::$STRING_KEY)) {
            return $this->offsetGet(self::$STRING_KEY);
        }

        return json_encode($this);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count((array) $this);
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (!is_null($offset)) {
            $this->{$offset} = $value;
        }
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    /**
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return isset($this->{$offset}) ? $this->{$offset} : null;
    }

    /**
     * Splits the message string and returns a structured array with project ID and messages.
     *
     * @param string $message
     * @return array
     */
    public function formatNotificationResponse($message)
    {
        // Split the string by "projects/"
        $result = explode("projects/", $message);

        if (count($result) === 2) {
            // Extract project ID
            $projectPart = explode("/", $result[1]);
            $projectId = $projectPart[0];

            // Extract the messages part after the project ID
            $messagesPart = explode("messages/", $result[1]);
            if (count($messagesPart) === 2) {
                // Further split the messages part by colon and percent
                $messages = explode("%", $messagesPart[1]);
                return [
                    'project' => $projectId,
                    'messages' => $messages
                ];
            }
        }

        return null; // Return null if the structure doesn't match
    }
}
