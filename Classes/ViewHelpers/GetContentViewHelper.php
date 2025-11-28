<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\ViewHelpers;

use PwTeaserTeam\PwTeaser\Domain\Model\Content;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class GetContentViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument('contents', 'array', 'Content elements');
        $this->registerArgument('as', 'string', 'the name of the iteration variable', true);
        $this->registerArgument('colPos', 'integer', 'column position to get content elements from', false, 0);
        $this->registerArgument('cType', 'string', 'the cType to filter content elements for');
        $this->registerArgument('index', 'integer', 'limits the output to n-th element');
    }

    public function render(): string
    {
        $contents = $this->arguments['contents'];

        if ($contents === null) {
            return '';
        }

        $output       = '';
        $indexCount   = 0;
        $breakNow     = false;
        $asHasBeenSet = false;

        /** @var $content Content */
        foreach ($contents as $content) {
            $contentCtype  = $content->getCtype();
            $contentColPos = $content->getColPos();

            if ($contentColPos === $this->arguments['colPos']) {
                if ($this->arguments['cType'] === null || $contentCtype === $this->arguments['cType']) {
                    if ($this->arguments['index'] === null) {
                        $this->templateVariableContainer->add($this->arguments['as'], $content);

                        $asHasBeenSet = true;
                    } elseif ($indexCount === $this->arguments['index']) {
                        $this->templateVariableContainer->add($this->arguments['as'], $content);

                        $asHasBeenSet = true;
                        $breakNow     = true;
                    }
                }
            }

            if ($asHasBeenSet) {
                $output .= $this->renderChildren();

                $this->templateVariableContainer->remove($this->arguments['as']);

                $asHasBeenSet = false;
            }

            if ($breakNow) {
                break;
            }

            if ($this->arguments['cType'] === null || $contentCtype === $this->arguments['cType']) {
                $indexCount++;
            }
        }

        return $output;
    }
}
