<?php

namespace Kevinrob\GuzzleCache;

class KeyValueHttpHeader
{
    /**
     * Take from https://github.com/hapijs/wreck.
     */
    const REGEX_SPLIT = '/(?:^|(?:\s*\,\s*))([^\x00-\x20\(\)<>@\,;\:\\\\"\/\[\]\?\=\{\}\x7F]+)(?:\=(?:([^\x00-\x20\(\)<>@\,;\:\\\\"\/\[\]\?\=\{\}\x7F]+)|(?:\"((?:[^"\\\\]|\\\\.)*)\")))?/';

    /**
     * @var string[]
     */
    protected $values = [];

    /**
     * @param array $values
     */
    public function __construct(array $values)
    {
        foreach ($values as $value) {
            $matches = [];
            if (preg_match_all(self::REGEX_SPLIT, $value, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $val = '';
                    if (count($match) == 3) {
                        $val = $match[2];
                    } elseif (count($match) > 3) {
                        $val = $match[3];
                    }

                    $this->values[$match[1]] = $val;
                }
            }
        }
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        // For performance, we can use isset,
        // but it will not match if value == 0
        return isset($this->values[$key]) || array_key_exists($key, $this->values);
    }

    /**
     * @param string $key
     * @param string $default the value to return if don't exist
     * @return string
     */
    public function get($key, $default = '')
    {
        if ($this->has($key)) {
            return $this->values[$key];
        }

        return $default;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return count($this->values) === 0;
    }
}
