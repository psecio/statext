<?php

namespace Psecio\Statext;

class Collection implements \Iterator, \Countable
{
    protected $items = [];
    protected $position = 0;

    public function __construct(array $items = [])
    {
        if (!empty($items)) {
            $this->setItems($items);
        }
    }

    public function setItems(array $items)
    {
        $this->items = $items;
    }

    public function add($item)
    {
        $this->items[] = $item;
    }

    public function rewind() {
        $this->position = 0;
    }

    public function current() {
        return $this->items[$this->position];
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        $this->position++;
    }

    public function valid() {
        return isset($this->items[$this->position]);
    }

    public function count()
    {
        return count($this->items);
    }
}
