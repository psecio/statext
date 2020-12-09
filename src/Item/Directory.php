<?php

namespace Psecio\Statext\Item;

class Directory extends \Psecio\Statext\Item
{
    protected $path = '';
    protected $shortPath = '';
    protected $type = 'directory';
    protected $meta = [];
    protected $output = [];
    protected $children = [];

    public function getPath()
    {
        return $this->path;
    }

    public function getOutputPath()
    {
        return $this->output->path;
    }

    public function isStatic()
    {
        return $this->output->static;
    }

    public function getChildren()
    {
        return $this->children;
    }
    public function setChildren(\Psecio\Statext\Collection $children)
    {
        $this->children = $children;
    }
}
