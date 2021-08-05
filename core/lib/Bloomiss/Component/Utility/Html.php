<?php

namespace Bloomiss\Component\Utility;

/**
 * Fournit des aides DOMDocument pour l'analyse et la sérialisation des chaînes HTML.
 */
class Html
{
    use LoaderTrait;
    /**
     * Décode toutes les entités HTML, y compris les entités numériques, en octets UTF-8 normaux.
     *
     * Les entités à double échappement ne seront décodées qu'une seule fois
     * ("&amp;lt;" devient "&lt;", et non "<"). Soyez prudent lorsque vous utilisez cette fonction,
     * car elle annulera les efforts de désinfection précédents (&lt;script&gt; deviendra <script>).
     *
     * Cette méthode n'est pas le contraire de Html::escape().
     * Par exemple, cette méthode convertira "&eacute;" en "é",
     * alors que Html::escape() ne convertira pas "é" en "&eacute;".
     *
     * @param string $text Le texte dans lequel décoder les entités.
     * @return string L'entrée $text, avec toutes les entités HTML décodées une fois.
     *
     * @see \html_entity_decode()
     * @see escape()
     */
    public static function decodeEntities(string $text):string
    {
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Échappe le texte en convertissant les caractères spéciaux en entités HTML.
     *
     * Cette méthode échappe au code HTML à des fins de nettoyage en remplaçant
     * les caractères spéciaux suivants par leurs équivalents d'entité HTML :
     *  - & (esperluette)           devient &amp;
     *  - " (guillemets doubles)    devient &quot;
     *  - ' (guillemet simple)      devient &#039;
     *  - < (inférieur à)           devient &lt;
     *  - > (supérieur à)           devient &gt;
     *
     * Les caractères spéciaux qui ont déjà été échappés seront doublement échappés
     * (par exemple, "&lt;" devient "&amp;lt;"),
     * et l'encodage UTF-8 non valide sera converti en caractère de remplacement Unicode ("�").
     *
     * Cette méthode n'est pas l'inverse de Html::decodeEntities().
     * Par exemple, cette méthode n'encodera pas "é" en "&eacute;", alors que
     * Html::decodeEntities() convertira toutes les entités HTML en octets UTF-8,
     * y compris "&eacute;" et "&lt;" à "é" et "<".
     *
     * Lors de la construction de tableaux de rendu @link theme_render @endlink,
     * il n'est pas recommandé de transmettre la sortie de Html::escape()
     * à '#markup'. Utilisez la clé '#plain_text' à la place et
     * le moteur de rendu échappera automatiquement le texte.
     *
     * @param string $text
     * @return string
     */
    public static function escape(string $text):string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
