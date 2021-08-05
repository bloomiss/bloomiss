<?php

namespace Bloomiss\Core\DependencyInjection;

use RuntimeException;

/**
 * Exception levée lorsqu'une méthode est appelée qui nécessite un conteneur,
 * mais que le conteneur n'est pas encore initialisé.
 */
class ContainerNotInitializedException extends RuntimeException
{

}
