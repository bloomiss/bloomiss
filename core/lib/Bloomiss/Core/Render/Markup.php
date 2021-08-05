<?php

namespace Bloomiss\Core\Render;

use Bloomiss\Component\Render\MarkupInterface;
use Bloomiss\Component\Render\MarkupTrait;
use Bloomiss\Component\Utility\LoaderTrait;
use Countable;

/**
 * Définit un objet qui transmet des chaînes sécurisées via le système de rendu.
 *
 * Cet objet ne doit être construit qu'avec une chaîne sûre connue.
 * S'il existe un risque que la chaîne contienne des données saisies par l'utilisateur
 * qui n'ont pas été filtrées au préalable, elle ne doit pas être utilisée.
 *
 * @internal Cet objet est marqué comme interne car il ne doit être utilisé que pendant le rendu.
 */
final class Markup implements MarkupInterface, Countable
{
    use LoaderTrait;
    use MarkupTrait;
}
