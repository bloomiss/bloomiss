<?php

namespace Bloomiss\Core\Utility;

use Bloomiss\Component\Render\FormattableMarkup;
use Bloomiss\Component\Utility\Xss;
use Bloomiss\Core\Database\DatabaseExceptionWrapper;
use Exception;
use PDOException;
use Throwable;

/**
 * Bloomiss classe utilitaire pour les erreurs.
 */
class Error
{

    /**
     * Le niveau de sévérité du l'erreur
     *
     * @var int
     */
    const ERROR = 3;

    /**
     * Un tableau de fonction  igniorer
     *
     * @var array
     */
    private static $ignoredFunctions = ['bloomissErrorHandler', 'bloomissExceptionHandler'];

    private static function getCaller($backtrace)
    {
        //var_dump($backtrace);
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

        $backtrace = $exception->getTrace();

        //Ajoutez la ligne qui lève l'exception au backtrace.
        array_unshift($backtrace, ['line' => $exception->getLine(), 'file' => $exception->getFile()]);

        //Pour les erreurs PDOException, nous essayons de renvoyer l'appelant initial,
        //sauter les fonctions internes de la couche de base de données.
        if ($exception instanceof PDOException || $exception instanceof DatabaseExceptionWrapper) {
            trigger_error("Revenir ici", E_USER_ERROR);
        }

        $caller = static::getLastcaller($backtrace);
        
        return [
            '%type' => get_class($exception),
            // Le gestionnaire d'exception PHP standard considère que le message
            // d'exception est en texte brut. Nous imitons ce comportement ici.
            '@message' => $message,
            '%function' => $caller['function'],
            '%file' => $caller['file'],
            '%line' => $caller['line'],
            'severity_level' => static::ERROR,
            'backtrace' => $backtrace,
            '@backtrace_string' => $exception->getTraceAsString(),
            'exception' => $exception
        ];
    }

    /**
     * Renvoie un message d'erreur d'exception sans autres exceptions.
     *
     * @param  Exception|Throwable $exception l'objet Exception qui a été levé
     *
     * @return string Un message d'erreur.
     */
    public static function renderExceptionSafe($exception)
    {
        $decoder = static::decodeException($exception);
        $backtrace = $decoder['backtrace'];
        unset($decoder['backtrace'], $decoder['exception'], $decoder['severity_level']);
        //Supprimer main()
        array_shift($backtrace);

        //Même s'il est possible que cette méthode soit appelée sur un site public,
        // elle n'est appelée que lorsque le gestionnaire d'exceptions lui-même a lancé une exception,
        // ce qui signifie normalement qu'un changement de code a fait que le système ne fonctionne plus correctement
        // (par opposition à un erreur déclenchée par l'utilisateur), nous supposons donc
        // qu'il est sûr d'inclure une trace arrière détaillée.
        //$decoder['@backtrace'] = static::formatBacktrace($backtrace);
        return new FormattableMarkup(
            '%type: @message dans %function (ligne %line de %file) '.
            '<pre class="backtrace">@backtrace</pre>',
            $decoder
        );
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

    public static function formatBacktrace(array $backtrace)
    {
        $ret = '';
        foreach ($backtrace as $trace) {
            $call = ['function' => 'main',  'args' => []];

            if (isset($trace['class'])) {
                $call['function'] = sprintf('%s%s%s', $trace['class'], $trace['type'], $trace['function']);
            } elseif (isset($trace['function'])) {
                $call['function'] = $trace['function'];
            }

            if (isset($trace['args'])) {
                foreach ($trace['args'] as $arg) {
                    $call['args'][] = ucfirst(gettype($arg));
                    if (is_scalar($arg)) {
                        $call['args'][] = is_string($arg) ?
                         sprintf("'%s'", loadFactory()->Xss::filter($arg)) :
                         $arg;
                    }
                }
            }

            $line = '';
            if (isset($trace['line'])) {
                $line = " (Ligne: {$trace['line']})";
            }

            $ret .= sprintf("%s (%s)%s\n", $call['function'], implode(', ', $call['args']), $line);
        }

        return $ret;
    }
}
