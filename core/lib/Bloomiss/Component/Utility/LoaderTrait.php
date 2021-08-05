<?php

namespace Bloomiss\Component\Utility;

use InvalidArgumentException;
use RuntimeException;

trait LoaderTrait
{

    private static $loader;

    private static $listClass = [
        'Unicode'   => __NAMESPACE__ . '\\' . 'Unicode',
        'UrlHelper' => __NAMESPACE__ . '\\' . 'UrlHelper',
        'Html'      => __NAMESPACE__ . '\\' . 'Html',
    ];

    private static $classLoaded = [];
    public static function load()
    {
        if (!isset(static::$loader)) {
            static::$loader = new static();
        }
        return static::$loader;
    }

    public function __get($name)
    {
        if (isset(static::$listClass) && is_array(static::$listClass) && !isset(static::$listClass[$name])) {
            throw new InvalidArgumentException(sprintf(
                'La classe (%s) ne fait pas partie de la liste des classe.\n',
                $name
            ));
        }
        $class = static::$listClass[$name];
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf(
                'Bien que la classe (%s) fasse partie de la liste, celle-ci n\'existe pas.',
                $name
            ));
        }

        if (!isset(static::$classLoaded) || !is_array(static::$classLoaded)) {
            throw new RuntimeException(
                'Une erreur inatendu est survenue.\n
                La variable static::$classLoaded semble inexistant.'
            );
        }

        //Tester si l'objet existe, sinon la construire.
        if (!isset(static::$classLoaded[$name])) {
            static::$classLoaded[$name] =  $class::load();
        }
        return static::$classLoaded[$name];
    }
}
