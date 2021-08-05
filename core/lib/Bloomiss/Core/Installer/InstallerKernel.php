<?php

namespace Bloomiss\Core\Installer;

use Bloomiss\Core\BloomissKernel;

/**
 * Étendez BloomissKernel pour gérer le forçage de certains comportements du noyau.
 */
class InstallerKernel extends BloomissKernel
{

    /**
     * Renvoie TRUE si une installation Bloomiss est en cours de tentative.
     *
     * @return void
     */
    public static function installationAttempted()
    {
        // Cela ne peut pas se reposer sur la constante MAINTENANCE_MODE,
        // car cela empêcherait les tests d'utiliser le programme d'installation non interactif,
        // auquel cas Bloomiss n'est installé que dans la même requête,
        // mais le code exécuté par la suite n'implique pas du tout le programme d'installation.
        // @see install_bloomiss()
        global $bloomissVars;
        return isset($bloomissVars['install_state']) && empty($bloomissVars['install_state']['installation_finished']);
    }
}
