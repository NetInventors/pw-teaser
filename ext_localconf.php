<?php

use PwTeaserTeam\PwTeaser\Controller\TeaserController;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::configurePlugin(
    'pw_teaser',
    'Pi1',
    [ TeaserController::class => 'index' ],
);

$rootLineFields   = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] ?? '', true);
$rootLineFields[] = 'sorting';

$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] = \implode(',', $rootLineFields);
