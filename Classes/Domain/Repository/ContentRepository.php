<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\Domain\Repository;

use PwTeaserTeam\PwTeaser\Domain\Model\Page;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ContentRepository extends Repository
{

    public function initializeObject(): void
    {
        $querySettings = $this->createQuery()->getQuerySettings();

        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @return QueryResultInterface|list<array<string,mixed>>
     */
    public function findByPid(int $pid): QueryResult|array
    {
        $query = $this->createQuery();

        $query->matching($query->equals('pid', $pid));
        $query->setOrderings([
            'sorting' => QueryInterface::ORDER_ASCENDING,
        ]);

        return $query->execute();
    }

    /**
     * @param list<Page> $pages Pages to get content elements
     *
     * @return QueryResultInterface|list<array<string,mixed>>
     */
    public function findByPages(array $pages): QueryResult|array
    {
        $query       = $this->createQuery();
        $constraints = [];

        foreach ($pages as $page) {
            $constraints[] = $query->equals('pid', $page->getUid());
        }

        $query->matching($query->logicalOr(...$constraints));

        return $query->execute();
    }
}
