<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\UserFunction;

use GuzzleHttp\Psr7\ServerRequest;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;

readonly class ItemsProcFunc
{
    public function __construct(
        private BackendConfigurationManager $configurationManager,
    ) {
    }

    public function getAvailableTemplatePresets(array &$parameters): void
    {
        $pid = (int) ($parameters['row']['pid'] ?? 0);

        if (
            $pid <= 0
            && isset($parameters['effectivePid'])
            && MathUtility::canBeInterpretedAsInteger($parameters['effectivePid'])
        ) {
            $pid = (int) $parameters['effectivePid'];
        }

        if ($pid <= 0) {
            return;
        }

        $request = (new ServerRequest('GET', '/'))->withQueryParams([ 'id' => $pid ]);
        $config  = $this->configurationManager->getTypoScriptSetup($request);
        $presets = $config['plugin.']['tx_pwteaser.']['view.']['presets.'] ?? [];

        foreach ($presets as $key => $preset) {
            $parameters['items'][] = [$preset['label'], \rtrim($key, '.')];
        }
    }
}
