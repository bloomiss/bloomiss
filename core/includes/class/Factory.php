<?php

namespace Bloomiss\BootStrap;

use Bloomiss\Component\Utility\Html;
use Bloomiss\Component\Utility\Unicode;
use Bloomiss\Component\Utility\Xss;
use Bloomiss\Core\Render\Markup;
use Bloomiss\Core\Utility\Error;

class Factory
{

    private $functionLoaded = [];

    private $listClass = [
        'HandleError'   => 'Bloomiss\BootStrap\HandleError',
        'Html'          => 'Bloomiss\Component\Utility\Html',
        'Unicode'       => 'Bloomiss\Component\Utility\Unicode',
        'Xss'           => 'Bloomiss\Component\Utility\Xss',
        'Markup'        => 'Bloomiss\Core\Render\Markup',
        'UtilityError'  => 'Bloomiss\Core\Utility\Error',
        //'UrlHelper'     => 'UrlHelper',
    ];
  

    private function set($name, $obj)
    {
        $this->functionLoaded[$name] = $obj;
    }

    private function get($name)
    {
        return $this->functionLoaded[$name];
    }

    private function loaded($name)
    {
        return isset($this->functionLoaded[$name]) && is_object($this->functionLoaded[$name]);
    }

    public function __get($name)
    {
        if (!$this->loaded($name)) {
            $this->set($name, new $this->listClass[$name]);
        }
        return $this->get($name);
    }
}
