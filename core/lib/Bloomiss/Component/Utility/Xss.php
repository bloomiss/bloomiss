<?php

namespace Bloomiss\Component\Utility;

use stdClass;

/**
 * Fournit une aide pour filtrer les scripts intersites.
 *
 * @ingroup utility
 */
class Xss
{
    use LoaderTrait;
    /**
     * Liste de balises html autorisée par filterAdmin()
     *
     * @var array
     *
     * @see Bloomiss\Component\Xss->filterAdmin()
     */
    private static $adminTags = [
        'a', 'abbr', 'acronym', 'address', 'article', 'aside', 'b', 'bdi', 'bdo', 'big', 'blockquote',
        'br', 'caption', 'cite', 'code', 'col', 'colgroup', 'command', 'dd', 'del', 'details', 'dfn',
        'div', 'dl', 'dt', 'em','figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'header', 'hgroup', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'mark', 'menu', 'meter', 'nav', 'ol',
        'output', 'p', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'section', 'small',
        'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead',
        'time', 'tr', 'tt', 'u', 'ul', 'var', 'wbr', '!--'
    ];

    private static $htmlTags = [
        'a', 'em', 'strong', 'cite', 'blockquote',
        'code', 'ul', 'ol', 'li', 'dl', 'dt', 'dd'
    ];

    /**
     * Filtre HTML pour empêcher les attaque du type cross-site-scripting (Xss)
     *
     * D'après les kses d'Ulf Harnhammar, voir http://sourceforge.net/projects/kses.
     * Pour des exmple d'attaque Xss, voir: http://ha.ckers.org/xss.html.
     *
     * Ce code fait quatre choses :
     * - Supprime les caractères et les constructions qui peuvent tromper les navigateurs.
     * - S'assure que toutes les entités HTML sont bien formées.
     * - S'assure que toutes les balises et attributs HTML sont bien formés.
     * - S'assure qu'aucune balise HTML ne contient d'URL avec un protocole non autorisé (par exemple, javascript :).
     *
     * @param string        $string     La chaîne contenant du HTML brut.
     *                                  Il sera dépouillé de tout ce qui peut provoquer une attaque XSS.
     * @param array|null    $htmlTags   Un tableau de balises Html
     * @return string Une version sécurisée XSS de $string, ou une chaîne vide si $string n'est pas valide en UTF-8.
     */
    private static function filter(string $string, ?array $htmlTags = null):string
    {
       
        if (is_null($htmlTags)) {
            $htmlTags = static::$htmlTags;
        }

        // Fonctionne uniquement sur des chaînes UTF-8 valides.
        // Cela est nécessaire pour éviter les problèmes de scripts intersites sur Internet Explorer 6.
        if (!static::load()->Unicode::validateUtf8($string)) {
            return '';
        }

        // Supprimer les caractères NULL (ignorés par certains navigateurs).
        $string = str_replace(chr(0), '', $string);

        // Supprimez les entités Netscape 4 JS. ex: & {dd};
        $string = preg_replace('%&\s*\{[^}]*(\}\s*;?|$)%', '', $string);

        //Désactiver toutes les entités HTML.
        $string = str_replace('&', '&amp;', $string);

        // Remplacez uniquement les entités bien formées dans notre liste de balises html autorisées :
        //  - Entités numériques décimales. ex &#201; => É
        $string = preg_replace('/&amp;#([0-9]+;)/', '&#\1', $string);

        //  - Entités numériques Hexadécimal.
        $string = preg_replace('/&amp;#[Xx]0*((?:[0-9A-Fa-f]{2})+;)/', '&#x\1', $string);

        // - Entité nommé
        $string = preg_replace('/&amp;([A-Za-z][A-Za-z0-9]*;)/', '&\1', $string);

        //@TODO array_flip Remplace les valeur par les clés, et les clé par les valeurs.
        $htmlTags = array_flip($htmlTags);
        $splitter = function ($correspondances) use ($htmlTags) {
            return static::split($correspondances[1], $htmlTags);
        };

        //Supprimez toutes les balises qui ne figurent pas dans la liste des balises HTML autorisées.
        $pattern = '%
        (
        <(?=[^a-zA-Z!/])  # un seul <
        |                 # ou
        <!--.*?-->        # un commentaire
        |                 # ou
        <[^>]*(>|$)       # une chaîne qui commence par un <, jusqu\'a > ou à la fin de la chaîne
        |                 # ou
        >                 # juste >
        )%x';

        return preg_replace_callback($pattern, $splitter, $string);
    }

    /**
     * Applique un filtre XSS/HTML très permissif pour une utilisation réservée aux administrateurs.
     * À utiliser uniquement pour les champs où il n'est pas pratique d'utiliser l'ensemble du système de filtrage,
     * mais où un balisage (principalement en ligne) est souhaité
     * (donc \Bloomiss\Component\Utility\Html::escape() n'est pas acceptable).
     *
     * Autorise toutes les balises pouvant être utilisées dans un corps HTML,
     * sauf pour les scripts et les styles.
     *
     * @param string $string La chaîne à laquelle appliquer le filtre.
     * @return string La chaîne filtré.
     *
     */
    public static function filterAdmin(string $string):string
    {
        return static::filter($string, static::$adminTags);
    }

    /**
     * Récupère la liste des balises HTML autorisées par Xss::filterAdmin().
     *
     * @return array la liste des balises HTML autorisées par Xss::filterAdmin().
     */
    public static function getAdminTagList():array
    {
        return static::$adminTags;
    }

    /**
     * Traite une balise HTML.
     *
     * @param string    $string    La balise HTML à traité
     * @param array     $htmlTags  Un tableau où les clés sont les balises autorisées
     *                             et les valeurs ne sont pas utilisées.
     * @return void
     *  Si l'élément n'est pas autorisé, une chaîne vide. Sinon, la version nettoyée de l'élément HTML.
     */
    private static function split(string $string, array $htmlTags):string
    {
        $matches = null;
        if (substr($string, 0, 1) != '<') {
            //Nous avons une correspondances d'un seul caractère ">".
            return '&gt;';
        } elseif (strlen($string) == 1) {
            //Nous avons une correspondances d'un seul caractère "<".
            return '&lt;';
        }

        if (!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9\-]+)\s*([^>]*)>?|(<!--.*?-->)$%', $string, $matches)) {
            //Chaine de caractères mal formés.
            return '';
        }

        $slash      = trim($matches[1]);
        $elem       = &$matches[2];
        $attributes = &$matches[3];
        $comment    = &$matches[4];

        if ($comment) {
            $elem = '!--';
        }

        // Reportez-vous à la méthode ::needsRemoval() pour décider si l'élément doit être supprimé.
        // Cela permet à la liste de balises d'être traitée soit comme une liste de balises autorisées,
        // soit comme une liste de balises refusées.
        if (static::needsRemoval($htmlTags, $elem)) {
            return '';
        }
        
        if ($comment) {
            return $comment;
        }
        
        if ($slash != '') {
            return sprintf('</%s>', $elem);
        }

        // Y a-t-il une barre oblique XHTML fermante à la fin des attributs ?
        $attributes = preg_replace('%(\s?)/\s*$%', '\1', $attributes, -1, $count);
        $xhtmlSlash = $count ? ' /' : '';

        $attr2 = static::cleanAtributes($attributes);

        return sprintf("<%s%s%s>", $elem, $attr2, $xhtmlSlash);
    }

    private static function cleanAtributes(string $attributes):string
    {
        //Nettoyer les attributs.
        $attr2 = implode(' ', static::attributes($attributes));
        $attr2 = preg_replace('/[<>]/', '', $attr2);
        $attr2 = strlen($attr2) ? ' ' . $attr2 : '';

        return $attr2;
    }

    /**
     * Traite une chaîne d'attributs HTML.
     *
     * @param string $attributes  Les attributs HTML à traiter.
     *
     * @return array Renvoie un tableau d'attributs HTML nettoyé.
     */
    private static function attributes(string $attributes):array
    {
        $param = new stdClass;
        $param->attributesArray = [];
        $param->mode = 0;
        $param->attributeName = '';
        $param->skip = false;
        $param->skipProtFiltering = false;

        while (strlen($attributes) != 0) {
            //La dernière opération a-t-elle été réussie ?
            $param->working = 0;

            switch ($param->mode) {
                case 0:
                    static::procesingNameAttributes($attributes, $param);
                    break;
                case 1:
                    static::procesingEqualAttributes($attributes, $param);
                    break;
                case 2:
                    static::procesingValueAttributes($attributes, $param);
                    break;
            }
            if ($param->working == 0) {
                //Pas bien formé; supprimer et réessayer.
                $patterns = [
                    '/',
                    '^',
                    '(',
                    // Une chaîne qui commence par un guillemet double,
                    // jusqu'au prochain guillemet double ou la fin de la chaîne
                    '"[^"]*("|$)',
                    // ou
                    '|',
                    //Une chaîne qui commence par un guillemet, jusqu'au prochain guillemet ou la fin de la chaîne
                    '\'[^\']*(\'|$)|',
                    // ou
                    '|',
                    // uUn caractère non blanc
                    '\S',
                    // N'importe quel nombre des trois ci-dessus
                    ')*',
                    // N'importe quel nombre d'espaces
                    '\s*',
                    '/x',
                ];
                $attributes = preg_replace(implode("\n", $patterns), '', $attributes);
                $param->mode = 0;
            }
        }

        //La liste d'attributs se termine par un attribut sans valeur comme « selected ».
        if ($param->mode == 1 && !$param->skip) {
            $param->attributesArray[] = $param->attributeName;
        }

        $attributesArray = $param->attributesArray;
        unset($param);
        return $attributesArray;
    }

    private static function procesingNameAttributes(string &$attributes, stdClass $params)
    {
        // Nom de l'attribut, href par exemple.
        if (preg_match('/^([-a-zA-Z][-a-zA-Z0-9]*)/', $attributes, $match)) {
            $params->attributeName = strtolower($match[1]);
            $params->skip = (
                $params->attributeName == 'style' ||
                substr($params->attributeName, 0, 2) == 'on' ||
                substr($params->attributeName, 0, 1) == '-' ||
                //Ignorer les attributs longs pour éviter une surcharge de traitement inutile
                strlen($params->attributeName) > 96
            );
            // Les valeurs des attributs de type URI doivent être filtrées
            // pour les protocoles potentiellement malveillants
            // (par exemple, un attribut href commençant par "javascript:").
            //
            // Cependant, pour certains attributs non-URI, l'exécution de ce filtrage
            // entraîne la falsification des données valides et sûres.
            // Nous empêchons cela en sautant le filtrage de protocole sur ces attributs.
            // @see  \Bloomiss\Component\Utility\UrlHelper::filterBadProtocol()
            // @see  http://www.w3.org/TR/html4/index/attributes.html
            $params->skipProtFiltering =
                substr($params->attributeName, 0, 5) ==='data-' || in_array($params->attributeName, [
                'title',
                'alt',
                'rel',
                'property',
            ]);

            $params->working = $params->mode = 1;
            $attributes = preg_replace('/^[-a-zA-Z][-a-zA-Z0-9]*/', '', $attributes);
        }
    }

    private static function procesingEqualAttributes(string &$attributes, stdClass $params)
    {
        //Signe égal ou sans valeur ("selected").
        if (preg_match('/^\s*=\s*/', $attributes)) {
            $params->working = 1;
            $params->mode = 2;
            $attributes = preg_replace('/^\s*=\s*/', '', $attributes);
            return;
        }

        if (preg_match('/^\s+/', $attributes)) {
            $params->working = 1;
            $params->mode = 0;
            if (!$params->skip) {
                $params->attributesArray[] = $params->attributeName;
            }
            $attributes = preg_replace('/^\s+/', '', $attributes);
        }
    }

    private static function procesingValueAttributes(string &$attributes, stdClass $params)
    {
        // Valeur de l'attribut, une URL après href= par exemple.
        $regexs = [
            [
                'match' => '/^"([^"]*)"(\s+|$)/',
                'replace' => '/^"[^"]*"(\s+|$)/',
                'att' => '%s="%s"'
            ],
            [
                'match' => "/^'([^']*)'(\s+|$)/",
                'replace' => "/^'[^']*'(\s+|$)/",
                'att' => "%s='%s'",
            ],
            [
                'match' => "%^([^\s\"']+)(\s+|$)%",
                'replace' => "%^[^\s\"']+(\s+|$)%",
                'att' => '%s="%s"'
            ],
        ];
        $urlHelper = static::load()->UrlHelper;
        foreach ($regexs as $regex) {
            if (preg_match($regex['match'], $attributes, $match)) {
                $value = $params->skipProtFiltering ? $match[1] :
                    $urlHelper::filterBadProtocol($match[1]);
                
                if (!$params->skip) {
                    $params->attributesArray[] = sprintf($regex['att'], $params->attributeName, $value);
                }
                $params->working = 1;
                $params->mode = 0;
                $attributes = preg_replace($regex['replace'], '', $attributes);
                return;
            }
        }
    }
    /**
     * Si cet élément doit être complètement supprimé.
     *
     * @param array     $htmlTags   Liste des balises HTML
     * @param string    $elem       Le nom d'un élément HTML.
     * @return bool true si l'élément à besoin d'être supprimé.
     */
    private static function needsRemoval(array $htmlTags, string $elem):bool
    {
        return !isset($htmlTags[strtolower($elem)]);
    }
}
