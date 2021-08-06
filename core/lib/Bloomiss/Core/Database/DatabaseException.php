<?php

namespace Bloomiss\Core\Database;

/**
 * Interface pour une exception de base de données.
 *
 * Toutes les exceptions de base de données doivent implémenter cette interface
 * afin qu'elles puissent être interceptées collectivement.
 * Notez que cela s'applique uniquement aux exceptions générées par Bloomiss.
 *
 * PDOException n'implémentera pas cette interface et les développeurs de modules doivent en tenir compte séparément.
 */
interface DatabaseException
{

}
