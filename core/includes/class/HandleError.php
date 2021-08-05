<?php

namespace Bloomiss\BootStrap;

use Bloomiss\Bloomiss;
use Bloomiss\Component\Utility\Xss;
use Bloomiss\Core\Installer\InstallerKernel;
use Bloomiss\Core\Logger\RfcLogLevel;
use Bloomiss\Core\Render\Markup;
use Bloomiss\Core\Utility\Error as UtilityError;
use Exception;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class HandleError
{
    private static $type = [
        E_ERROR             => ["Erreur"                    , RfcLogLevel::ERROR],
        E_WARNING           => ["Avertissement"             , RfcLogLevel::WARNING],
        E_PARSE             => ["Erreur analyse"            , RfcLogLevel::ERROR],
        E_NOTICE            => ["Notice"                    , RfcLogLevel::NOTICE],
        E_CORE_ERROR        => ["Erreur noyau"              , RfcLogLevel::ERROR],
        E_CORE_WARNING      => ["Avertissement noyau"       , RfcLogLevel::WARNING],
        E_COMPILE_ERROR     => ["Erreur compilation"        , RfcLogLevel::ERROR],
        E_COMPILE_WARNING   => ["Avertissement compilation" , RfcLogLevel::WARNING],
        E_USER_ERROR        => ["Erreur User"               , RfcLogLevel::ERROR],
        E_USER_WARNING      => ["Avertissement User"        , RfcLogLevel::WARNING],
        E_USER_NOTICE       => ["Notice User"               , RfcLogLevel::NOTICE],
        E_STRICT            => ["Avertissement Strict"      , RfcLogLevel::DEBUG],
        E_RECOVERABLE_ERROR => ["Erreur fatale récupérable" , RfcLogLevel::ERROR],
        E_DEPRECATED        => ["Fonction obsolète"         , RfcLogLevel::DEBUG],
        E_USER_DEPRECATED   => ["Fonction user obsolète"    , RfcLogLevel::DEBUG],
    ];

    private $utilityCore;
    private $utility;
    private $markup;
    //private $utilityError;
    // private $utilityXss;

    private static $fatal = false;
    private static $lanch = '';

   /* public function __construct()
    {
        $this->utilityCore = bloomissGetUtilityCore();
        $this->utility = bloomissGetUtility();

        $this->markup = 'Markup';
        //$this->utilityXss = new Xss;
    }*/
    /**
     * Fournis un gestionnaire personnalisé pour les erreur
     *
     * @param int           $errorLevel Le niveau d'erreur.
     * @param string        $message    Le message d'erreur.
     * @param string|null   $filename   (optionel) Le nom du fichier où l'erreur à été déclenché.
     * @param int|null      $line       (optionel) le numéro de ligne où l'erreur à été déclenché.
     * @return void
     */
    public static function bloomissErrorHandlerReal(
        int $errorLevel,
        string $message,
    ) : void {
        if ($errorLevel & error_reporting()) {
            list($severityMsg, $severityLevel) = static::$type[$errorLevel];
            $backtrace = debug_backtrace();
            $caller = UtilityError::getLastcaller($backtrace);
            //$caller = UtilityError::getLastcaller($backtrace);
            //Nous traitons les erreurs récupérables comme fatales.
            $recoverable = $errorLevel == E_RECOVERABLE_ERROR;

            // Comme les méthodes __toString() ne doivent pas lever d'exceptions (erreurs récupérables)
            // en PHP, nous leur permettons de déclencher une erreur fatale en
            // émettant une erreur utilisateur à l'aide de trigger_error().
            $toString = $errorLevel == E_USER_ERROR &&
                substr($caller['function'], -strlen('__toString()')) == '__toString()';
            ($recoverable || $toString) ? static::fatalOn() : static::fatalOff();

            static::logError([
                '%type' => isset(static::$type[$errorLevel]) ? $severityMsg : 'Erreur inconnu',
                // Le gestionnaire d'erreur PHP standard considère que les messages d'erreur sont HTML.
                // Nous imitons ce comportement ici.
                '@message' => Markup::create(Xss::filterAdmin($message)),
                '%function' => $caller['function'],
                '%file' => $caller['file'],
                '%line' => $caller['line'],
                'severity_level' => $severityLevel,
                'backtrace' => $backtrace,
                '@backtrace_string' => (new Exception())->getTraceAsString(),
                'exception' => null
            ]);
        }
    }

    /**
     * Enregistre une erreur ou une exception PHP et affiche une page d'erreur dans les cas fatals.
     *
     * @param $error
     *      Un tableau avec les clés suivantes : %type, @message, %function, %file, %line,
     *      @backtrace_string, gravité_level, backtrace et exception.
     *      Tous les paramètres sont en texte brut, à l'exception de @message, qui doit être une chaîne HTML,
     *      backtrace, qui est un backtrace PHP standard, et exception, qui est l'objet d'exception
     *      (ou NULL si l'erreur n'est pas une exception ).
     *
     * @param bool $fatal
     *      TRUE pour :
     *      - Une exception est levée et n'est pas interceptée par autre chose.
     *      - Une erreur fatale récupérable, qui est une erreur fatale.
     *      Les erreurs fatales non récupérables ne peuvent pas être enregistrées par Bloomiss.
     * @return void
     */
    private static function logError($error)
    {
        $fatal = static::$fatal;
        $isInstaller = InstallerKernel::installationAttempted();

        //Backtrace, exception et 'severity_level' ne sont pas des valeurs de remplacement valides pour t().
        $backtrace = $error['backtrace'];
        $severityLevel = $error['severity_level'];
        $exception = $error['exception'];
        unset($error['backtrace'], $error['severity_level'], $error['exception']);

        $response = new Response();

        // N'appelez le loggeur que si une concepteur de loggeur est disponible.
        // Cela peut se produire s'il y a une erreur lors de la reconstruction du conteneur ou lors de l'installation.
        if (Bloomiss::hasService('logger.factory')) {
            trigger_error("Revenir ici", E_USER_ERROR);
        }

        //Enregistrez les erreurs fatales, afin que les développeurs puissent les trouver et les déboguer.
        if ($fatal) {
            error_log(sprintf(
                '%s: %s dans %s sur la ligne %d %s',
                $error['%type'],
                $error['@message'],
                $error['%file'],
                $error['%line'],
                $error['@backtrace_string'],
            ));
            if (PHP_SAPI === 'cli') {
                trigger_error("Revenir ici", E_USER_ERROR);
            }
        }
        trigger_error("Revenir ici");

        var_dump($fatal, $isInstaller);
    }

    public static function logErrorm($error)
    {
        UtilityError::getLastcaller($debugBacktrace);
    }

    private static function fatalOn()
    {
        static::$fatal = true;
    }

    private static function fatalOff()
    {
        static::$fatal = false;
    }

    public static function testStatic()
    {
        UtilityError::getLastcaller($debugBacktrace);
        var_dump(static::class);
    }
}
