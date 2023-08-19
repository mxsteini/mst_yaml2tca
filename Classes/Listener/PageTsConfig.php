<?php

declare(strict_types=1);

namespace MST\MstYaml2Tca\Listener;

/*
 * This file is part of TYPO3 CMS-based extension "container" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use MST\MstYaml2Tca\Tca\Registry;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\ModifyLoadedPageTsConfigEvent;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageTsConfig
{

  public function __construct(
    private readonly LoggerInterface $logger
  )
  {
  }


  public function __invoke(ModifyLoadedPageTsConfigEvent $event): void
  {
    $tsConfig = $event->getTsConfig();
    $register = GeneralUtility::makeInstance(Registry::class);
    $files = $register->getFiles();
    $localTsConfig = '';
    foreach ($files as $file) {
      if ($register->getTsConfigStatus($file['filename']) !== true) {
        $localTsConfig .= $this->getPageTsString($file['extKey'], $file['filename']);
        $register->setTsConfigDone($file['filename']);
      }
    }

    $tsConfig = array_merge(['pageTsConfig-package-mst-yaml2tca' => $localTsConfig], $tsConfig);
    $event->setTsConfig($tsConfig);
  }


  public function getPageTsString(string $extKey, string $file): string
  {
    $pageTs = '';

    if (file_exists($file)) {
      $tt_content = (new YamlFileLoader())->load($file);


      foreach (['plugins', 'contentElements'] as $type) {
        if (key_exists($type, $tt_content) && is_array($tt_content[$type])) {
          foreach ($tt_content[$type] as $group => $groupConfigurations) {

            $content = '
mod.wizards.newContentElement.wizardItems.' . $group . '.header = ' . $groupConfigurations['title'] . '
mod.wizards.newContentElement.wizardItems.' . $group . '.show = *
';
            foreach ($groupConfigurations['elements'] as $cType => $elementConfiguration) {
              if ($type === 'plugins') {
                $defaultValues = [
                  'CType' => 'list',
                  'list_type' => mb_strtolower(GeneralUtility::underscoredToUpperCamelCase($extKey)) . '_' . $cType
                ];
                if (key_exists('defaultValues', $elementConfiguration)) {
                  $elementConfiguration['defaultValues'] = array_replace_recursive($defaultValues, $elementConfiguration['defaultValues']);
                } else {
                  $elementConfiguration['defaultValues'] = $defaultValues;
                }
              }

              if (isset($elementConfiguration['defaultValues']) && is_array($elementConfiguration['defaultValues'])) {
                array_walk($elementConfiguration['defaultValues'], static function (&$item, $key) {
                  $item = $key . ' = ' . $item;
                });
                $ttContentDefValues = 'CType = ' . $cType . LF . implode(LF, $elementConfiguration['defaultValues']);
              } else {
                $ttContentDefValues = 'CType = ' . $cType;
              }

              $content .= 'mod.wizards.newContentElement.wizardItems.' . $group . '.elements {
' . $cType . ' {
    title = ' . $elementConfiguration['title'] . '
    description = ' . ($elementConfiguration['description'] ?? '') . '
    iconIdentifier = ' . ($elementConfiguration['icon'] ?? '') . '
    tt_content_defValues {
    ' . $ttContentDefValues . '
    }
}
}
';
            }
            $pageTs .= LF . $content;
          }
        }
      }
    }
    return $pageTs;
  }
}
