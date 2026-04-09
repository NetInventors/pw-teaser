<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\Controller;

use Psr\Http\Message\ResponseInterface;
use PwTeaserTeam\PwTeaser\Domain\Model\Page;
use PwTeaserTeam\PwTeaser\Domain\Repository\CategoryRepository;
use PwTeaserTeam\PwTeaser\Domain\Repository\ContentRepository;
use PwTeaserTeam\PwTeaser\Domain\Repository\PageRepository;
use PwTeaserTeam\PwTeaser\Event\ModifyPagesEvent;
use PwTeaserTeam\PwTeaser\Utility\Settings;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3Fluid\Fluid\View\ViewInterface as FluidStandaloneViewInterface;

class TeaserController extends ActionController
{
    protected array $settings = [];

    protected int|null $currentPageUid = null;

    protected ContentObjectRenderer|null $contentObject = null;

    /**
     * @var FluidStandaloneViewInterface|ViewInterface
     */
    protected $view;

    protected array $viewSettings = [];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly ContentRepository $contentRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly Settings $settingsUtility,
    ) {
    }

    public function initializeAction(): void
    {
        $this->settings = $this->settingsUtility->renderConfigurationArray($this->settings);

        $frameworkSettings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
        );

        $viewSettings = $frameworkSettings['view'];
        $presets      = $viewSettings['presets'] ?? [];

        unset($viewSettings['presets']);

        $this->viewSettings = $this->settingsUtility->renderConfigurationArray($viewSettings, 'view.');

        $this->viewSettings['presets'] = $presets;
    }

    public function indexAction(): ResponseInterface
    {
        $this->currentPageUid = $GLOBALS['TSFE']->id;

        $this->performTemplatePathAndFilename();
        $this->setOrderingAndLimitation();
        $this->performPluginConfigurations();

        [ $rootPageUids, $pages ] = match($this->settings['source']) {
            'thisChildrenRecursively' => [
                $this->currentPageUid,
                $this->pageRepository->findByPidRecursively(
                    $this->currentPageUid,
                    (int) $this->settings['recursionDepthFrom'],
                    (int) $this->settings['recursionDepth']
                ),
            ],

            'custom' => [
                $this->settings['customPages'],
                $this->pageRepository->findByPidList(
                    $this->settings['customPages'],
                    '1' === $this->settings['orderByPlugin'],
                ),
            ],

            'customChildren' => [
                $this->settings['customPages'],
                $this->pageRepository->findChildrenByPidList($this->settings['customPages']),
            ],

            'customChildrenRecursively' => [
                $this->settings['customPages'],
                $this->pageRepository->findChildrenRecursivelyByPidList(
                    $this->settings['customPages'],
                    (int) $this->settings['recursionDepthFrom'],
                    (int) $this->settings['recursionDepth'],
                ),
            ],

            // Handles also source=thisChildren
            default  => [
                $this->currentPageUid,
                $this->pageRepository->findByPid($this->currentPageUid),
            ],
        };

        if ('nested' !== $this->settings['pageMode']) {
            $pages = $this->performSpecialOrderings($pages);
        }

        /** @var $page Page */
        foreach ($pages as $page) {
            if ($page->getUid() === $this->currentPageUid) {
                $page->setIsCurrentPage(true);
            }

            // Load contents if enabled in configuration
            if ('1' === $this->settings['loadContents']) {
                $page->setContents($this->contentRepository->findByPid($page->getUid())->toArray());
            }
        }

        if ('nested' === $this->settings['pageMode']) {
            $pages = $this->convertFlatToNestedPagesArray($pages, (string) $rootPageUids);
        }

        /** @var ModifyPagesEvent $event */
        $event = $this->eventDispatcher->dispatch(new ModifyPagesEvent($pages, $this));

        $this->view->assign('pages', $event->getPages());

        if ($this->settings['enablePagination'] ?? true) {
            $itemsPerPage = $this->settings['itemsPerPage'] ?? 10;
            $currentPage  = \max(
                1,
                $this->request->hasArgument('currentPage') ? (int) $this->request->getArgument('currentPage') : 1,
            );

            $paginator = GeneralUtility::makeInstance(
                ArrayPaginator::class,
                $event->getPages(),
                $currentPage,
                (int) $itemsPerPage,
                (int) ($this->settings['limit'] ?? 0),
                0,
            );

            $pagination = $this->getPagination($paginator);

            $this->view->assign('pagination', [
                'currentPage' => $currentPage,
                'paginator'   => $paginator,
                'pagination'  => $pagination,
            ]);
        }

        return $this->responseFactory
            ->createResponse()
            ->withAddedHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->streamFactory->createStream($this->view->render()))
        ;
    }

    protected function sortByRecursivelySorting(Page $a, Page $b): int
    {
        if ($a->getRecursiveRootLineOrdering() === $b->getRecursiveRootLineOrdering()) {
            return 0;
        }

        return ($a->getRecursiveRootLineOrdering() < $b->getRecursiveRootLineOrdering()) ? -1 : 1;
    }

    protected function setOrderingAndLimitation(): void
    {
        if (!empty($this->settings['orderBy'])) {
            $this->pageRepository->setOrderBy(match($this->settings['orderBy']) {
                'customField' => $this->settings['orderByCustomField'],
                default       => $this->settings['orderBy']
            });
        }

        if (!empty($this->settings['orderDirection'])) {
            $this->pageRepository->setOrderDirection($this->settings['orderDirection']);
        }

        if (!empty($this->settings['limit']) && 'random' !== $this->settings['orderBy']) {
            $this->pageRepository->setLimit((int) $this->settings['limit']);
        }
    }

    protected function performTemplatePathAndFilename(): bool
    {
        $templateType = $this->viewSettings['templateType'] ?? '';
        $templateFile = $this->viewSettings['templateRootFile'] ?? '';

        $layoutRootPaths = (array) (($this->viewSettings['layoutRootPath'] ?? null) ?: null);

        if ([] === $layoutRootPaths) {
            $layoutRootPaths = $this->viewSettings['layoutRootPaths'] ?? [];
        }

        $partialRootPaths = (array) (($this->viewSettings['partialRootPath'] ?? null) ?: null);

        if ([] === $partialRootPaths) {
            $partialRootPaths = $this->viewSettings['partialRootPaths'] ?? [];
        }

        $templateRootPaths = (array) (($this->viewSettings['templateRootPath'] ?? null) ?: null);

        if ([] === $templateRootPaths) {
            $templateRootPaths = $this->viewSettings['templateRootPaths'] ?? [];
        }

        $preset = $this->viewSettings['templatePreset'] ?? null;

        if ('preset' === $templateType && !empty($preset)) {
            $currentPreset = $this->viewSettings['presets'][$preset];

            if (\array_key_exists('partialRootPaths', $currentPreset) && !empty($currentPreset['partialRootPaths'])) {
                $partialRootPaths = $currentPreset['partialRootPaths'];
            }

            if (\array_key_exists('layoutRootPaths', $currentPreset) && !empty($currentPreset['layoutRootPaths'])) {
                $layoutRootPaths = $currentPreset['layoutRootPaths'];
            }

            $templateType = 'file';
            $templateFile = $currentPreset['templateRootFile'];
        }

        if ('preset' !== $templateType && $templateRootPaths !== [ null ] && !empty($templateRootPaths)) {
            if (!\file_exists(GeneralUtility::getFileAbsFileName(\reset($templateRootPaths)))) {
                throw new \Exception('Template folder "' . \reset($templateRootPaths) . '" not found!');
            }

            $this->view->setTemplateRootPaths($templateRootPaths);
        }

        if ($layoutRootPaths !== [ null ] && !empty($layoutRootPaths)) {
            if (!\file_exists(GeneralUtility::getFileAbsFileName(reset($layoutRootPaths)))) {
                throw new \Exception('Layout folder "' . \reset($layoutRootPaths) . '" not found!');
            }

            $this->view->setLayoutRootPaths($layoutRootPaths);
        }
        if ($partialRootPaths !== [ null ] && !empty($partialRootPaths)) {
            if (!\file_exists(GeneralUtility::getFileAbsFileName(reset($partialRootPaths)))) {
                throw new \Exception('Partial folder "' . \reset($partialRootPaths) . '" not found!');
            }

            $this->view->setPartialRootPaths($partialRootPaths);
        }
        if (
            $templateType === 'file'
            && !empty($templateFile)
            && \file_exists(GeneralUtility::getFileAbsFileName($templateFile))
        ) {
            $this->view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templateFile));

            return true;
        }

        $templatePathAndFilename = $this->viewSettings['templatePathAndFilename'] ?? '';
        if (
            $templateType === null && !empty($templatePathAndFilename)
            && \file_exists(GeneralUtility::getFileAbsFileName($templatePathAndFilename))
        ) {
            $this->view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templatePathAndFilename));

            return true;
        }

        return false;
    }

    protected function performPluginConfigurations(): void
    {
        $this->pageRepository->setShowNavHiddenItems(('1' === $this->settings['showNavHiddenItems']));
        $this->pageRepository->setFilteredDokType(
            GeneralUtility::trimExplode(
                ',',
                $this->settings['showDoktypes'],
                true,
            )
        );

        if ('1' === ($this->settings['hideCurrentPage'] ?? null)) {
            $this->pageRepository->setIgnoreOfUid($this->currentPageUid);
        }

        if ($this->settings['ignoreUids'] ?? null) {
            $ignoringUids = GeneralUtility::trimExplode(',', $this->settings['ignoreUids'], true);
            \array_map([ $this->pageRepository, 'setIgnoreOfUid' ], $ignoringUids);
        }

        if (($this->settings['categoriesList'] ?? null) && ($this->settings['categoryMode'] ?? null)) {
            $categories = [];

            foreach (GeneralUtility::intExplode(',', $this->settings['categoriesList'], true) as $categoryUid) {
                $categories[] = $this->categoryRepository->findByUid($categoryUid);
            }

            $isAnd = match ((int) $this->settings['categoryMode']) {
                PageRepository::CATEGORY_MODE_OR,
                PageRepository::CATEGORY_MODE_OR_NOT => false,
                default                              => true,
            };

            $isNot = match ((int) $this->settings['categoryMode']) {
                PageRepository::CATEGORY_MODE_AND_NOT,
                PageRepository::CATEGORY_MODE_OR_NOT => true,
                default                              => false,
            };

            $this->pageRepository->addCategoryConstraint($categories, $isAnd, $isNot);
        }

        if ('custom' === $this->settings['source']) {
            $this->settings['pageMode'] = 'flat';
        }

        if ('nested' === $this->settings['pageMode']) {
            $this->settings['recursionDepthFrom'] = 0;
            $this->settings['orderBy']            = 'uid';
            $this->settings['limit']              = 0;
        }
    }

    /**
     * @param list<Page> $pages
     */
    protected function performSpecialOrderings(array $pages): array
    {
        if ('random' === $this->settings['orderBy']) {
            \shuffle($pages);

            if (!empty($this->settings['limit'])) {
                $pages = \array_slice($pages, 0, $this->settings['limit']);
            }
        }

        if ('sorting' === $this->settings['orderBy'] && \str_contains($this->settings['source'], 'Recursively')) {
            \usort($pages, [ $this, 'sortByRecursivelySorting' ]);

            if (\strtolower($this->settings['orderDirection']) === \strtolower(QueryInterface::ORDER_DESCENDING)) {
                $pages = \array_reverse($pages);
            }

            if (!empty($this->settings['limit'])) {
                return \array_slice($pages, 0, $this->settings['limit']);
            }

            return $pages;
        }

        return $pages;
    }

    /**
     * @param list<Page> $pages
     *
     * @return list<Page>
     */
    protected function convertFlatToNestedPagesArray(array $pages, string $rootPageUids): array
    {
        $rootPageUidArray = GeneralUtility::intExplode(',', $rootPageUids);
        $rootPages        = [];

        foreach ($rootPageUidArray as $rootPageUid) {
            $page = $this->pageRepository->findByUid($rootPageUid);

            $this->fillChildPagesRecursivley($page, $pages);

            $rootPages[] = $page;
        }

        return $rootPages;
    }

    protected function fillChildPagesRecursivley(Page $parentPage, array $pages): Page
    {
        $childPages = [];

        /** @var $page Page */
        foreach ($pages as $page) {
            if ($page->getPid() === $parentPage->getUid()) {
                $this->fillChildPagesRecursivley($page, $pages);

                $childPages[] = $page;
            }
        }

        \usort($childPages, static function (Page $a, Page $b) {
            if ($a->getSorting() === $b->getSorting()) {
                return 0;
            }

            return ($a->getSorting() < $b->getSorting()) ? -1 : 1;
        });

        $parentPage->setChildPages($childPages);

        return $parentPage;
    }

    protected function getPagination(
        PaginatorInterface $paginator,
        string|null $paginationClass = null,
    ): PaginationInterface {
        if (!empty($paginationClass) && \class_exists($paginationClass)) {
            return GeneralUtility::makeInstance($paginationClass, $paginator);
        }

        return GeneralUtility::makeInstance(SimplePagination::class, $paginator);
    }
}
