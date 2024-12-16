.. include:: /Includes.rst.txt

=============
Configuration
=============

Basic Setup
==========

To use the extension, you need to load your YAML configuration file in your
extension's TCA override file:

.. code-block:: php

   // Configuration/TCA/Overrides/tt_content.php
   
   $register = GeneralUtility::makeInstance(\MST\MstYaml2Tca\Tca\Registry::class);
   $register->loadFile(
       $extKey,
       GeneralUtility::getFileAbsFileName('EXT:[yourextension]/Configuration/Yaml/Elements.yaml')
   );

YAML Configuration
================

The YAML configuration file is structured into different sections for each type of TCA element.
Here's an example configuration:

Columns
-------

Define custom columns for tables:

.. code-block:: yaml

   columns:
     tt_content:
       color:
         label: 'Color'
         config:
           type: color
           valuePicker:
             items:
               - ['Red', '#FF0000']
               - ['Blue', '#0000FF']

Palettes
--------

Configure palettes to group fields:

.. code-block:: yaml

   palettes:
     tt_content:
       small_header:
         label: 'Small Header'
         showitem:
           - header
           - subheader
           - --linebreak--
           - header_layout

Content Elements
--------------

Define new content elements:

.. code-block:: yaml

   contentElements:
     new_mst_site_basic_elements:
       title: "Basic Elements"
       elements:
         new_mst_site_text:
           title: "Text Element"
           description: "A simple text element"
           icon: "content-text"
           config:
             showitem:
               - title: "General"
                 fields:
                   - "--palette--;;general"
                   - bodytext

Plugins
-------

Configure plugins:

.. code-block:: yaml

   plugins:
     my_plugin_group:
       title: "My Plugins"
       elements:
         my_plugin:
           title: "My Plugin"
           description: "Plugin description"
           iconIdentifier: "content-plugin"

Containers
---------

Set up container elements:

.. code-block:: yaml

   container:
     col3:
       label: '3 Columns'
       description: 'Three column container'
       iconIdentifier: content-container-columns-3
       config:
         -
           -
             name: "left"
             colPos: 1000
           -
             name: "main"
             colPos: 1001
           -
             name: "right"
             colPos: 1002

FlexForm Integration
==================

The extension automatically checks for FlexForm configurations in the following locations:

* ``Configuration/FlexForms/ContentElements/[ElementName].xml``
* ``Configuration/FlexForms/Plugins/[PluginName].xml``
* ``Configuration/FlexForms/Containers/[ContainerName].xml``

You can also specify a custom FlexForm location in your YAML configuration:

.. code-block:: yaml

   contentElements:
     my_group:
       elements:
         my_element:
           flexform: 'EXT:my_extension/Configuration/FlexForms/Custom.xml' 