<?php

namespace Bloomiss\Core;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * L'interface de BloomissKernel, le cœur de Bloomiss.
 *
 * Cette interface étend KernelInterface de Symfony et ajoute des méthodes
 * pour répondre aux modules activés ou désactivés au cours de sa durée de vie.
 */
interface BloomissKernelInterface extends HttpKernelInterface, ContainerAwareInterface
{

}
