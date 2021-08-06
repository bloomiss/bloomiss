<?php

namespace Bloomiss\Component\Render;

use Bloomiss\Component\Utility\Html;
use Countable;

/**
 * Formate une chaîne pour l'affichage HTML en remplaçant les espaces réservés des variables.
 *
 * Lorsqu'il est converti en une chaîne, cet objet remplace les espaces réservés de variable
 * dans la chaîne par les arguments transmis lors de la construction et échappe les valeurs
 * afin qu'elles puissent être affichées en toute sécurité au format HTML.
 * Consultez la documentation de \Bloomiss\Component\Render\FormattableMarkup::placeholderFormat()
 * pour plus de détails sur les espaces réservés pris en charge et comment les utiliser en toute sécurité.
 * Une utilisation incorrecte de cette classe peut entraîner des failles de sécurité.
 *
 * Dans la plupart des cas, vous devez utiliser TranslatableMarkup ou PluralTranslatableMarkup plutôt que cet objet,
 * car ils traduiront le texte (sur les sites non anglophones uniquement) en plus de le formater.
 * Les variables concaténées sans l'insertion de mots ou de ponctuation spécifiques à la langue sont quelques
 * exemples où la traduction n'est pas applicable et l'utilisation directe de cette classe directement est appropriée.
 *
 * Cette classe est conçue pour formater des messages qui sont principalement du texte,
 * et non comme un langage de modèle HTML. En tant que tel:
 *  -   La chaîne transmise ne doit contenir aucun (ou un minimum) HTML.
 *  -   Les espaces réservés de variable ne doivent pas être utilisés dans les balise html "<" et ">",
 *      comme dans les valeurs d'attribut HTML. Ce serait un risque pour la sécurité. Exemples:
 *      @code
 *          //non sécurisé placeholder placé entre "<" et ">":
 *          $this->placeholderFormat('<@variable>text</@variable>', ['@variable' => $variable]);
 *          //non sécurisé placeholder placé entre "<" et ">":
 *          $this->placeholderFormat('<a @variable>link text</a>', ['@variable' => $variable]);
 *          //non sécurisé placeholder placé entre "<" et ">":
 *          this->placeholderFormat('<a title="@variable">link text</a>', ['@variable' => $variable]);
 *      @endcode
 *      Seul l'attribut "href" est pris en charge via l'espace réservé spécial
 *      ":variable", pour permettre l'insertion de liens simples :
 *      @code
 *          //Sécurié (utilisation du placeholder ":variable" pour l'attribut href):
 *          $this->placeholderFormat('<a href=":variable">link text</a>', [':variable' , $variable]);
 *          //Sécurié (utilisation du placeholder ":variable" pour l'attribut href):
 *          $this->placeholderFormat('<a href=":variable" title="static text">link text</a>',
 *            [':variable' => $variable]);
 *          // Non sécurisé (Le placeholder "@variable" ne filtre pas les protocoles dangereux
 *          $this->placeholderFormat('<a href="@variable">link text</a>', ['@variable' => $variable]);
 *          // Non sécurisé (Le placeholder "@variable" placé ntre "<" et ">"):
 *          $this->placeholderFormat('<a href=":url" title="@variable">link text</a>',
 *            [':url' => $url, '@variable' => $variable]);
 *      @endcode
 *  Pour créer du HTML non minimal, utilisez un langage de modèle HTML tel que Twig, plutôt que cette classe.
 */
class FormattableMarkup implements MarkupInterface, Countable
{

    /**
     * La chaîne contenant des placesHolder
     *
     * @var string
     */
    private $string;

    /**
     * Les arguments avec lesquels remplacer les espaces réservés.
     *
     * @var array
     */
    private $arguments = [];

    /**
     * Construit une nouvelle instance de la classe.
     *
     * @param string $string
     *      Une chaîne contenant des espaces réservés. La chaîne elle-même ne sera pas échappée,
     *      tout contenu dangereux doit être dans $args et inséré via des espaces réservés.
     *
     * @param array $arguments
     *      Un tableau avec des remplacements d'espaces réservés, codés par des espaces réservés.
     *      Voir \Bloomiss\Component\Render\FormattableMarkup::placeholderFormat()
     *      pour plus d'informations sur les espaces réservés.
     *
     * @see placeholderFormat()
     */
    public function __construct($string, array $arguments)
    {
        $this->string = (string) $string;
        $this->arguments = $arguments;
    }
    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return static::placeholderFormat($this->string, $this->arguments);
    }

    /**
     * Renvoie la longueur de la chaîne.
     *
     * @return int La longeur de la chaîne.
     */
    public function count():int
    {
        return mb_strlen($this->string);
    }

    /**
     * Renvoie une représentation de l'objet à utiliser dans la sérialisation JSON.
     *
     * @return string Le contenu de la chaîne sécurisée.
     */
    public function jsonSerialize():string
    {
        return $this->__toString();
    }
    /**
     * Remplace les placeholdes de la chaîne avec les valeur.
     *
     * @param string $string
     *      Une chaîne contenant des espaces réservés.
     *      La chaîne elle-même est censée être un code HTML sûr et correct.
     *      Tout contenu dangereux doit être dans $args et inséré via des espaces réservés.
     *
     * @param array $args
     *      Un tableau associatif de remplacements. Chaque clé de tableau doit être identique
     *      à un espace réservé dans $string. La valeur correspondante doit être une chaîne
     *      ou un objet qui implémente \Bloomiss\Component\Render\MarkupInterface.
     *      La valeur remplace l'espace réservé dans $string. La désinfection et le formatage
     *      seront effectués avant le remplacement. Le type de nettoyage et
     *      de formatage dépend du premier caractère de la clé :
     *       -  @variable: Lorsque la valeur de remplacement du placeholder est :
     *           --  Une chaîne, la valeur remplacée dans la chaîne renvoyée sera filtrée à l'aide de
     *              \Bloomiss\Component\Utility\Html::escape().
     *           --  Un objet MarkupInterface, la valeur remplacée dans la chaîne renvoyée ne sera pas filtrée.
     *           --  Un objet MarkupInterface converti en une chaîne, la valeur remplacée dans la chaîne renvoyée
     *              doit être filtrée de force à l'aide de \Bloomiss\Component\Utility\Html::escape().
     *          @code
     *              $this->placeholderFormat('This will force HTML-escaping of the replacement value: @text',
     *               ['@text' => (string) $safe_string_interface_object));
     *          @endcode
     *          Utilisez ce placeholder comme choix par défaut pour tout ce qui est affiché sur le site,
     *          mais pas dans les attributs HTML, JavaScript ou CSS. Cela représente un risque pour la sécurité.
     *       -  %variable: À utiliser lorsque la valeur de remplacement doit être encapsulée dans une balise <em>:
     *          Un appel comme :
     *          @code
     *              $string = "%output_text";
     *              $arguments = ['%output_text' => 'text output here.'];
     *              $this->placeholderFormat($string, $arguments);
     *          @endcode
     *          crée le code HTML suivant :
     *          @code
     *              <em class="placeholder">text output here.</em>
     *          @endcode
     *          Comme avec @variable, ne l'utilisez pas dans les attributs HTML, JavaScript ou CSS.
     *          Cela représente un risque pour la sécurité.
     *       -  :variable: La valeur de retour est échappée avec \Bloomiss\Component\Utility\Html::escape()
     *          et filtrée pour les protocoles dangereux en utilisant UrlHelper::stripDangerousProtocols().
     *          Utilisez ceci lorsque vous utilisez l'attribut "href", en vous assurant que la valeur
     *          de l'attribut est toujours entourée de guillemets :
     *          @code
     *              //secure (avec guillemet)
     *              $this->placeholderFormat('<a href=":url">@variable</a>',
     *               [':url' => $url, '@variable' => $variable]);
     *              //Non sécurisé (sans guillemet)
     *              $this->placeholderFormat('<a href=:url>@variable</a>',
     *               [':url' => $url, '@variable' => $variable]);
     *          @endcode
     *          Lorsque ":variable" provient d'une entrée utilisateur arbitraire,
     *          le résultat est sécurisé, mais il n'est pas garanti qu'il s'agisse d'une URL valide
     *          (ce qui signifie que la sortie résultante peut échouer à la validation HTML).
     *          Pour garantir une URL valide, utilisez Url::fromUri($user_input)->toString()
     *          (qui lève une exception ou renvoie une URL bien formée) avant de passer
     *          le résultat dans un espace réservé ":variable".
     *
     * @return string
     *      Une chaîne HTML formatée avec les espaces réservés remplacés.
     */
    private static function placeholderFormat(string $string, array $args):string
    {
        //Transformez les arguments avant de les insérer.
        foreach ($args as $key => $value) {
            switch ($key[0]) {
                case '@':
                    // Échappement si la valeur n'est pas un objet d'une classe qui implémente
                    // \Bloomiss\Component\Render\MarkupInterface, par exemple les chaînes seront échappées.
                    // Les chaînes qui sont sûres dans les fragments HTML, mais pas dans d'autres contextes,
                    // peuvent toujours être une instance de \Bloomiss\Component\Render\MarkupInterface,
                    // donc ce type d'espace réservé ne doit pas être utilisé dans les attributs HTML,
                    // JavaScript ou CSS.
                    $args[$key] = static::placeholderEscape($value);
                    break;

                case ':':
                    //Supprimez les protocoles d'URL qui peuvent être des vecteurs XSS.
                    trigger_error("Revenir ici", E_USER_ERROR);
                    $value = loadFactory()->get('UrlHelper')::stripDangerousProtocols($value);
                    // Échappement inconditionnel, sans vérifier si la valeur est une instance de
                    // \Bloomiss\Component\Render\MarkupInterface. Cela force les caractères qui ne sont pas sûrs
                    // à utiliser dans un attribut HTML "href" à être codés. Si un appelant souhaite transmettre
                    // une valeur extraite du HTML et donc déjà encodée en HTML, il doit invoquer
                    // \Bloomiss\Component\Render\OutputStrategyInterface::renderFromHtml()
                    // avant de le transmettre en tant que valeur d'espace réservé de ce type.
                    // @todo Ajoutez des conseils et des avertissements plus forts.
                    // https://www.drupal.org/node/2569041.
                    $args[$key] = loadFactory()->get('html')::escape($value);
                    break;

                case '%':
                    // De la même manière que @, échappez les valeurs non sûres.
                    // Ajoutez également un balisage markup
                    // afin de rendre comme un espace réservé. Ne pas utiliser dans les attributs,
                    // conformément à l'avertissement ci-dessus à propos de
                    // \Drupal\Component\Render\MarkupInterface et également en raison du balisage d'emballage.
                    $args[$key] = sprintf('<em class="placeholder">%s</em>', static::placeholderEscape($value));
                    break;
                default:
                    trigger_error(sprintf(
                        'Placeholder (%s) invalide, avec la chaîne : "%s"',
                        $key,
                        $string
                    ), E_USER_WARNING);
                    // Aucun remplacement possible donc nous pouvons écarter l'argument.
                    unset($args[$key]);
                    break;
            }
        }
        return strtr($string, $args);
    }

    /**
     * Échappe une valeur de remplacement d'espace réservé si nécessaire.
     *
     * @param string|MarkupInterface $value
     *      Une valeur de remplacement d'espace réservé.
     *
     * @return string
     *      La valeur de remplacement correctement échappée.
     */
    private static function placeholderEscape($value):string
    {
        return $value instanceof MarkupInterface ?
        (string) $value :
        loadFactory()->get('html')::escape($value);
    }
}
