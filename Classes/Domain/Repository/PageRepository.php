<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\Domain\Repository;

use PwTeaserTeam\PwTeaser\Domain\Model\Page;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository as TYPO3PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class PageRepository extends Repository
{
    public const CATEGORY_MODE_OR = 1;

    public const CATEGORY_MODE_AND = 2;

    public const CATEGORY_MODE_OR_NOT = 3;

    public const CATEGORY_MODE_AND_NOT = 4;

    protected string $orderBy = 'uid';

    protected string $orderDirection = QueryInterface::ORDER_ASCENDING;

    protected QueryInterface|null $query = null;

    protected array $queryConstraints = [];

    public function initializeObject(): void
    {
        $querySettings = $this->createQuery()->getQuerySettings();

        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);

        $this->query = $this->createQuery();
    }

    public function findByPid(int $pid): array|QueryResultInterface
    {
        $translatedPid = $this->translatePids([ $pid ]);

        $this->addQueryConstraint($this->query->equals('pid', \reset($translatedPid)));

        return $this->executeQuery();
    }

    public function findByPidRecursively(int $pid, int $recursionDepthFrom, int $recursionDepth): array
    {
        return $this->findChildrenRecursivelyByPidList((string) $pid, $recursionDepthFrom, $recursionDepth);
    }

    public function findByPidList(string $pidlist, bool $orderByPlugin = false): array
    {
        $pagePids = GeneralUtility::intExplode(',', $pidlist, true);

        if (empty($pagePids)) {
            return [];
        }

        $query = $this->query;

        $this->addQueryConstraint($query->in('uid', $this->translatePids($pagePids)));
        $query->matching($query->logicalAnd(...$this->queryConstraints));

        if (false === $orderByPlugin) {
            $this->handleOrdering($query);

            $results = $query->execute();

            $this->resetQuery();

            return $this->handlePageLocalization($results);
        }

        $results = $query->execute();

        $this->resetQuery();

        return $this->orderByPlugin($pagePids, $this->handlePageLocalization($results));
    }

    protected function orderByPlugin(array $pagePids, array $results): array
    {
        $sortedResults = [];

        foreach ($pagePids as $pagePid) {
            foreach ($results as $result) {
                if ($pagePid === $result->getUid()) {
                    $sortedResults[] = $result;
                }
            }
        }

        return $sortedResults;
    }

    public function findChildrenByPidList(string $pidlist): array
    {
        $pagePids = GeneralUtility::intExplode(',', $pidlist, true);

        if (empty($pagePids)) {
            return [];
        }

        $this->addQueryConstraint($this->query->in('pid', $this->translatePids($pagePids)));

        return $this->executeQuery();
    }

    public function findChildrenRecursivelyByPidList(
        string $pidlist,
        int $recursionDepthFrom,
        int $recursionDepth,
    ): array {
        $pagePids       = $this->getRecursivePageList($pidlist, $recursionDepthFrom, $recursionDepth);
        $translatedPids = $this->translatePids($pagePids);

        $this->addQueryConstraint(
            $this->query->in('uid', [] === $translatedPids ? $pagePids : $translatedPids),
        );

        return $this->executeQuery();
    }

    protected function translatePids(array $pidList, int|null $languageUid = null): array
    {
        if ([] === $pidList) {
            return $pidList;
        }

        if (!$languageUid) {
            /** @var Context $context */
            $context     = GeneralUtility::makeInstance(Context::class);
            $languageUid = $context->getPropertyFromAspect('language', 'id');
        }

        /** @var ConnectionPool $pool */
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);

        $translatedPidList = [];

        foreach ($pidList as $pid) {
            $queryBuilder  = $pool->getQueryBuilderForTable('pages');
            $translatedRow = $queryBuilder
                ->select('*')
                ->from('pages')
                ->where('l10n_parent = :pid AND sys_language_uid = :lang')
                ->setParameter('pid', $pid)
                ->setParameter('lang', $languageUid)
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative()
            ;

            $translatedPidList[$pid] = $translatedRow ? $translatedRow['uid'] : $pid;
        }

        return \array_values($translatedPidList);
    }

    protected function addQueryConstraint(ConstraintInterface $constraint): void
    {
        $this->queryConstraints[] = $constraint;
    }

    public function addCategoryConstraint(array $categories, bool $isAnd = true, bool $isNot = false): void
    {
        if (true === $isAnd && false === $isNot) {
            $this->queryConstraints[] = $this->query->logicalAnd(...$this->buildCategoryConstraint($categories));
        }

        if (true === $isAnd && true === $isNot) {
            $this->queryConstraints[] = $this->query->logicalNot(
                $this->query->logicalAnd(
                    ...$this->buildCategoryConstraint($categories),
                ),
            );
        }

        if (false === $isAnd && false === $isNot) {
            $this->queryConstraints[] = $this->query->logicalOr(...$this->buildCategoryConstraint($categories));
        }

        if (false === $isAnd && true === $isNot) {
            $this->queryConstraints[] = $this->query->logicalNot(
                $this->query->logicalOr(
                    ...$this->buildCategoryConstraint($categories),
                ),
            );
        }
    }

    protected function buildCategoryConstraint(array $categories): array
    {
        $contraints = [];

        foreach ($categories as $category) {
            $contraints[] = $this->query->contains('categories', $category);
        }

        return $contraints;
    }

    protected function executeQuery(): QueryResultInterface|array
    {
        $query = $this->query;

        $query->matching($query->logicalAnd(...$this->queryConstraints));
        $this->handleOrdering($query);

        $queryResult = $query->execute();

        $this->resetQuery();

        return $this->handlePageLocalization($queryResult);
    }

    protected function handlePageLocalization(QueryResult $pages): array
    {
        /** @var Context $context */
        $context        = GeneralUtility::makeInstance(Context::class);
        $currentLangUid = $context->getPropertyFromAspect('language', 'id');
        $displayedPages = [];

        /** @var Page $page */
        foreach ($pages as $page) {
            if ($currentLangUid === 0) {
                if (
                    $page->getL18nConfiguration() !== Page::L18N_HIDE_DEFAULT_LANGUAGE
                    && $page->getL18nConfiguration() !== Page::L18N_HIDE_ALWAYS_BUT_TRANSLATION_EXISTS
                ) {
                    $displayedPages[] = $page;
                }
            } else {
                /** @var TYPO3PageRepository $pageSelect */
                $pageSelect          = $GLOBALS['TSFE']->sys_page;
                $pageRowWithOverlays = $pageSelect->getPage($page->getUid());

                if (false === (boolean) $GLOBALS['TYPO3_CONF_VARS']['FE']['hidePagesIfNotTranslatedByDefault']) {
                    if (
                        isset($pageRowWithOverlays['_PAGES_OVERLAY'])
                        || !(
                            $page->getL18nConfiguration() === Page::L18N_HIDE_IF_NO_TRANSLATION_EXISTS
                            || $page->getL18nConfiguration() === Page::L18N_HIDE_ALWAYS_BUT_TRANSLATION_EXISTS
                        )
                    ) {
                        $displayedPages[] = $page;
                    }
                } elseif (
                    isset($pageRowWithOverlays['_PAGES_OVERLAY'])
                    || (
                        $page->getL18nConfiguration() === Page::L18N_HIDE_IF_NO_TRANSLATION_EXISTS
                        || $page->getL18nConfiguration() === Page::L18N_HIDE_ALWAYS_BUT_TRANSLATION_EXISTS
                    )
                ) {
                    $displayedPages[] = $page;
                }
            }
        }

        return $displayedPages;
    }

    protected function getRecursivePageList(string $pidlist, int $recursionDepthFrom, int $recursionDepth): array
    {
        $pids = GeneralUtility::intExplode(',', $pidlist, true);

        /** @var TYPO3PageRepository $pageSelect */
        $pageSelect = $GLOBALS['TSFE']->sys_page;

        $pagePids = [];

        foreach ($pids as $pid) {
            if (0 === $recursionDepthFrom) {
                $pagePids[] = [ $pid ];
            }

            $pagePids[] = $pageSelect->getDescendantPageIdsRecursive($pid, $recursionDepth, $recursionDepthFrom);
        }

        $pagePids = \array_merge(...$pagePids);

        return \array_unique($pagePids);
    }

    public function setOrderBy(string $orderBy): void
    {
        if ('random' !== $orderBy) {
            $this->orderBy = $orderBy;
        }
    }

    public function setOrderDirection(string $orderDirection): void
    {
        $this->orderDirection = 'desc' === $orderDirection || '1' === $orderDirection
            ? QueryInterface::ORDER_DESCENDING
            : QueryInterface::ORDER_ASCENDING
        ;
    }

    public function setLimit(int $limit): void
    {
        $this->query->setLimit($limit);
    }

    public function setShowNavHiddenItems(bool $showNavHiddenItems): void
    {
        $navHide = [ 0 ];

        if (true === $showNavHiddenItems) {
            $navHide[] = 1;
        }

        $this->addQueryConstraint($this->query->in('nav_hide', $navHide));
    }

    public function setFilteredDokType(array $dokTypesToFilterFor): void
    {
        if (0 < \count($dokTypesToFilterFor)) {
            $this->addQueryConstraint($this->query->in('doktype', $dokTypesToFilterFor));
        }
    }

    public function setIgnoreOfUid(int $currentPageUid): void
    {
        $this->addQueryConstraint($this->query->logicalNot($this->query->equals('uid', $currentPageUid)));
        $this->addQueryConstraint($this->query->logicalNot($this->query->equals('l10n_parent', $currentPageUid)));
    }

    protected function handleOrdering(QueryInterface $query): void
    {
        $query->setOrderings([ $this->orderBy => $this->orderDirection ]);
    }

    protected function resetQuery(): void
    {
        unset($this->query);

        $this->query = $this->createQuery();

        unset($this->queryConstraints);

        $this->queryConstraints = [];
    }
}
