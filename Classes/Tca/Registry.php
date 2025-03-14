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

  public function __construct(
    private readonly LoggerInterface $logger
  )
  {
    if (!key_exists('yaml2tca', $GLOBALS['TCA']['tt_content'])) {
      $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad'] = [];
    }
  }

  public function loadFile(string $extKey, string $file): void
  {
    if (!key_exists($file, $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad']) && file_exists($file)) {
      $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad'][$file] = [
        'filename' => $file,
        'tca' => false,
        'tsconfig' => false,
        'extKey' => $extKey
      ];

      $content = GeneralUtility::makeInstance(YamlFileLoader::class)->load($file);
      if (key_exists('contentElements', $content) && is_array($content['contentElements'])) {
        $this->loadContentElements($extKey, $content['contentElements']);
      }
      if (key_exists('plugins', $content) && is_array($content['plugins'])) {
        $this->loadPlugins($extKey, $content['plugins']);
      }
      if (key_exists('container', $content) && is_array($content['container'])) {
        $this->loadContainer($extKey, $content['container']);
      }
      if (key_exists('palettes', $content) && is_array($content['palettes'])) {
        $this->loadPalettes($extKey, $content['palettes']);
      }
      if (key_exists('columns', $content) && is_array($content['columns'])) {
        $this->loadColumns($extKey, $content['columns']);
      }
    }
  }

  private function loadPalettes(string $extKey, array $palettes): void
  {
    foreach ($palettes as $tablename => $palettes) {
      foreach ($palettes as $paletteId => $palette) {
        $GLOBALS['TCA'][$tablename]['palettes'][$paletteId]['label'] = $palette['label'];
        $GLOBALS['TCA'][$tablename]['palettes'][$paletteId]['showitem'] = implode(',', $palette['showitem']);
      }
    }
  }

  private function loadColumns(string $extKey, array $columns): void
  {
    foreach ($columns as $tablename => $columns) {
      ExtensionManagementUtility::addTCAcolumns($tablename, $columns);
    }
  }

  private function makeElements(array $sections): array
  {
    foreach ($sections as &$section) {
      foreach ($section['elements'] as &$element) {
        if (key_exists('config', $element) && is_array($element['config']) && key_exists('showitem', $element['config'])) {
          $element['config']['showitem'] = $this->compileShowItem($element['config']['showitem']);
        } else if (key_exists('config', $element) && is_array($element['config']) && key_exists('showItem', $element['config'])) {
          $element['config']['showitem'] = $this->compileShowItem($element['config']['showItem']);
        }
      }
    }
    return $sections;
  }

  private function compileShowItem(array $element): string
  {
    $divs = [];

    foreach ($element as $div) {
      $fields = implode(',', $div['fields']);
      $divs[] = '--div--;' . $div['title'] . ',' . $fields;
    }
    return implode(',', $divs);
  }

  public function setTsConfigDone(string $file): void
  {
    $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad'][$file]['tsconfig'] = true;
  }

  public function getTsConfigStatus(string $file): bool
  {
    return $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad'][$file]['tsconfig'];
  }

  private function loadContentElements(string $extKey, array $contentElements): void
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
        $extension = key_exists('extension', $section) ? $element['extension'] : $extKey;
        // For TYPO3 v13+, use SelectItem class if available
        if (class_exists(\TYPO3\CMS\Core\Schema\Struct\SelectItem::class)) {
          $localElement = $element;
          $localElement['label'] = $element['title'];
          $localElement['value'] = $ctype;
          $localElement['group'] = $sectionId;
          $selectItem = \TYPO3\CMS\Core\Schema\Struct\SelectItem::fromTcaItemArray($localElement);
        } else {
          $selectItem = [
            $element['title'],
            $ctype,
            $element['icon'] ?? '',
            $sectionId,
          ];
        }
        ExtensionManagementUtility::addTcaSelectItem(
          'tt_content',
          'CType',
          $selectItem
        );

        $GLOBALS['TCA']['tt_content']['types'][$ctype] = $element['config'];
        $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][$ctype] = $element['icon'] ?? '';

        $possibleFlexForm = 'EXT:' . $extension . '/Configuration/FlexForms/ContentElements/' . GeneralUtility::underscoredToUpperCamelCase($ctype) . '.xml';

        if (key_exists('flexform', $element)) {
          $possibleFlexForm = $element['flexform'];
        }

        if (file_exists(GeneralUtility::getFileAbsFileName($possibleFlexForm))) {
          $extensionSignature = mb_strtolower(GeneralUtility::underscoredToUpperCamelCase($extension));
          $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$extensionSignature . '_' . $ctype] = 'layout,pages,select_key,recursive';
          $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$extensionSignature . '_' . $ctype] = 'pi_flexform';
          ExtensionManagementUtility::addPiFlexFormValue(
            '*',
            'FILE:' . $possibleFlexForm,
            $ctype
          );
        }

      }
    }
  }

  private function loadPlugins(string $extKey, array $plugins): void
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

        $possibleFlexForm = 'EXT:' . $extension . '/Configuration/FlexForms/Plugins/' . GeneralUtility::underscoredToUpperCamelCase($plugin) . '.xml';
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

  private function loadContainer(string $extKey, array $containers): void
  {
    foreach ($containers as $ctype => $container) {
      $extension = key_exists('extension', $container) ? $container['extension'] : $extKey;
      $plugin = key_exists('plugin', $container) ? $element['plugin'] : $ctype;

      $registry = GeneralUtility::makeInstance(ContainerRegistry::class);
      $containerConfiguration = new ContainerConfiguration($ctype, $container['label'], $container['description'], $container['config']);
      $containerConfiguration->setIcon($container['iconIdentifier']);
      $registry->configureContainer($containerConfiguration);
      $possibleFlexForm = 'EXT:' . $extension . '/Configuration/FlexForms/Containers/' . GeneralUtility::underscoredToUpperCamelCase($ctype) . '.xml';
      if (key_exists('flexform', $container)) {
        $possibleFlexForm = $container['flexform'];
      }

      if (file_exists(GeneralUtility::getFileAbsFileName($possibleFlexForm))) {
        $extensionSignature = mb_strtolower(GeneralUtility::underscoredToUpperCamelCase($extension));
        ExtensionManagementUtility::addToAllTCAtypes(
          'tt_content',
          'pi_flexform',
          $ctype,
          'after:header'
        );

        ExtensionManagementUtility::addPiFlexFormValue(
          '*',
          'FILE:' . $possibleFlexForm,
          $ctype
        );
      }
      if (key_exists('showItem', $container)) {
        $GLOBALS['TCA']['tt_content']['types'][$ctype]['showitem'] = $this->compileShowItem($container['showItem']);
      }
    }
  }

  public function getFiles(): array
  {
    return $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad'];
  }

  public function collectFilesFromExtensions(): array
  {
    return [];
  }
}
