<?php

/**
 * @file
 * La page PHP qui sentralisent toutes les demandes de page sur une installation Bloomiss.
 *
 * Tout le code Drupal est publié sous la licence publique générale GNU.
 * Voir les fichiers LICENSE.txt dans le répertoire "core".
 */

$autoloader = require_once implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'autoload.php']);
