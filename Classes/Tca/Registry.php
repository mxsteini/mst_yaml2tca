<?php

declare(strict_types=1);

namespace MST\MstYaml2Tca\Tca;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use B13\Container\Tca\ContainerConfiguration;
use B13\Container\Tca\Registry as ContainerRegistry;

class Registry implements SingletonInterface
{

  private $extensionToScan = [];
  private $filesToLoad = [];

  public function __construct(
    private readonly LoggerInterface $logger
  )
  {
  }

  public function loadExtension(string $extKey)
  {
    $this->extensionToScan[] = $extKey;
  }

  public function loadFile(string $extKey, string $file)
  {
    if (!key_exists($file, $this->filesToLoad) && file_exists($file)) {
      $this->filesToLoad[$file] = [
        'filename' => $file,
        'tca' => false,
        'tsconfig' => false,
        'extKey' => $extKey
      ];

      $content = (new YamlFileLoader())->load($file);
      if (key_exists('contentElements', $content) && is_array($content['contentElements'])) {
        $this->loadContentElements($extKey, $content['contentElements']);
      }
      if (key_exists('plugins', $content) && is_array($content['plugins'])) {
        $this->loadPlugins($extKey, $content['plugins']);
      }
      if (key_exists('container', $content) && is_array($content['container'])) {
        $this->loadContainer($content['container']);
      }
    }
  }

  private function makeElements($sections)
  {
    foreach ($sections as &$section) {
      foreach ($section['elements'] as &$element) {
        $divs = [];
        if (key_exists('config', $element) && is_array($element['config']) && key_exists('showItem', $element['config'])) {
          foreach ($element['config']['showItem'] as $div) {
            $fields = implode(',', $div['fields']);
            $divs[] = '--div--;' . $div['title'] . ',' . $fields;
          }
          $element['config']['showitem'] = implode(',', $divs);
        }
      }
    }
    return $sections;
  }

  public function setTsConfigDone($file)
  {
    $this->filesToLoad[$file]['tsconfig'] = true;
  }

  public function getTsConfigStatus($file)
  {
    return $this->filesToLoad[$file]['tsconfig'];
  }

  private function loadContentElements(string $extKey, array $contentElements)
  {
    $sections = $this->makeElements($contentElements);
    foreach ($sections as $sectionId => $section) {
      ExtensionManagementUtility::addTcaSelectItemGroup(
        'tt_content',
        'CType',
        $sectionId,
        $section['title'],
        $section['position'] ?? 'bottom');
      foreach ($section['elements'] as $ctype => $element) {
        ExtensionManagementUtility::addTcaSelectItem(
          'tt_content',
          'CType',
          [
            $element['title'],
            $ctype,
            $element['icon'] ?? '',
            $sectionId,
          ],
        );

        $GLOBALS['TCA']['tt_content']['types'][$ctype] = $element['config'];
        $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][$ctype] = $element['icon'] ?? '';

      }
    }
  }

  private function loadPlugins(string $extKey, array $plugins)
  {
    $sections = $this->makeElements($plugins);

    foreach ($sections as $sectionId => $section) {
      ExtensionManagementUtility::addTcaSelectItemGroup(
        'tt_content',
        'CType',
        $sectionId,
        $section['title'],
        $section['position'] ?? 'bottom');
      foreach ($section['elements'] as $elementId => $element) {
        $plugin = key_exists('plugin', $element) ? $element['plugin'] : $elementId;
        $extension = key_exists('extension', $element) ? $element['extension'] : $extKey;
        ExtensionUtility::registerPlugin(
          $extension,
          $plugin,
          $element['title'],
          $element['icon'] ?? null
        );

        $possibleFlexForm = 'EXT:' . $extension . '/Configuration/FlexForms/' . GeneralUtility::underscoredToUpperCamelCase($plugin) . '.xml';
        if (key_exists('flexform', $element)) {
          $possibleFlexForm = $element['flexform'];
        }

        if (file_exists(GeneralUtility::getFileAbsFileName($possibleFlexForm))) {
          $extensionSignature = mb_strtolower(GeneralUtility::underscoredToUpperCamelCase($extension));
          $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$extensionSignature . '_' . $plugin] = 'layout,pages,select_key,recursive';
          $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$extensionSignature . '_' . $plugin] = 'pi_flexform';
          ExtensionManagementUtility::addPiFlexFormValue(
            $extensionSignature . '_' . $plugin,
            'FILE:' . $possibleFlexForm
          );
        }
      }
    }
  }

  private function loadContainer(array $containers)
  {
    foreach ($containers as $ctype => $container) {
      $registry = GeneralUtility::makeInstance(ContainerRegistry::class);
      $containerConfiguration = new ContainerConfiguration($ctype, $container['label'], $container['description'], $container['config']);
      $containerConfiguration->setIcon($container['iconIdentifier']);
      $registry->configureContainer($containerConfiguration);
    }
  }

  public function getFiles()
  {
    return $this->filesToLoad;
  }

  public function collectFilesFromExtensions()
  {
    return [];
  }
}
