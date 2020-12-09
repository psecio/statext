<?php

namespace Psecio\Statext;

class Item
{
    protected $meta = [];

    public function __construct(array $data = [])
    {
        $this->load($data);
    }

    public function load(array $data)
    {
        $vars = get_object_vars($this);
        foreach ($data as $index => $value) {
            if (array_key_exists($index, $vars) == true) {
                $this->$index = $value;
            }
        }
    }

    public function __isset($key)
    {
        // See if there's an object property for it
        $properties = get_object_vars($this);
        if (array_key_exists($key, $properties) == true) {
            return true;
        }

        return false;
    }

    public function __get($key)
    {
        // Check the class properties
        $properties = get_object_vars($this);
        if (array_key_exists($key, $properties) == true) {
            return $this->$key;
        }

        return null;
    }

    public function getMeta($key = null)
    {
        if ($key == null) {
            return $this->meta;
        }

        if (array_key_exists($key, $this->meta) == false) {
            return null;
        }
        return $this->meta[$key];
    }
}
