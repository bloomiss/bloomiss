<?php

use Bloomiss\BootStrap\Factory;
use Bloomiss\BootStrap\HandleError;

function loadFactory()
{
    static $factory;
    if (!isset($factory)) {
        $factory = new Factory();
    }
    return $factory;
}

function bloomissLoad()
{
    global $bloomissVars;
    $bloomissVars = isset($bloomissVars) ?$bloomissVars : [];
    class_alias('Bloomiss\Bloomiss', 'Bloomiss');
    class_alias('Bloomiss\BootStrap\HandleError', 'BloomissError');
    $bloomissVars['config']['system.logging']['error_level'] = HandleError::ERROR_REPORTING_DISPLAY_VERBOSE;
    //$bloomissVars['config']['system.logging']['error_level'] = HandleError::ERROR_REPORTING_DISPLAY_ALL;
}

function bloomissGetHandleError()
{

    static $handleError;
    if (!isset($handleError)) {
        $handleError = new HandleError();
    }
    return $handleError;
}

function bloomiss()
{

    static $handleError;
    if (!isset($handleError)) {
        $handleError = new HandleError();
    }
    return $handleError;
}

/**
 * Fournis un gestionnaire personnalisé pour les erreur
 *
 * @param int           $errorLevel Le niveau d'erreur.
 * @param string        $message    Le message d'erreur.
 * @param string|null   $filename   (optionel) Le nom du fichier où l'erreur à été déclenché.
 * @param int|null      $line       (optionel) le numéro de ligne où l'erreur à été déclenché.
 * @return void
 */

function bloomissErrorHandler(int $errorLevel, string $message, ?string $filename = null, ?int $line = null) : void
{
    loadFactory()->HandleError::bloomissErrorHandlerReal($errorLevel, $message, $filename, $line);
}

/**
 * Fournit un gestionnaire personnalisée pour les exceptions PHP.
 *
 * Les exceptions non capturées sont celles qui ne sont pas incluses dans un bloc try/catch.
 * Elles sont toujours fatales : l'exécution du script s'arrêtera dès la sortie du gestionnaire d'exceptions
 *
 * @param  Exception|Throwable $exception l'objet Exception qui a été levé
 * @return void
 */
function bloomissExceptionHandler(Exception|Throwable $exception) : void
{
    ini_set('xdebug.show_exception_trace', '0');
  /*  require_once implode(DIRECTORY_SEPARATOR, [ __DIR__ , "errors.inc"]);

    $classError = new BootError();
    try {
        $t = Error::decodeException($exception);
        //Consignez le message au watchdog et renvoyez une page d'erreur à l'utilisateur.
        $classError->bloomissLogErrorFatal($exception);
    //Catch \Throwable, qui couvre à la fois les erreurs et les exceptions.
    } catch (Throwable $error) {
        bloom*issExceptionHandlerAdditional($exception, $error, $classError);
    }*/
    try {
        //Consignez le message au watchdog et renvoyez une page d'erreur à l'utilisateur.
        $exceptionDecoder = loadFactory()->UtilityError::decodeException($exception);
        loadFactory()->HandleError::fatalOn();
        loadFactory()->HandleError::logError($exceptionDecoder);
    } catch (\Throwable $error) {
        bloomissExceptionHandlerAdditionnel($exception, $error);
    }
}

/**
 * Affiche toutes les erreurs supplémentaires détectées lors de la gestion d'une exception.
 *
 * @param \Exception|\Throwable $exception
 *      Le premier objet d'exception qui a été levé.
 * @param \Exception|\Throwable $exception2
 *      Le second objet d'exception qui a été levé.
 * @return void
 */
function bloomissExceptionHandlerAdditionnel($exception, $exception2)
{
    // Une autre exception non interceptée a été levée lors de la gestion de la première.
    // Si nous affichons des erreurs, alors faites-le sans qu'une autre exception non interceptée ne soit levée.
    if (loadFactory()->HandleError::errorDisplayable()) {
        print '<h1>Exception non interceptée supplémentaire levée lors de la gestion de l\'exception.</h1>';
        print sprintf(
            '<h2>Original</h2><p>%s</p>',
            loadFactory()->UtilityError::renderExceptionSafe($exception)
        );
        print sprintf(
            '<h2>Additionnal</h2><p>%s</p>',
            loadFactory()->UtilityError::renderExceptionSafe($exception2)
        );
    }
}
