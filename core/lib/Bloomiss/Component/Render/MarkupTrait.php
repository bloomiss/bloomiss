<?php

namespace Bloomiss\Component\Render;

/**
 * Implémente MarkupInterface et Countable pour les objets rendus.
 */
trait MarkupTrait
{

    /**
     * Crée un objet Markup si nécessaire.
     *
     * Si $string est égal à une chaîne vide,
     * il n'est pas nécessaire de créer un objet Markup.
     *
     * Si $string est un objet qui implémente MarkupInterface,
     * il est retourné inchangé.
     *
     * @param mixed $string
     * @return string|MarkupInterface
     */
    public static function create($string)
    {
        if ($string instanceof MarkupInterface) {
            return $string;
        }

        $string = (string)$string;
        if ($string === '') {
            return '';
        }

        $safeString = loadFactory()->Markup;
        $safeString->string = $string;
        return $safeString;
    }

    /**
     * Renvoie la version chaîne de l'objet Markup.
     *
     * @return string Le contenu de la chaîne sécurié
     */
    public function __toString():string
    {
        return $this->string;
    }

    /**
     * Renvoie le nombre de caractère dans la chaîne.
     *
     * @return int Le nombre de caractères.
     */
    public function count():int
    {
        return mb_strlen($this->__toString());
    }
    /**
     * Renvoie une représentation de l'objet à utiliser dans la sérialisation JSON.
     *
     * @return string Le contenu de la chaîne sécurié
     */
    public function jsonSerialize():string
    {
        return $this->__toString();
    }
}
