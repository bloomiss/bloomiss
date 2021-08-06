<?php

namespace Bloomiss\Component\Utility;

/**
 * Méthodes basées sur l'URL de la classe d'assistance.
 */
class UrlHelper
{
    /**
     * Liste des protocol autorisés
     *
     * @var array
     */
    private static $proAllowed = ['https', 'http'];

    /**
     * Traite une valeur d'attribut HTML et supprime les protocoles dangereux des URL.
     *
     * @param string $string La chaîne avec la valeur de l'attribut.
     * @return string Version nettoyée et avec échappement HTML de $string.
     */
    public static function filterBadProtocol(string $string):string
    {
        //Obtenez la représentation en texte brut de la valeur de l'attribut (c'est-à-dire sa signification).
        $html = loadFactory()->Html;
        $string = $html::decodeEntities($string);
        return $html::escape(static::stripDangerousProtocols($string));
    }

    /**
     * Supprime les protocoles dangereux (par exemple, 'javascript:') d'une URI.
     *
     * Cette fonction doit être appelée pour toutes les URI dans les entrées saisies par l'utilisateur
     * avant d'être affichées dans une valeur d'attribut HTML. Il est souvent appelé dans le cadre de
     * \Bloomiss\Component\Utility\UrlHelper::filterBadProtocol() ou
     * \Bloomiss\Component\Utility\Xss::filter(), mais ces fonctions renvoient une chaîne encodée HTML,
     * donc cette fonction peut être appelée indépendamment lorsque la sortie doit être une chaîne de texte brut
     * pour passer aux fonctions qui appelleront Html ::escape() séparément. Le comportement exact dépend de la valeur :
     *  - Si la valeur est une URL relative bien formée (selon RFC 3986)
     *    ou une URL absolue qui n'utilise pas de protocole dangereux (comme "javascript:"),
     *    alors l'URL reste inchangée. Cela inclut toutes les URL générées via Url::toString().
     *  - Si la valeur est une URL absolue bien formée avec un protocole dangereux,
     *    le protocole est supprimé. Ce processus est répété sur l'URL restante
     *    jusqu'à ce qu'elle soit réduite à un protocole sûr.
     *  - Si la valeur n'est pas une URL bien formée, le même comportement de nettoyage
     *    que pour les URL bien formées sera invoqué, ce qui supprime la plupart
     *    des sous-chaînes qui précèdent un ":". Le résultat peut être utilisé dans
     *    des attributs d'URL tels que "href" ou "src" (uniquement après avoir appelé Html::escape() séparément),
     *    mais cela peut ne pas produire de code HTML valide (par exemple, les URL mal formées dans les attributs
     *    "href" échouent HTML validation). Cela peut être évité en utilisant
     *    Url::fromUri($possably_not_a_url)->toString(), qui lève une exception ou renvoie une URL bien formée.
     *
     * @param string $uri Une URI en texte brut pouvant contenir des protocoles dangereux.
     * @return string
     *      Une URI en texte brut dépourvu de protocoles dangereux. Comme pour toutes les chaînes de texte brut,
     *      cette valeur de retour ne doit pas être affichée sur une page HTML sans avoir été préalablement nettoyée.
     *      Cependant, il peut être transmis à des fonctions qui attendent des chaînes de texte brut.
     *
     * @see \Bloomiss\Component\Utility\Html::escape
     * @see \Bloomiss\Core\Url::toString()
     * @see \Bloomiss\Core\Url::fromUri()
     */
    public static function stripDangerousProtocols(string $uri):string
    {
        
        //Supprimez itérativement tout protocole invalide trouvé.
        do {
            $before = $uri;
            $colonPos = strpos($uri, ':');
            if ($colonPos > 0) {
                //On a trouvé un deux-points ':', peut-être un protocole. Vérifier.
                static::verifyProtocol($uri);
            }
        } while ($before != $uri);

        return $uri;
    }

    private static function verifyProtocol(string &$uri)
    {
        $proAllowed = array_flip(static::$proAllowed);
        $colonPos = strpos($uri, ':');
        $protocol = substr($uri, 0, $colonPos);
        // Si un deux-points est précédé d'une barre oblique,
        // d'un point d'interrogation ou d'un dièse,
        // il ne peut en aucun cas faire partie du schéma d'URL.
        // Il doit s'agir d'une URL relative, qui hérite du protocole (sûr) du document de base.
        if (preg_match('![/?#]!', $protocol)) {
            return;
        }

        //Vérifiez s'il s'agit d'un protocole non autorisé. Selon RFC2616, section 3.2.3
        //La comparaison de schémas (comparaison d'URI) doit être insensible à la casse.
        if (!isset($proAllowed[strtolower($protocol)])) {
            $uri = substr($uri, $colonPos + 1);
        }
    }
}
