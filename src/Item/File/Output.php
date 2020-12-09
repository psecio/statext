<?php

namespace Psecio\Statext\Item\File;

class Output extends \Psecio\Statext\Item
{
    const TYPE_STATIC = 'static';
    const TYPE_SOURCE = 'source';

    protected $path = '';
    protected $type = 'static';
    protected $layout = 'layout.twig';
    protected $icon = '/assets/img/icon/default.png';
    protected $name = '';
}
