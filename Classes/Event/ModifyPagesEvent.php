<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\Event;

use PwTeaserTeam\PwTeaser\Controller\TeaserController;

final class ModifyPagesEvent
{
    private array $pages;

    private TeaserController $teaserController;

    public function __construct(array $pages, TeaserController $newsController)
    {
        $this->pages            = $pages;
        $this->teaserController = $newsController;
    }

    public function getPages(): array
    {
        return $this->pages;
    }

    public function setPages(array $pages): void
    {
        $this->pages = $pages;
    }

    public function getTeaserController(): TeaserController
    {
        return $this->teaserController;
    }
}
