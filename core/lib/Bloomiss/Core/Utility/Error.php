<?php

namespace Bloomiss\Core\Utility;

use Exception;
use Throwable;

/**
 * Bloomiss classe utilitaire pour les erreurs.
 */
class Error
{

    private static $ignoredFunctions = ['bloomissErrorHandler'];

    private static function getCaller($backtrace)
    {
        $call = $backtrace[0];

        //Le deuxième appel nous donne la fonction d'où provient l'appel.
        $format     = "%s%s%s()";
        $function = (isset($backtrace[1])) ? $backtrace[1]['function'] : 'main';
        $class = (isset($backtrace[1]) && isset($backtrace[1]['class'])) ? $backtrace[1]['class'] : '';
        $type  = (isset($backtrace[1]) && isset($backtrace[1]['class'])) ? $backtrace[1]['type'] : '';
        
        $call['function'] = sprintf($format, $class, $type, $function);

        return $call;
    }
    /**
     * Décode une exception et récupère le bon appelant.
     *
     * @param  Exception|Throwable $exception l'objet Exception qui a été levé
     *
     * @return array Une erreur dans le format attendu par bloomissLogError().
     */
    public static function decodeException(Exception|Throwable $exception)
    {
        $message = $exception->getMessage();
        var_dump([
            'message' => $message
        ]);
        throw new Exception('Reveni à la fonction decodeException()', 1);
    }

    /**
     * Récupère le dernier appelant depuis backtrace.
     *
     * @param array $backtrace Le backtrace standard de PHP, passé par référence.
     *
     * @return array Un tableau associative avec pour clée 'file', 'line', 'function'
     */
    public static function getLastcaller(array &$backtrace):array
    {
        // Les erreurs qui se produisent dans les fonctions internes de PHP
        // ne génèrent pas d'informations sur le fichier et la ligne. Ignorez les fonctions ignorées.
        
        while (($backtrace && !isset($backtrace[0]['line'])) ||
            (isset($backtrace[1]['function']) && in_array($backtrace[1]['function'], static::$ignoredFunctions))
        ) {
            array_shift($backtrace);
        }
        
        // La première trace est l'appel lui-même.
        // Il nous donne la ligne et le fichier du dernier appel.
        return static::getCaller($backtrace);
    }

    public static function tryCallUserFunc(array &$backtrace, string $other, ?int $othreRef = null):void
    {
        //pass
        var_dump($backtrace, $other, $othreRef);
    }
}
