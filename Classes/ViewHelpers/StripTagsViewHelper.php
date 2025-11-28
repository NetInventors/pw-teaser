<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class StripTagsViewHelper extends AbstractViewHelper
{
    public function render($string = null): string
    {
        if ($string === null) {
            $string = \html_entity_decode($this->renderChildren());
        }

        return \strip_tags($string);
    }
}
