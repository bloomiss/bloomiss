<?php

namespace Bloomiss\BootStrap;

use Bloomiss\Bloomiss;
use Bloomiss\Component\Render\FormattableMarkup;
use Bloomiss\Component\Utility\Xss;
use Bloomiss\Core\DependencyInjection\ContainerNotInitializedException;
use Bloomiss\Core\Installer\InstallerKernel;
use Bloomiss\Core\Logger\RfcLogLevel;
use Bloomiss\Core\Render\Markup;
use Bloomiss\Core\Utility\Error as UtilityError;
use Exception;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HandleError
{
    /**
    * Niveau repport Erreur : Affiche aucune erreur
    */
    const ERROR_REPORTING_HIDE = 'hide';

    /**
    * Niveau repport Erreur : Affiche les erreur et Avertissement
    */
    const ERROR_REPORTING_DISPLAY_SOME = 'some';

    /**
    * Niveau repport Erreur : Affiche toutes les message
    */
    const ERROR_REPORTING_DISPLAY_ALL = 'all';

    /**
    * Niveau repport Erreur : Affiche toutes les message, plus les informations sur les traces.
    */
    const ERROR_REPORTING_DISPLAY_VERBOSE = 'verbose';

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
        E_RECOVERABLE_ERROR => ["Erreur fatale r??cup??rable" , RfcLogLevel::ERROR],
        E_DEPRECATED        => ["Fonction obsol??te"         , RfcLogLevel::DEBUG],
        E_USER_DEPRECATED   => ["Fonction user obsol??te"    , RfcLogLevel::DEBUG],
    ];

    private static $fatal = false;

    /**
     * Fournis un gestionnaire personnalis?? pour les erreur
     *
     * @param int           $errorLevel Le niveau d'erreur.
     * @param string        $message    Le message d'erreur.
     * @param string|null   $filename   (optionel) Le nom du fichier o?? l'erreur ?? ??t?? d??clench??.
     * @param int|null      $line       (optionel) le num??ro de ligne o?? l'erreur ?? ??t?? d??clench??.
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
            //Nous traitons les erreurs r??cup??rables comme fatales.
            $recoverable = $errorLevel == E_RECOVERABLE_ERROR;

            //@TODO Je traite aussi les erreur utilisateur comme fatal
            $errorUser = $errorLevel == E_USER_ERROR;

            // Comme les m??thodes __toString() ne doivent pas lever d'exceptions (erreurs r??cup??rables)
            // en PHP, nous leur permettons de d??clencher une erreur fatale en
            // ??mettant une erreur utilisateur ?? l'aide de trigger_error().
            $toString = $errorLevel == E_USER_ERROR &&
                substr($caller['function'], -strlen('__toString()')) == '__toString()';
            ($recoverable || $toString || $errorUser) ? static::fatalOn() : static::fatalOff();

            static::logError([
                '%type' => isset(static::$type[$errorLevel]) ? $severityMsg : 'Erreur inconnu',
                // Le gestionnaire d'erreur PHP standard consid??re que les messages d'erreur sont HTML.
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
     *      Un tableau avec les cl??s suivantes??: %type, @message, %function, %file, %line,
     *      @backtrace_string, gravit??_level, backtrace et exception.
     *      Tous les param??tres sont en texte brut, ?? l'exception de @message, qui doit ??tre une cha??ne HTML,
     *      backtrace, qui est un backtrace PHP standard, et exception, qui est l'objet d'exception
     *      (ou NULL si l'erreur n'est pas une exception ).
     *
     * @param bool $fatal
     *      TRUE pour??:
     *      - Une exception est lev??e et n'est pas intercept??e par autre chose.
     *      - Une erreur fatale r??cup??rable, qui est une erreur fatale.
     *      Les erreurs fatales non r??cup??rables ne peuvent pas ??tre enregistr??es par Bloomiss.
     * @return void
     */
    public static function logError($error)
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

        //Enregistrez les erreurs fatales, afin que les d??veloppeurs puissent les trouver et les d??boguer.
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
                // Lorsqu'il est appel?? depuis l'interface de ligne de commande,
                // affichez simplement un message en texte brut.
                // Ne devrait pas traduire la cha??ne pour ??viter que des erreurs ne produisent plus d'erreurs.
                $response->setContent(html_entity_decode(strip_tags(new FormattableMarkup(
                    '%type: @message dans %function (ligne %line de %file).',
                    $error
                ))) . PHP_EOL);
                $response->send();
                exit(1);
            }
        }

        if (Bloomiss::hasRaquest() && Bloomiss::request()->isXmlHttpRequest()) {
            trigger_error("Revenir ici");
        } else {
            // Afficher le message si le niveau de rapport d'erreur actuel permet l'affichage de ce type de message,
            // et ce sans condition dans update.php.
            $message = '';
            $class = null;
            if (static::errorDisplayable($error)) {
                $class = 'error';

                // Si le type d'erreur est ????Notice User????,
                // traitez-le comme une information de d??bogage au lieu d'un message d'erreur.
                if ($error['%type'] == 'Notice User') {
                    $error['%type'] = 'Debug';
                    $class = 'status';
                }

                // Essayez de r??duire la verbosit?? en supprimant DRUPAL_ROOT
                // du chemin d'acc??s au fichier dans le message.
                // Cela ne se produit pas pour la (fausse) s??curit??.
                if (Bloomiss::hasService('kernel')) {
                    trigger_error("Revenir ici");
                }

                //V??rifiez si le rapport d'erreur d??taill?? est activ??.
                $errorLevel = static::getErrorLevel();

                if ($errorLevel != static::ERROR_REPORTING_DISPLAY_VERBOSE) {
                    // Sans journalisation d??taill??e, utilisez un message simple.
                    // Nous utilisons \Bloomiss\Component\Render\FormattableMarkup directement ici,
                    // plut??t que d'utiliser t() car nous sommes au milieu de la gestion des erreurs
                    // et nous ne voulons pas que t() provoque d'autres erreurs.
                    $message = new FormattableMarkup(
                        '%type: @message dans %function (ligne %line de %file).',
                        $error
                    );
                } else {
                    // Avec la journalisation d??taill??e, nous inclurons ??galement un backtrace.
                    // La premi??re trace est l'erreur elle-m??me, d??j?? contenue dans le message.
                    // Alors que la deuxi??me trace est la source de l'erreur et est ??galement contenue dans le message,
                    // le message ne contient pas de valeurs d'argument,
                    // nous l'affichons donc ?? nouveau dans la trace arri??re.
                    array_shift($backtrace);
                    //G??n??rez une backtrace contenant uniquement des valeurs d'argument scalaires.
                    $error['@backtrace'] = UtilityError::formatBacktrace($backtrace);
                    $message = new FormattableMarkup(
                        '%type: @message dans %function (ligne %line de %file) '.
                        '<pre class="backtrace">@backtrace</pre>',
                        $error
                    );
                }
            }
            
            if ($fatal) {
                // Nous nous rabattons sur une page de maintenance ?? ce stade,
                // car la g??n??ration de la page elle-m??me peut g??n??rer des erreurs.
                // Ne devrait pas traduire la cha??ne pour ??viter que des erreurs ne produisent plus d'erreurs.
                $message = "Le site web ?? rencontr?? une erreur innatendu. Veuillez essayer plus tard. <br />" .
                PHP_EOL .
                $message;
                
                if ($isInstaller) {
                    trigger_error("Revenir ici");
                }

                $response->setContent($message);
                $response->setStatusCode(500, '500 Service unavailable (with message)');

                $response->send();
                // Une exception doit arr??ter l'ex??cution du script.
                exit;
            }

            if ($message) {
                if (Bloomiss::hasService('session')) {
                    trigger_error("Revenir ici");
                } else {
                    print "<p>{$message}</p>" . PHP_EOL;
                }
            }
        }
    }

    /**
     * D??termine si une erreur doit ??tre affich??e.
     *
     * En mode maintenance ou lorsque error_level est ERROR_REPORTING_DISPLAY_ALL,
     * toutes les erreurs doivent ??tre affich??es.
     *
     * Pour ERROR_REPORTING_DISPLAY_SOME, $error sera examin?? pour d??terminer s'il doit ??tre affich??.
     *
     * @param $error Erreur facultative ?? examiner pour ERROR_REPORTING_DISPLAY_SOME.
     *
     * @return bool TRUE si l'erreur peut ??tre affich??.
     */
    public static function errorDisplayable($error = null):bool
    {
        if (defined('MAINTENANCE_MODE')) {
            return true;
        }
        $errorLevel = static::getErrorLevel();

        if ($errorLevel == static::ERROR_REPORTING_DISPLAY_ALL ||
            $errorLevel == static::ERROR_REPORTING_DISPLAY_VERBOSE
        ) {
            return true;
        }

        if ($errorLevel == static::ERROR_REPORTING_DISPLAY_SOME && isset($error)) {
            return $error['%type'] != 'Notice' && $error['%type'] != 'Avertissement Strict';
        }

        return false;
    }

    /**
     * Renvoie le niveau d'erreur actuel.
     *
     * Cette fonction ne doit ??tre utilis??e que pour obtenir le niveau d'erreur actuel
     * avant le d??marrage du noyau ou avant l'installation de Bloomiss.
     * Dans toutes les autres situations, le code suivant est pr??f??rable??:
     * @code
     * \Drupal::config('system.logging')->get('error_level');
     * @endcode
     *
     * @return string Le niveau actuel de l'erreur
     */
    private static function getErrorLevel():string
    {

        // Augmentez le niveau d'erreur au maximum pour le programme d'installation,
        // afin que les utilisateurs puissent d??poser des rapports de bogues appropri??s
        // pour les erreurs du programme d'installation. La valeur renvoy??e est diff??rente de celle ci-dessous,
        // car le programme d'installation dispose en fait d'un service 'config.factory',
        // qui lit la valeur par d??faut 'error_level' ?? partir de la configuration par d??faut du module syst??me
        // et la valeur par d??faut n'est pas d??taill??e.
        // @voir error_displayable()
        if (InstallerKernel::installationAttempted()) {
            return static::ERROR_REPORTING_DISPLAY_VERBOSE;
        }
        $errorLevel = null;

        // Essayez d'obtenir la configuration du niveau d'erreur ?? partir de la base de donn??es.
        // Si cela ??choue, par exemple si la connexion ?? la base de donn??es n'existe pas,
        // essayez de la lire ?? partir de settings.php.
        try {
            $errorLevel = Bloomiss::config('system.logging')->get('error_level');
        } catch (Exception $e) {
            global $bloomissVars;
            $errorLevel = isset($bloomissVars['config']['system.logging']['error_level']) ?
            $bloomissVars['config']['system.logging']['error_level'] :
            static::ERROR_REPORTING_HIDE;
        }

        // S'il n'y a pas de conteneur ou s'il n'a pas de service config.factory,
        // nous sommes peut-??tre dans une situation d'erreur marginale en essayant
        // de servir une requ??te r??guli??re sur un site public, utilisez donc la valeur par d??faut non d??taill??e.
        return $errorLevel ?: static::ERROR_REPORTING_DISPLAY_ALL;
    }

    public static function fatalOn()
    {
        static::$fatal = true;
    }

    public static function fatalOff()
    {
        static::$fatal = false;
    }
}
