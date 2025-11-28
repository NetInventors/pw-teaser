<?php

declare(strict_types=1);

namespace PwTeaserTeam\PwTeaser\Utility;

use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

readonly class Settings
{
    public function __construct(
        private ContentObjectRenderer $contentObject,
        private ConfigurationManager $configurationManager,
    ) {
    }

    public function renderConfigurationArray(array $settings, string $section = 'settings.'): array
    {
        $settings = $this->enhanceSettingsWithTypoScript($this->makeConfigurationArrayRenderable($settings), $section);
        $result   = [];

        foreach ($settings as $key => $value) {
            if (\str_ends_with($key, '.')) {
                $keyWithoutDot = \substr($key, 0, -1);

                if (\array_key_exists($keyWithoutDot, $settings)) {
                    $result[$keyWithoutDot] = $this->contentObject->cObjGetSingle($settings[$keyWithoutDot], $value);
                } else {
                    $result[$keyWithoutDot] = $this->renderConfigurationArray($value);
                }
            } elseif (!\array_key_exists($key . '.', $settings)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected function enhanceSettingsWithTypoScript(
        array $settings,
        string $section = 'settings.',
        string $extKey = 'tx_pwteaser',
    ): array {
        $typoscript = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        $typoscript = $typoscript['plugin.'][$extKey . '.'][$section] ?? [];

        foreach ($settings as $key => $setting) {
            if ($setting === '' && \is_array($typoscript) && \array_key_exists($key, $typoscript)) {
                $settings[$key] = $typoscript[$key];
            }
        }

        return $settings;
    }

    protected function makeConfigurationArrayRenderable(array $configuration): array
    {
        $dottedConfiguration = [];

        foreach ($configuration as $key => $value) {
            if (\is_array($value)) {
                if (\array_key_exists('_typoScriptNodeValue', $value)) {
                    $dottedConfiguration[$key] = $value['_typoScriptNodeValue'];
                }

                $dottedConfiguration[$key . '.'] = $this->makeConfigurationArrayRenderable($value);
            } else {
                $dottedConfiguration[$key] = $value;
            }
        }

        return $dottedConfiguration;
    }
}
