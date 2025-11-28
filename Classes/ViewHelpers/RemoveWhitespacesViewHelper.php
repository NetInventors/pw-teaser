<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class RemoveWhitespacesViewHelper extends AbstractViewHelper
{
    public function render(): string
    {
        return \str_replace(["\t", "\r", "\n"], '', $this->renderChildren());
    }
}
