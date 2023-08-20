# EXT:mst_yaml2tca - Load simple TCA-Elements from yaml

## Situation
Writing TCA is always exhausting. Especially if you want to have the element in the New Content Element Wizard.
With this extension it is possible to put simple elements in a YAML file.

Currently "normal" content elements, plugins and containers can be loaded from YAML files.

When loading, it is still being tested whether it is possible to load a Flexform.
For this purpose, a file with the name of the element (in UpperCamelCase) can simply be placed in the Configuration/FlexForms directory.
Alternatively, a file can also be stored in the flexform field.

The yaml file has a section for each of the different content types.
Plugins and content elements can each be assigned to a group.
Containers are always automatically sorted into the Container groups.
In EXT:mst_yaml2tca/Resources/Private/Yaml/Elements.yaml is an example for such a file

Unlike "real" TCA, the section "showItem" is defined as an array. Each entry corresponds to a tab.

## Usage
To load a yaml file simply insert in:
Configuration/TCA/Overrides/tt_content.php

```php
  $register = GeneralUtility::makeInstance(\MST\MstYaml2Tca\Tca\Registry::class);
  $register->loadFile($extKey, GeneralUtility::getFileAbsFileName('EXT:[yourextension]/Configuration/Yaml/Elements.yaml'));
```

