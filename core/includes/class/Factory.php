<?php

namespace Bloomiss\BootStrap;

use Bloomiss\Component\Utility\Html;
use Bloomiss\Component\Utility\Xss;
use Bloomiss\Core\Utility\Error;

class Factory
{

    private $functionLoaded = [];

    public function __construct()
    {
        $this->set('handleError', new HandleError);
        $this->set('utilityError', new Error);
        $this->set('xss', new Xss);
        $this->set('html', new Html);
    }
    public function set($name, $obj)
    {
        $this->functionLoaded[$name] = $obj;
    }

    public function get($name)
    {
        return $this->functionLoaded[$name];
    }
}
