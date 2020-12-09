<?php

namespace Psecio\Statext\Item;

class File extends \Psecio\Statext\Item
{
    protected $source = [];
    protected $display = [];
    protected $output = [];
    protected $type = 'file';

    public function __construct(array $data = [])
    {
        // Set the output filename according to the input
        parent::__construct($data);

        if (isset($data['display']->file) == false) {
            $this->display->file = str_replace('.md', '.html', $this->source->name);
        }
    }

    public function getSourcePath()
    {
        return $this->source->path;
    }

    public function getSourceName()
    {
        return $this->source->name;
    }

    public function getOutputFile()
    {
        return $this->output->file;
    }

    public function getOutputPath()
    {
        return $this->output->path;
    }

    public function getDisplayPath()
    {
        return $this->display->path;
    }

    public function getOutputType()
    {
        return $this->output->type;
    }

    public function getContents()
    {
        return $this->source->contents;
    }

    public function getLayout()
    {
        return $this->output->layout;
    }

    public function getTitle()
    {
        $title = $this->getMeta('title');
        if ($title == null) {
            return $this->getOutputFile();
        }
        return $title;
    }

    public function getDescription()
    {
        $desc = $this->getMeta('description');
        return ($desc == null) ? '' : $desc;
    }
}
