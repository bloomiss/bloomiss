<?php

namespace Bloomiss;

use Bloomiss\Core\Config\ImmutableConfig;
use Bloomiss\Core\DependencyInjection\ContainerNotInitializedException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Wrapper de conteneur de service statique.
 *
 * En règle générale, le code dans Bloomiss doit accepter ses dépendances
 * via une injection de constructeur ou une injection de méthode setter.
 * Cependant, il existe des cas, en particulier dans le code de procédure hérité, où cela est impossible.
 * Cette classe agit comme un accesseur global unifié à des services arbitraires
 * au sein du système afin de faciliter la transition du code procédural au code OO injecté.
 *
 * Le conteneur est construit par le noyau et transmis à cette classe qui le stocke statiquement.
 * Le conteneur contient toujours les services de \Bloomiss\Core\CoreServiceProvider,
 * les fournisseurs de services des modules activés et tout autre fournisseur de services défini
 * dans $GLOBALS['conf']['container_service_providers'].
 *
 * Cette classe existe uniquement pour prendre en charge le code hérité qui ne peut pas être injecté de dépendance.
 * Si votre code en a besoin, envisagez de le refactoriser pour qu'il soit orienté objet, si possible.
 * Lorsque cela n'est pas possible, par exemple dans le cas d'implémentations de hooks,
 * et que votre code contient plus de quelques lignes non réutilisables
 *
 * @code
 *   // Code procédural hérité.
 *   function hook_do_stuff() {
 *     $lock = lock()->acquire('stuff_lock');
 *     // ...
 *   }
 *
 *   // Code procédural correct
 *   function hook_do_stuff() {
 *     $lock = \Drupal::lock()->acquire('stuff_lock');
 *     // ...
 *   }
 *
 *   //La méthode préférée : le code injecté par les dépendances.
 *   function hook_do_stuff() {
 *     // Déplacez l'implémentation réelle vers une classe et instanciez-la.
 *     $instance = new StuffDoingClass(\Drupal::lock());
 *     $instance->doStuff();
 *
 *     // Ou, mieux encore, comptez sur le conteneur de service pour éviter de coder en dur
 *     // une implémentation d'interface spécifique, afin que la logique réelle puisse être échangée.
 *     // Cela peut ne pas toujours avoir de sens, mais en général, c'est une bonne pratique.
 *     \Drupal::service('stuff.doing')->doStuff();
 *   }
 *
 *   interface StuffDoingInterface {
 *     public function doStuff();
 *   }
 *
 *   class StuffDoingClass implements StuffDoingInterface {
 *     protected $lockBackend;
 *
 *     public function __construct(LockBackendInterface $lock_backend) {
 *       $this->lockBackend = $lock_backend;
 *     }
 *
 *     public function doStuff() {
 *       $lock = $this->lockBackend->acquire('stuff_lock');
 *       // ...
 *     }
 *   }
 * @endcode
 *
 * @see \Bloomiss\Core\BloomissKernel
 */
class Bloomiss
{
    /**
     * L'objet conteneur actuellement actif, ou NULL s'il n'est pas encore initialisé.
     *
     * @var ContainerInterface|null
     */
    private static $container = null;

    /**
     * Définit un container global
     *
     * @param ContainerInterface $container
     *  Une nouvelle instance de conteneur pour remplacer l'instance actuelle.
     * @return void
     */
    public static function setContainer(ContainerInterface $container):void
    {
        static::$container = $container;
    }
    /**
     * Désactive le conteneur global.
     *
     * @return void
     */
    public static function unsetContainer():void
    {
        static::$container = null;
    }
    /**
     * Renvoie le conteneur global actuellement actif.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     *
     * @throws Bloomiss\Core\DependencyInjection\ContainerNotInitializedException
     */
    public static function getContainer():ContainerInterface
    {
        if (static::$container === null) {
            throw new ContainerNotInitializedException(
                '\Bloomiss::$container n\'est pas initialiser. '.
                '\Bloomiss::setContainer() doit être appelé avec le container réelle'
            );
        }
        return static::$container;
    }
    /**
     * Renvoie TRUE si le conteneur a été initialisé, FALSE sinon.
     *
     * @return boolean
     */
    public static function hasContainer():bool
    {
        return static::$container !== null;
    }

    /**
     * Récupère un service du conteneur.
     *
     * Utilisez cette méthode si le service souhaité n'est pas l'un de ceux avec une méthode d'accès dédiée ci-dessous.
     * Si elle est répertoriée ci-dessous,
     * ces méthodes sont préférées car elles peuvent renvoyer des conseils de type utiles.
     *
     * @param string $idService L'ID du service à récupérer.
     * @return mixed Le service spécifié.
     */
    public static function service(string $idService):mixed
    {
        return static::getContainer()->get($idService);
    }
    /**
     * Indique si un service est défini dans le conteneur.
     *
     * @param string $idService L'ID du service à vérifier.
     * @return boolean TRUE si le service existe, FALSE sinon.
     */
    public static function hasService(string $idService):bool
    {
        //Vérifiez d'abord hasContainer() afin de toujours renvoyer un booléen.
        return static::hasContainer() && static::getContainer()->has($idService);
    }

    /**
     * Indique s'il existe un objet de Request actuellement actif.
     *
     * @return boolean TRUE s'il y a un objet de requête actuellement actif, FALSE sinon.
     */
    public static function hasRaquest()
    {
        //Vérifiez d'abord hasContainer() afin de toujours renvoyer un booléen.
        return static::hasContainer() &&
        static::getContainer()->has('request_stack') &&
        static::getContainer()->get('request_stack')->getCurrentRequest() !== null;
    }

    /**
     * Récupère l'objet de requête actuellement utilisé.
     *
     * Remarque : L'utilisation de ce wrapper en particulier est particulièrement déconseillée.
     * La plupart des codes ne devraient pas avoir besoin d'accéder directement à la demande.
     * Cela signifie qu'il ne fonctionnera que lors du traitement d'une requête HTTP et
     * nécessitera une modification ou un encapsulage spécial lorsqu'il sera exécuté à partir
     * d'un outil de ligne de commande, de certains processeurs de file d'attente ou de tests automatisés.
     *
     * Si le code doit accéder à la requête, il est considérablement préférable d'enregistrer
     * un objet auprès du conteneur de services et de lui attribuer une méthode setRequest()
     * configurée pour s'exécuter lors de la création du service.
     * De cette façon, l'objet de requête correct peut toujours être fourni par le conteneur
     * et le service peut toujours être testé unitairement.
     *
     * Si cette méthode doit être utilisée, n'enregistrez jamais l'objet de requête renvoyé.
     * Cela peut conduire à des incohérences car l'objet de la requête est volatile
     * et peut changer à différents moments, comme lors d'une sous-requête.
     *
     * @return Request L'objet Request actuellement active.
     */
    public static function request():Request
    {
        return static::getContainer()->get('request_stack')->getCurrentRequest();
    }

    /**
     * Récupère un objet de configuration.
     * Il s'agit du principal point d'entrée de l'API de configuration.
     * Appel:
     *  @code
     *      \Drupal::config('book.admin')
     *  @endcode
     * Renverra un objet de configuration que le module Book
     * peut utiliser pour lire ses paramètres administratifs.
     *
     * @param string $name
     *  Le nom de l'objet de configuration à récupérer, qui correspond généralement à un fichier de configuration.
     * Pour
     *  @code
     *      \Drupal::config('book.admin')
     * @endcode,
     * l'objet de configuration renvoyé contiendra le contenu du fichier de configuration book.admin.
     *
     * @return ImmutableConfig un objet de configuration immutable
     */
    public static function config(string $name):ImmutableConfig
    {
        return static::getContainer()->get('config.factory')->get($name);
    }
}
