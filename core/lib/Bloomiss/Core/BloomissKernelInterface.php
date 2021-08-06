<?php

namespace Bloomiss\Core;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * L'interface de BloomissKernel, le cœur de Bloomiss.
 *
 * Cette interface étend KernelInterface de Symfony et ajoute des méthodes
 * pour répondre aux modules activés ou désactivés au cours de sa durée de vie.
 */
interface BloomissKernelInterface extends HttpKernelInterface, ContainerAwareInterface
{
    /**
     * Événement déclenché lorsque le conteneur de service a terminé l'initialisation dans la sous-requête.
     *
     * Cet événement vous permet d'initialiser des dérogations telles que la langue aux services.
     * @var string
     */
    const CONTAINER_INITIALIZE_SUBREQUEST_FINISHED = 'kernel.container.finish_container_initialize_subrequest';

    /**
     * Démarre le noyau actuel
     *
     * @return BloomissKernelInterface
     */
    public function boot():BloomissKernelInterface;

    /**
     * Arrête le noyau
     *
     * @return void
     */
    public function shutdown():void;

    /**
     * Renvoie les fournisseur de services disponible.
     *
     * @return array les fournisseur de services disponible.
     */
    public function discoverServiceProviders(): array;

    /**
     * Renvoie tous les fournisseurs de services enregistrés.
     *
     * @param string $origin L'origine pour laquelle retourner les fournisseurs de services ;
     *                       l'un de « application » ou « site ».
     * @return array         Un tableau associatif d'objets ServiceProvider, codés par nom.
     */
    public function getSerivceProviders(string $origin):array;

    /**
     * Obtient le conteneur actuel.
     *
     * @return Symfony\Component\DependencyInjection\ContainerInterface
     *      Une instance de ContainerInterface.
     */
    public function getContainer(): ContainerInterface;

    /**
     * Renvoie la définition du conteneur mis en cache - si possible.
     *
     * Cela permet également d'inspecter un conteneur construit à des fins de débogage.
     *
     * @return array|null
     *      La définition du conteneur mis en cache ou NULL si elle n'est pas trouvée dans le cache.
     */
    public function getCachedContainerDefinition(): array|null;

    /**
     * Définie le chemin actuel du site
     *
     * @param string $path Le chemin  actuel du site
     *
     * @return void
     *
     * @throws LogixException Dans le cas ou le noyau est déjà chargé.
     */
    public function setSitePath(string $path) : void;

    /**
     * Renvoie le chemin actuel du site
     *
     * @return string Le chemin  actuel du site
     */
    public function getSitePath() : string;

    /**
     * Renvoiel'application principal.
     *
     * @return string
     */
    public function getAppRoot() : string;

    /**
     * Met à jour la liste des modules du noyau vers la nouvelle liste.
     *
     * Le noyau doit mettre à jour sa liste de bundles et son conteneur pour correspondre à la nouvelle liste.
     *
     * @param array $moduleList         La nouvelle liste de modules.
     * @param array $moduleFilename     Liste des noms de fichiers de modules,
     *                                  codés par nom de module.
     * @return void
     */
    public function updateModules(array $moduleList, array $moduleFilename = []) : void;

    /**
     * Force la reconstruction du conteneur
     *
     * @return Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function rebuildContainer() : ContainerInterface;

    /**
     * Invalidez le conteneur de services pour la prochaine requête.
     *
     * @return void
     */
    public function invalideContainer() : void;

    /**
     * Méthode d'assistance qui demande une initialisation associée.
     *
     * @param Request $requete La requete actuel.
     *
     * @return void
     */
    public function preHandle(Request $requete);

    /**
     * Méthode d'assistance qui charge les fichiers d'inclusion Drupal hérités.
     *
     * @return void
     */
    public function loadLegacyIncludes() : void;
}
