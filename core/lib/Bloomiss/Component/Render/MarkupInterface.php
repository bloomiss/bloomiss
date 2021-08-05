<?php

namespace Bloomiss\Component\Render;

use JsonSerializable;

/**
 * Marque la méthode __toString() d'un objet comme renvoyant un document Markup.
 *
 * Les objets qui implémentent cette interface ne seront pas automatiquement
 * filtrés contre XSS par le système de rendu ou automatiquement échappés par le moteur de thème.
 *
 * S'il existe un risque que la méthode __toString() de l'objet renvoie des données saisies par l'utilisateur
 * qui n'ont pas été filtrées au préalable, elle ne doit pas être utilisée.
 *
 * Si l'objet qui implémente cela n'effectue pas lui-même d'échappement ou de filtrage automatique,
 * il doit alors être marqué comme "@internal". Par exemple,
 * Views a l'objet interne ViewsRenderPipelineMarkup pour fournir un
 * pipeline de rendu personnalisé afin de rendre JSON et de rendre rapidement les champs.
 * En revanche, FormattableMarkup et TranslatableMarkup nettoient toujours leur sortie
 * lorsqu'ils sont utilisés correctement.
 *
 * Si l'objet doit être utilisé directement dans les modèles Twig,
 * il doit implémenter \Countable afin qu'il puisse être utilisé dans les instructions if.
 *
 * @see  Bloomiss\Component\Render\MarkupTrait
 */
interface MarkupInterface extends JsonSerializable
{
    /**
     * Renvoie un document Markup
     *
     * @return string Le docuement Markup
     */
    public function __toString();
}
