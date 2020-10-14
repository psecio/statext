<?php

namespace Psecio\Statext\Item;

class File extends \Psecio\Statext\Item
{
    protected $template = 'default.twig';
    protected $source = [];
    protected $display = [];
    protected $output = [];

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

    public function getContents()
    {
        return $this->source->contents;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function getTitle()
    {
        $title = $this->getMeta('title');
        var_export($title);

        if ($title == null) {
            return $this->getOutputFile();
        }
        return $title;
    }
}
