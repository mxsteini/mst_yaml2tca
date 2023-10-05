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

  public function loadFile(string $extKey, string $file)
  {
    if (!key_exists($file, $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad']) && file_exists($file)) {
      $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad'][$file] = [
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
        $this->loadContainer($extKey, $content['container']);
      }
    }
  }

  private function makeElements($sections)
  {
    foreach ($sections as &$section) {
      foreach ($section['elements'] as &$element) {
        if (key_exists('config', $element) && is_array($element['config']) && key_exists('showItem', $element['config'])) {
          $element['config']['showitem'] = $this->compileShowItem($element['config']['showItem']);
        }
      }
    }
    return $sections;
  }

  private function compileShowItem($element)
  {
    $divs = [];

    foreach ($element as $div) {
      $fields = implode(',', $div['fields']);
      $divs[] = '--div--;' . $div['title'] . ',' . $fields;
    }
    return implode(',', $divs);
  }

  public function setTsConfigDone($file)
  {
    $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad'][$file]['tsconfig'] = true;
  }

  public function getTsConfigStatus($file)
  {
    return $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad'][$file]['tsconfig'];
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
        $extension = key_exists('extension', $section) ? $element['extension'] : $extKey;
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

        $possibleFlexForm = 'EXT:' . $extension . '/Configuration/FlexForms/' . GeneralUtility::underscoredToUpperCamelCase($ctype) . '.xml';

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

  private function loadContainer(string $extKey, array $containers)
  {
    foreach ($containers as $ctype => $container) {
      $extension = key_exists('extension', $container) ? $container['extension'] : $extKey;
      $plugin = key_exists('plugin', $container) ? $element['plugin'] : $ctype;

      $registry = GeneralUtility::makeInstance(ContainerRegistry::class);
      $containerConfiguration = new ContainerConfiguration($ctype, $container['label'], $container['description'], $container['config']);
      $containerConfiguration->setIcon($container['iconIdentifier']);
      $registry->configureContainer($containerConfiguration);
      $possibleFlexForm = 'EXT:' . $extension . '/Configuration/FlexForms/' . GeneralUtility::underscoredToUpperCamelCase($ctype) . '.xml';
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

  public function getFiles()
  {
    return $GLOBALS['TCA']['tt_content']['yaml2tca']['filesToLoad'];
  }

  public function collectFilesFromExtensions()
  {
    return [];
  }
}
