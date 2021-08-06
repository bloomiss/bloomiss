<?php

namespace Bloomiss\Component\Utility;

/**
 * Fournit des conversions et des opérations liées à Unicode.
 *
 * @ingroup utility
 */
class Unicode
{
    /**
     * Vérifie si une chaîne est au format UTF-8 valide.
     *
     * Toutes les fonctions conçues pour filtrer les entrées doivent utiliser bloomiss_validate_utf8
     * pour s'assurer qu'elles fonctionnent sur des chaînes UTF-8 valides afin d'éviter le contournement du filtre.
     *
     * Lorsque le texte contenant un octet de tête UTF-8 non valide (0xC0 - 0xFF) est présenté en UTF-8
     * dans Internet Explorer 6, le programme peut mal interpréter les octets suivants.
     *
     * Lorsque ces octets suivants sont des caractères de contrôle HTML tels que des guillemets ou des chevrons,
     * les parties du texte jugées sûres par les filtres se retrouvent dans des emplacements potentiellement dangereux;
     * Un attribut onerror qui est en dehors d'une balise, et donc considéré comme sûr par un filtre,
     * peut être interprété par le navigateur comme s'il était à l'intérieur de la balise.
     *
     * La fonction ne renvoie pas FALSE pour les chaînes contenant des codes de caractères supérieurs à U+10FFFF,
     * même si ceux-ci sont interdits par la RFC 3629.
     *
     * @param string $text Le texte à controler.
     * @return bool TRUE si le texte est au format UTF-8 valise, False sinon.
     */
    public static function validateUtf8(string $text):string
    {
        if (\strlen($text) == 0) {
            return true;
        }

        // Avec le modificateur PCRE_UTF8 'u', preg_match() échoue silencieusement
        // sur les chaînes contenant des séquences d'octets UTF-8 invalides.
        // Cependant, il ne rejette pas les codes de caractères supérieurs
        // à U+10FFFF (représentés par 4 octets ou plus).
        return (\preg_match('/^./us', $text) == 1);
    }
}
