<?php

namespace Bloomiss\Core\Logger;

/**
 * @defgroup logging_severity_levels Logging severity levels
 * @{
 *
 * Les définitions de constantes de cette classe correspondent
 * aux niveaux de gravité de journalisation définis dans la RFC 5424, section 6.2.1.
 * PHP fournit des constantes LOG_* prédéfinies à utiliser dans la fonction syslog(),
 * mais leurs valeurs sur les builds Windows ne correspondent pas à la RFC 5424.
 * Le rapport de bogue PHP associé a été fermé avec le commentaire,
 * "Et ce n'est pas non plus un bogue, car Windows vient de ont moins de niveaux de log" et
 * "Donc, le comportement que vous voyez est parfaitement normal."
 *
 * @see https://tools.ietf.org/html/rfc5424#section-6.2.1
 * @see http://bugs.php.net/bug.php?id=18090
 * @see http://php.net/manual/function.syslog.php
 * @see http://php.net/manual/network.constants.php
 * @see \Drupal\Core\Logger\RfcLogLevel::getLevels()
 * @}
 */

/**
 * Définit divers niveaux de gravité de la journalisation.
 *
 * @ingroup logging_severity_levels
 */
class RfcLogLevel
{
    /**
     * gravité Message log -- Emergency : Le système est inutilisable.
     */
    const EMERGENCY = 0;

    /**
     * gravité Message log -- Alert : Des mesures doivent être prises immédiatement.
     */
    const ALERT = 1;

    /**
     * gravité Message log -- Condition critique.
     */
    const CRITICAL = 2;
    /**
     * gravité Message log -- Condition d'erreur.
     */
    const ERROR = 3;
    /**
     * gravité Message log -- Condition d'avertissement.
     */
    const WARNING = 4;
    /**
     * gravité Message log -- Conditions normales mais importantes.
     */
    const NOTICE = 5;
    /**
     * gravité Message log -- Message d'information.
     */
    const INFO = 6;
    /**
     * gravité Message log -- Message de débugage.
     */
    const DEBUG = 7;
}
