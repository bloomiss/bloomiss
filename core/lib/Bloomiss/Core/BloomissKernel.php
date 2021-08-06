<?php

namespace Bloomiss\Core;

use Bloomiss\Bloomiss;
use Bloomiss\Component\Render\FormattableMarkup;
use Bloomiss\Component\Utility\Xss;
use Bloomiss\Core\Render\Markup;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    protected static $isEnvInitialized = false;

    public function __construct()
    {
        //throw new Exception('Reveni à la fonction __construct()', 1);
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
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true)
    {
        //Assurez-vous que les variables d'environnement PHP sont saines.
        static::bootEnvironment();

        //code here
        /*var_dump([
            'request' => $request,
            'type' => $type,
            'catch' => $catch,
        ]);*/
        //$this->__toString();

        
        throw new Exception("Revenir ici");
        //var_dump($type, $request, $catch);

        return null;
    }

    public function __toString()
    {
        trigger_error('Test erreur to string', E_USER_ERROR);
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
        var_dump([
            'request' => $request,
            'response' => $response,
        ]);

        trigger_error("Revenir ici", E_USER_ERROR);
    }
}
