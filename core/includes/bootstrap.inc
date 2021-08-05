<?php

use Bloomiss\BootStrap\HandleError;
use Bloomiss\Component\Utility\Utility;
use Bloomiss\Core\Utility\Utility as UtilityCore;

/**
 * Fournis un gestionnaire personnalisé pour les erreur
 *
 * @param int           $errorLevel Le niveau d'erreur.
 * @param string        $message    Le message d'erreur.
 * @param string|null   $filename   (optionel) Le nom du fichier où l'erreur à été déclenché.
 * @param int|null      $line       (optionel) le numéro de ligne où l'erreur à été déclenché.
 * @return void
 */


function bloomissLoad()
{
    global $bloomissVars;
    $bloomissVars = isset($bloomissVars) ?$bloomissVars : [];
    class_alias('Bloomiss\Bloomiss', 'Bloomiss');
}

function bloomissErrorHandler(int $errorLevel, string $message, ?string $filename = null, ?int $line = null) : void
{
    static $handleError;
    if (!isset($handleError)) {
        $handleError = new HandleError();
    }
    $handleError::bloomissErrorHandlerReal($errorLevel, $message, $filename, $line);
    //unset($handleError);
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
    var_dump($exception);
}
