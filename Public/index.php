<?php

/**
 * @file
 * La page PHP qui sentralisent toutes les demandes de page sur une installation Bloomiss.
 *
 * Tout le code Drupal est publié sous la licence publique générale GNU.
 * Voir les fichiers LICENSE.txt dans le répertoire "src".
 */

use Bloomiss\Core\BloomissKernel;
use Symfony\Component\HttpFoundation\Request;

//define('MAINTENANCE_MODE', 1);
$autoloader = require_once implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'src', 'autoload.php']);

$kernel = new BloomissKernel('dev', $autoloader);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
