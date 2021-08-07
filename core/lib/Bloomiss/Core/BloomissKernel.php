<?php

namespace Bloomiss\Core;

use Bloomiss\Core\Installer\InstallerRedirectTrait;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use UnexpectedValueException;

/**
 * La classe BloomissKernel est le cœur de Bloomiss lui-même.
 *
 * Cette classe est responsable de la construction du
 * conteneur d'injection de dépendances et s'occupe également de
 * l'enregistrement des fournisseurs de services.
 *
 * Il permet aux fournisseurs de services enregistrés d'ajouter leurs services au conteneur.
 * Core fournit le CoreServiceProvider, qui, en plus d'enregistrer tous les services de base
 * qui ne peuvent pas être enregistrés dans le fichier core.services.yaml,
 * ajoute toutes les passes de compilateur nécessaires par core, par ex.
 * pour le traitement des services étiquetés.
 *
 * Chaque module peut ajouter son propre fournisseur de services,
 * c'est-à-dire une classe implémentant Bloomiss\Core\DependencyInjection\ServiceProvider,
 * pour enregistrer des services dans le conteneur, ou modifier des services existants.
 */
class BloomissKernel implements BloomissKernelInterface, TerminableInterface
{
    use InstallerRedirectTrait;

    /**
     * Si les services essentiels ont été configurés correctement par preHandle().
     *
     * @var boolean
     */
    private $prepared = false;

    /**
     * L'environnement, ex: 'testing', 'install'
     *
     * @var string
     */
    private $environment;
    /**
     * L'objet Chargeur de classe
     *
     * @var \Composer\Autoload\ClassLoader
     */
    private $classLoader;

    /**
     * Si le conteneur peut être vidé.
     *
     * @var bool
     */
    private $allowDumping;

    /**
     * La racine de l'application
     *
     * @var string
     */
    private $root;
    /**
     * Si l'environnement PHP a été initialisé.
     *
     * Cette phase héritée ne peut être démarrée qu'une seule fois
     * car elle définit les paramètres INI de la session.
     *
     * Si une session a déjà été démarrée,
     * la régénération de ces paramètres interromprait la session.
     *
     * @var boolean
     */
    private static $isEnvInitialized = false;

    /**
     * Construit un objet de la classe BloomissKernel.
     *
     * @param string $environment
     *      Chaîne indiquant l'environnement, par ex. 'prod' ou 'dev'.
     *
     * @param  $classLoader
     *      Le chargeur de classe. Normalement \Composer\Autoload\ClassLoader,
     *      tel qu'inclus par le contrôleur frontal, mais peut également être décoré.
     * @param string $appRoot
     *      (optionel) Chemin d'accès à la racine de l'application sous forme de chaîne.
     *      Si elle n'est pas fournie, la racine de l'application sera calculée.
     */
    public function __construct($environment, $classLoader, $appRoot = null)
    {
        $this->environment = $environment;
        $this->classLoader = $classLoader;
        $this->allowDumping = true;
        if ($appRoot === null) {
            $appRoot = static::guessApplicationRoot();
        }
        $this->root = $appRoot;
    }
    // -------------------------------------------------------------------------------------------------------------- //
    //                                                                                                                //
    //                                          Fontion publique statique                                             //
    //                                                                                                                //
    // -------------------------------------------------------------------------------------------------------------- //

    /**
     * Configure un environnement PHP cohérent.
     *
     * Sa méthode définit les options d'environnement PHP dont nous voulons nous assurer qu'elles sont correctement
     * définies pour des raisons de sécurité ou simplement de bon sens.
     *
     * @param string|null $appRoot Le chemin d'accès à la racine de l'application sous forme de chaîne.
     *                             Si elle n'est pas fournie, la racine de l'application sera calculée.
     * @return void
     */
    public static function bootEnvironment(?string $appRoot = null): void
    {
        if (static::$isEnvInitialized) {
            return;
        }

        //Détermine si le répertoire de l'application principal si celuis-ci n'est pas fourni.
        if ($appRoot == null) {
            $appRoot = static::guessApplicationRoot();
        }

        // Appliquez E_STRICT, mais autorisez les utilisateurs à définir des niveaux ne faisant pas partie de E_STRICT.
        error_reporting(E_STRICT | E_ALL);

        /*
            Remplacez les paramètres PHP requis pour que Drupal fonctionne correctement.
            sites/default/default.settings.php contient plus de paramètres d'exécution.
            Le fichier .htaccess contient des paramètres qui ne peuvent pas être modifiés lors de l'exécution.
        */
        if (PHP_SAPI !== 'cli') {
            /*
                Utilisez des cookies de session, et non des sessions transparentes
                qui placent l'ID de session dans la chaîne de requête.
            */
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            // N'envoyez pas d'en-têtes HTTP à l'aide du gestionnaire de session PHP.
            // Envoyez une chaîne vide pour désactiver le limiteur de cache.
            ini_set('session.cache_limiter', '');
            // Utilisez uniquement des cookies de session.
            ini_set('session.cookie_httponly', '1');
        }
        /*
            Définissez des paramètres régionaux sains,
            pour assurer une gestion cohérente des chaînes, des dates, des heures et des nombres.
        */
        setlocale(LC_ALL, 'C');

        // Définissez la configuration appropriée pour les chaînes multi-octets.
        mb_internal_encoding('utf-8');
        mb_language('uni');

        //Indiquez que le code fonctionne dans un site enfant de test.
        //Assurez-vous qu'aucun autre code ne le définit.
        //Vu que je n'utilise pas SimpleTest, je ne test pas l'user agent.
        define('BLOOMISS_TEST_IN_CHILD_SITE', false);

        //Charger Bootstrap
        bloomissLoad();
        // Définire les gestionnaire d'erreur et d'exception sur ceux de Bloomiss
        set_error_handler("bloomissErrorHandler");
        set_exception_handler("bloomissExceptionHandler");
        static::$isEnvInitialized = true;
    }

    /**
     * Déterminez le répertoire principal de l'application en fonction de l'emplacement de ce fichier.
     *
     * @return string Le répertoire principal de l'application
     */
    public static function guessApplicationRoot():string
    {
        // Détérmine le répertoire principal de l'application en:
        // - En supprimant les répertoire de namespace du chemins.
        // - Obtenir le chemin d'accès au répertoire deux niveaux au-dessus du chemin déterminé à l'étape précédente.
        return dirname(substr(__DIR__, 0, -strlen(__NAMESPACE__)), 2);
    }
    // -------------------------------------------------------------------------------------------------------------- //
    //                                                                                                                //
    //          Fontion implémenté par l'interface - Bloomiss\Core\BloomissKernelInterface                            //
    //                                                                                                                //
    // -------------------------------------------------------------------------------------------------------------- //

    /**
     * {@inheritDoc}
     */
    public function boot():BloomissKernel
    {
        trigger_error("Revenir ici");
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function shutdown():void
    {
        throw new Exception("Reveni à la fonction shutdown()", 1);
    }
        
    /**
     * {@inheritDoc}
     */
    public function discoverServiceProviders(): array
    {
        throw new Exception("Reveni à la fonction discoverServiceProviders(): array", 1);
    }
    
    /**
     * {@inheritDoc}
     */
    public function getSerivceProviders(string $origin):array
    {
        var_dump(['origin'=> $origin]);
        throw new Exception("Reveni à la fonction getSerivceProvidersarray", 1);
    }
    
    /**
     * {@inheritDoc}
     */
    public function getContainer(): ContainerInterface
    {
        throw new Exception("Reveni à la fonction getContainer(): ContainerInterface", 1);
    }

    /**
     * {@inheritDoc}
     */
    public function getCachedContainerDefinition(): array|null
    {
        throw new Exception("Reveni à la fonction getCachedContainerDefinition(): array|null", 1);
    }

    /**
     * {@inheritDoc}
     */
    public function setSitePath(string $path) : void
    {
        var_dump(["path" => $path]);
        throw new Exception("Reveni à la fonction setSitePath(string ) : void", 1);
    }

    /**
     * {@inheritDoc}
     */
    public function getSitePath() : string
    {
        throw new Exception('Reveni à la fonction getSitePath() : string', 1);
    }

    /**
     * {@inheritDoc}
     */
    public function getAppRoot() : string
    {
        throw new Exception('Reveni à la fonction getAppRoot() : string', 1);
    }

    /**
     * {@inheritDoc}
     */
    public function updateModules(array $moduleList, array $moduleFilename = []) : void
    {
        var_dump([
            'moduleList' => $moduleList,
            'moduleFilename' => $moduleFilename,
        ]);
        throw new Exception('Reveni à la fonction updateModules(array $moduleList, array $moduleFilename = [])', 1);
    }

    /**
     * {@inheritDoc}
     */
    public function rebuildContainer() : ContainerInterface
    {
        throw new Exception('Reveni à la fonction rebuildContainer() : ContainerInterface', 1);
    }

    /**
     * {@inheritDoc}
     */
    public function invalideContainer() : void
    {
        throw new Exception('Reveni à la fonction invalideContainer() : void', 1);
    }

    /**
     * {@inheritDoc}
     */
    public function preHandle(Request $requete)
    {
        var_dump(['requete' => $requete]);
        throw new Exception('Reveni à la fonction preHandle(Request $requete)', 1);
    }

    /**
     * {@inheritDoc}
     */
    public function loadLegacyIncludes() : void
    {
        throw new Exception('Reveni à la fonction loadLegacyIncludes() : void', 1);
    }
    // -------------------------------------------------------------------------------------------------------------- //
    //                                                                                                                //
    //          Fontion implémenté par l'interface - Symfony\Component\HttpKernel\HttpKernelInterface                 //
    //                                                                                                                //
    // -------------------------------------------------------------------------------------------------------------- //
    
    /**
     * Gère une requête pour la convertir en réponse.
     * Lorsque $catch est vrai, l'implémentation doit intercepter toutes les exceptions
     * et faire de son mieux pour les convertir en une instance Response.
     *
     *
     * @param Request $request  La requête reçue par le serveur.
     * @param integer $type     Le type de la requete
     *                          (Soit HttpKernelInterface::MAIN_REQUEST ou HttpKernelInterface::SUB_REQUEST)
     * @param bool $catch       Que ce soit pour intercepter les exceptions ou non
     *
     * @return Response Une instance de Response
     *
     * @throws \Exception Lorsqu'une exception se produit pendant le traitement
     * @SuppressWarnings(BooleanArgumentFlag)
     */
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = false)
    {
        //Assurez-vous que les variables d'environnement PHP sont saines.
        static::bootEnvironment();

        try {
            $this->initializeSettings($request);

            $response = new Response();
        } catch (Exception $e) {
            if ($catch) {
                throw $e;
            }

            $response = $this->handleException($e, $request, $type);
        }

        //Adaptez les en-têtes de réponse à la requête en cours.
        $response->prepare($request);

        return $response;
    }

    // -------------------------------------------------------------------------------------------------------------- //
    //                                                                                                                //
    //     Fontion implémenté par l'interface - Symfony\Component\DependencyInjection\ContainerAwareInterface         //
    //                                                                                                                //
    // -------------------------------------------------------------------------------------------------------------- //

    /**
     * Sets the container.
     */
    public function setContainer(ContainerInterface $container = null)
    {
        var_dump(['container' => $container]);
    }

    // -------------------------------------------------------------------------------------------------------------- //
    //                                                                                                                //
    //          Fontion implémenté par l'interface - Symfony\Component\HttpKernel\TerminableInterface                 //
    //                                                                                                                //
    // -------------------------------------------------------------------------------------------------------------- //

    /**
     * Termine un cycle demande/réponse.
     *
     * Doit être appelé après l'envoi de la réponse et avant d'arrêter le noyau.
     */
    public function terminate(Request $request, Response $response)
    {
        /*var_dump([
            'request' => $request,
            'response' => $response,
        ]);*/

        trigger_error("Revenir ici");
    }


    // -------------------------------------------------------------------------------------------------------------- //
    //                                                                                                                //
    //                                              Méthode protected                                                 //
    //                                                                                                                //
    // -------------------------------------------------------------------------------------------------------------- //

    // -------------------------------------------------------------------------------------------------------------- //
    //                                                                                                                //
    //                                               Méthode private                                                  //
    //                                                                                                                //
    // -------------------------------------------------------------------------------------------------------------- //

    // -------------------------------------------------------------------------------------------------------------- //
    //                                                                                                                //
    //                                          Méthode static protected                                              //
    //                                                                                                                //
    // -------------------------------------------------------------------------------------------------------------- //

    // -------------------------------------------------------------------------------------------------------------- //
    //                                                                                                                //
    //                                          Méthode static private                                                //
    //                                                                                                                //
    // -------------------------------------------------------------------------------------------------------------- //
    
    /**
     * Renvoie le répertoire du site approprié pour une requête.
     *
     * Une fois le noyau créé, BloomissKernelInterface::getSitePath() est préférable car il obtient le résultat
     * mis en cache statiquement de cette méthode. Les répertoires de site contiennent tout le code spécifique au site.
     * Cela inclut settings.php pour la configuration de niveau d'amorçage, les magasins de configuration de fichiers,
     * le stockage de fichiers publics et les modules et thèmes spécifiques au site.
     *
     * Un fichier nommé sites.php doit être présent dans le répertoire sites pour le multisite.
     * S'il n'existe pas, alors 'sites/default' sera utilisé.
     *
     * Trouve un fichier de répertoire de site correspondant en supprimant le nom d'hôte du site Web de gauche à droite
     * et le nom de chemin de droite à gauche. Par défaut, le répertoire doit contenir un fichier 'settings.php' pour
     * qu'il corresponde. Si le paramètre $require_settings est défini sur FALSE, alors un répertoire sans fichier
     * 'settings.php' correspondra également. Le premier fichier de configuration trouvé sera utilisé et les autres
     * seront ignorés. Si aucun fichier de configuration n'est trouvé, renvoie une valeur par défaut 'sites/default'.
     * Voir default.settings.php pour des exemples sur la façon dont l'URL est convertie en répertoire.
     *
     * Le fichier sites.php dans le répertoire sites peut définir des alias dans un tableau associatif nommé $sites.
     * Le tableau est écrit au format '<port>.<domain>.<path>' => 'directory'.
     * Par exemple, pour créer un alias de répertoire pour https://www.drupal.org:8080/mysite/test
     * dont le fichier de configuration se trouve dans sites/example.com, le tableau doit être défini comme :
     * @code
     * $sites = array(
     *   '8080.www.drupal.org.mysite.test' => 'example.com',
     *  );
     * @endcode
     *
     * @param Request $request
     *      La requête actuell
     *
     * @param string|null $appRoot
     *      (optionnel) Chemin d'accès à la racine de l'application sous forme de chaîne.
     *      Si elle n'est pas fournie, la racine de l'application sera calculée.
     *
     * @param bool $requireSetting
     *      Seuls les répertoires avec un fichier settings.php existant seront reconnus.
     *      La valeur par défaut est TRUE. Lors de l'installation initiale,
     *      ce paramètre est défini sur FALSE afin que Bloomiss puisse détecter un répertoire
     *      correspondant, puis y créer un nouveau fichier settings.php.
     *
     * @return string
     *      Le chemin du répertoire correspondant.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *      Dans le cas où le nom de l'hôte dans la requête est invalide.
     */
    private static function findSitePath(Request $request, ?string $appRoot = null):string
    {
        if (static::validateHostnname($request) === false) {
            throw new BadRequestException();
        }
        throw new BadRequestException();
        //Initialiser la variable 'bool' requireSetting
        $requireSetting = (func_num_args() == 3) ? func_get_arg(2) : true;

        //var_dump();
        var_dump(static::validateHostnname($request));
        trigger_error('Revenir à la fonction nom_fonction()');
        return '';
    }

    /**
     * Valide la longeur de l'hote.
     *
     * @param string $host
     *      Le nom d'hote
     *
     * @return bool
     *      TRUE si la longeur est approprié, FALSE sinon.
     */
    private static function validateHostnameLength(string $host):bool
    {
        //Limitez la longueur du nom d'hôte à 1000 octets pour empêcher les attaques DoS avec des noms d'hôte longs
        return strlen($host) <= 1000
        //Limitez le nombre de sous-domaines et de séparateurs de ports
        //pour empêcher les attaques DoS dans findSitePath().
        && substr_count($host, '.') <= 100
        && substr_count($host, ':') <= 100;
    }
    /**
     * Valide le nom d'hôte fourni à partir de la requête HTTP.
     *
     * @param Request $request
     *      L'objet de la classe Request
     * @return bool
     *      TRUE si le nom de l'hote est valide, FALSE sinon.
     */
    private static function validateHostnname(Request $request):bool
    {
        // $request->getHost() peut lever une UnexpectedValueException
        // s'il détecte un nom d'hôte incorrect, mais il ne valide pas la longueur.
        try {
            $httpHost = $request->getHost();
        } catch (UnexpectedValueException $e) {
            return false;
        }

        if (static::validateHostnameLength($httpHost) === false) {
            return false;
        }

        return true;
    }

    /**
     * Convertit une exception en réponse.
     *
     * @param Exception $exception
     *      Une exception
     *
     * @param Request   $request
     *      Un objet de la classe Request
     *
     * @param integer   $type
     *      Le type de la requête (un de HttpKernelInterface::MAIN_REQUEST ou HttpKernelInterface::SUB_REQUEST)
     *
     * @return Response
     *      Un objet de la classe Response
     * @throws Exception
     *      Si l'exception transmise ne peut pas être transformée en réponse.
     */
    private function handleException(Exception $exception, Request $request, int $type):Response
    {
        $response = new Response();
        /*var_dump([
            'e' => $exception,
            'request' => $request,
            'type' => $type,
        ]);
        trigger_error('Revenir ici', E_USER_ERROR);*/

        if ($exception instanceof HttpExceptionInterface) {
            trigger_error('Revenir ici');
        }

        throw $exception;
    }

    /**
     * Localisez le chemin du site et initialisez le singleton des paramètres.
     *
     * @param Request $request
     *      La requete actuel
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *      //Au cas où le nom d'hôte dans la demande n'est pas approuvé.
     */
    private function initializeSettings(Request $request):void
    {
        static::findSitePath($request);
        //var_dump($request);
        trigger_error('Revenir ici');
    }
}
