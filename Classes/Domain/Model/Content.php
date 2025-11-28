<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Content extends AbstractEntity
{
    protected string $ctype;

    protected int $colPos;

    protected string $header;

    protected string $bodytext;

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $image;

    /**
     *
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $assets;

    /**
     * @var ObjectStorage<Category>
     */
    protected ObjectStorage $categories;

    protected array $contentRow;

    public function __construct()
    {
        $this->image      = new ObjectStorage();
        $this->assets     = new ObjectStorage();
        $this->categories = new ObjectStorage();
    }

    public function setImage(ObjectStorage $image): void
    {
        $this->image = $image;
    }

    public function getImage(): ObjectStorage
    {
        return $this->image;
    }

    public function addImage(FileReference $image): void
    {
        $this->image->attach($image);
    }

    public function removeImage(FileReference $image): void
    {
        $this->image->detach($image);
    }

    public function getImageFiles(): array
    {
        $imageFiles = [];

        /** @var FileReference $image */
        foreach ($this->getImage() as $image) {
            $imageFiles[] = $image->getOriginalResource()->toArray();
        }

        return $imageFiles;
    }

    public function setAssets(ObjectStorage $assets): void
    {
        $this->assets = $assets;
    }

    public function getAssets(): ObjectStorage
    {
        return $this->assets;
    }

    public function addAssets(FileReference $assets):void
    {
        $this->assets->attach($assets);
    }

    public function removeAssets(FileReference $assets): void
    {
        $this->assets->detach($assets);
    }

    public function getAssetsFiles(): array
    {
        $assetsFiles = [];

        /** @var FileReference $assets */
        foreach ($this->getAssets() as $assets) {
            $assetsFiles[] = $assets->getOriginalResource()->toArray();
        }

        return $assetsFiles;
    }

    public function setBodytext(string $bodytext): void
    {
        $this->bodytext = $bodytext;
    }

    public function getBodytext(): string
    {
        return $this->bodytext;
    }

    public function setCtype(string $ctype): void
    {
        $this->ctype = $ctype;
    }

    public function getCtype(): string
    {
        return $this->ctype;
    }

    public function setColPos(int $colPos): void
    {
        $this->colPos = $colPos;
    }

    public function getColPos(): int
    {
        return $this->colPos;
    }

    public function setHeader(string $header): void
    {
        $this->header = $header;
    }

    public function getHeader(): string
    {
        return $this->header;
    }

    public function getCategories(): ObjectStorage
    {
        return $this->categories;
    }

    public function setCategories(ObjectStorage $categories): void
    {
        $this->categories = $categories;
    }

    public function addCategory(Category $category): void
    {
        $this->categories->attach($category);
    }

    public function removeCategory(Category $category): void
    {
        $this->categories->detach($category);
    }

    public function getContentRow(): array
    {
        return $this->contentRow;
    }
}
