<?php

namespace Bloomiss\Core\Database;

use RuntimeException;

/**
 * Cette classe wrapper sert uniquement à fournir des informations de débogage supplémentaires.
 *
 * Cette classe encapsulera toujours une PDOException.
 */
class DatabaseExceptionWrapper extends RuntimeException implements DatabaseException
{

}
