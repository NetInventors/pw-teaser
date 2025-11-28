<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\Domain\Model;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Page extends AbstractEntity
{
    public const L18N_SHOW_ALWAYS = 0;

    public const L18N_HIDE_DEFAULT_LANGUAGE = 1;

    public const L18N_HIDE_IF_NO_TRANSLATION_EXISTS = 2;

    public const L18N_HIDE_ALWAYS_BUT_TRANSLATION_EXISTS = 3;

    protected int $doktype;

    protected bool $isCurrentPage = false;

    protected string $navTitle = '';

    protected string|null $slug = null;

    /**
     * @TYPO3\CMS\Extbase\Annotation\Validate("NotEmpty")
     */
    protected string $title = '';

    protected string $subtitle = '';

    protected string|null $keywords = null;

    protected string|null $description = null;

    protected string|null $abstract = null;

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $media;

    protected int $sorting = 0;

    protected int $crdate = 0;

    protected int $tstamp = 0;

    protected int $lastUpdated = 0;

    protected int $starttime = 0;

    protected int $endtime = 0;

    protected int $newUntil = 0;

    protected string $author = '';

    protected string $authorEmail = '';

    /**
     * @var list<Content>
     */
    protected array $contents;

    /**
     * @var ObjectStorage<Category>
     */
    protected ObjectStorage $categories;

    /**
     * @var integer
     */
    protected int $l18nConfiguration;

    /**
     * @var list<Page>
     */
    protected array $childPages = [];

    protected array|null $pageRow = null;

    public function __construct()
    {
        $this->categories = new ObjectStorage();
        $this->media      = new ObjectStorage();
    }

    public function setContents(array $contents): void
    {
        $this->contents = $contents;
    }

    public function getContents(): array
    {
        return $this->contents;
    }

    public function setIsCurrentPage(bool $isCurrentPage): void
    {
        $this->isCurrentPage = $isCurrentPage;
    }

    public function getIsCurrentPage(): bool
    {
        return $this->isCurrentPage;
    }

    public function getAuthorEmail(): string
    {
        return $this->authorEmail;
    }

    public function getKeywords(): string|null
    {
        return $this->keywords;
    }

    /**
     * @return list<string>
     */
    public function getKeywordsAsArray(): array
    {
        if (null === $this->keywords) {
            return [];
        }

        return GeneralUtility::trimExplode(',', $this->keywords, true);
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function getAbstract(): string|null
    {
        return $this->abstract;
    }

    public function getNavTitle(): string
    {
        return $this->navTitle;
    }

    public function getSlug(): string|null
    {
        return $this->slug;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getMedia(): ObjectStorage
    {
        return $this->media;
    }

    public function getNewUntil(): int
    {
        return $this->newUntil;
    }

    public function getIsNew(): bool
    {
        if (0 === $this->newUntil) {
            return false;
        }

        return \time() < $this->newUntil;
    }

    public function getCrdate(): int
    {
        return $this->crdate;
    }

    public function getTstamp(): int
    {
        return $this->tstamp;
    }

    public function getLastUpdated(): int
    {
        return $this->lastUpdated;
    }

    public function getStarttime(): int
    {
        return $this->starttime;
    }

    public function getEndtime(): int
    {
        return $this->endtime;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getDoktype(): int
    {
        return $this->doktype;
    }

    public function getL18nConfiguration(): int
    {
        return $this->l18nConfiguration;
    }

    /**
     * @return ObjectStorage<Category>
     */
    public function getCategories(): ObjectStorage
    {
        return $this->categories;
    }

    public function getRootLine(): array
    {
        $context = GeneralUtility::makeInstance(Context::class);

        /** @var RootlineUtility $rootline */
        $rootline = GeneralUtility::makeInstance(RootlineUtility::class, $this->getUid(), '', $context);

        return $rootline->get();
    }

    public function getRootLineDepth(): int
    {
        return \count($this->getRootLine());
    }

    public function getRecursiveRootLineOrdering(): string
    {
        $recursiveOrdering = [];

        foreach ($this->getRootLine() as $pageRootPart) {
            \array_unshift($recursiveOrdering, \str_pad($pageRootPart['sorting'], 11, '0', STR_PAD_LEFT));
        }

        return \implode('-', $recursiveOrdering);
    }

    public function getPageRow(): array
    {
        return $this->pageRow;
    }

    public function getSorting(): int
    {
        return $this->sorting;
    }

    public function setChildPages(array $childPages): void
    {
        $this->childPages = $childPages;
    }

    public function getChildPages(): array
    {
        return $this->childPages;
    }
}
