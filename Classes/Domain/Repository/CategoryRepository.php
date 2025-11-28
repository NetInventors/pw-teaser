<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\Domain\Repository;

use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Category>
 */
final class CategoryRepository extends Repository
{
    protected $defaultOrderings = [
        'title' => QueryInterface::ORDER_ASCENDING,
    ];

    /**
     * @return QueryResultInterface<int, Category>
     */
    public function findByParent(int $parentUid): QueryResultInterface
    {
        $q = $this->createQuery();

        $q->matching($q->equals('parent', $parentUid));

        return $q->execute();
    }

    public function findByUids(array $uids): QueryResultInterface
    {
        $q = $this->createQuery();

        $q->matching($q->in('uid', $uids));

        return $q->execute();
    }
}
