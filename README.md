# Traduki
Export/import translations of nodes, xliff files and entities in Neos CMS and Flow applications 

## Configuration Settings
- `sourceLanguage` the default language all the translations are based on
- `export.directory` the directory all exports are written to, default: 'Data/Traduki/Export/'
- `export.workspace` the workspace to read the nodes fron, default: 'live' 
- `export.documentTypeFilterPresets` filter options for document nodes to be exported, default: 'Neos.Neos:Document'
- `export.contentTypeFilterPresets` filter options for content nodes to de exported, default: '!Neos.Neos:Document'
- `import.directory` the directory all imports will be read from, default: 'Data/Traduki/Import/'


## Export/Import
For each type there is a command controller with an export and an import action. 

Translation strings will be exported into a custom XML structure (for nodes) or xliff files (for Xliff and entities), 
can be sent to translation agencies to translate and the translated strings can be imported back afterwards. 

### Translate Nodes
Compared to [Flownative.Neos.Trados](https://github.com/flownative/neos-trados) on which this is based on, this export
has nested content nodes that respect the structure and order in which they appear in a document. To have the context 
makes it easier for translators to translate the content.

#### nodes:export
```
USAGE:
  ./flow nodes:export [<options>] <starting point>

ARGUMENTS:
  --starting-point     The node with which to start the export: as identifier
                       or the path relative to the site node.

OPTIONS:
  --source-language    overwrite the default source language to use as base for
                       the export.
  --target-language    The target language for the translation
  --filename           Path and filename to the XML file to create. default
                       will be generated from the starting point node label
  --modified-after     export only nodes modified after this date
  --ignore-hidden      do not export hidden nodes, default: true
  --document-filter    preset key of the document type filter, default: default
  --content-filter     preset key of the content type filter, default: default

DESCRIPTION:
  This command exports a specific node tree including all content into an XML format.
  To filter Document or Content nodeTypes to be exported, use the settings
  - Kleisli.Traduki.export.documentTypeFilterPreset
  - Kleisli.Traduki.export.contentTypeFilterPreset
  and add your own filter presets

```

***Skip node properties for translation***

By default all node properties of type `string` will be included in the export. To skip certain properties
they can be defined in the nodeType options.
e.g.
```
'Neos.Neos:Document':
  options:
    Kleisli:
      Traduki:
        properties:
          twitterCardType:
            skip: true
```

#### nodes:import
```
USAGE:
  ./flow nodes:import [<options>] <filename>

ARGUMENTS:
  --filename           Path and filename to the XML file to import.

OPTIONS:
  --target-language    The target language for the translation, optional if
                       included in XML.
  --workspace          A workspace to import into, optional but recommended

DESCRIPTION:
  This command imports translated content from XML.
```

### Translate Xliff
#### xliff:export
Update and Merge all xliff files of a package into one file

```
USAGE:
  ./flow xliff:export <target language> <package key>

ARGUMENTS:
  --target-language    The target language code for the translation. e.g. fr
  --package-key        e.g. Vendor.Package

DESCRIPTION:
  The source language is taken from the setting Kleisli.Traduki.sourceLanguage

```
#### xliff:import
Split and import a merged xliff files of a package
```
USAGE:
  ./flow xliff:import [<options>]

OPTIONS:
  --sub-folder-path    restrict importing to a subfolder path within the
                       Xliff-Import directory
  --file-name-suffix   e.g. "Vendor.Package.xlf", default value is ".xlf

DESCRIPTION:
  By default, all the files in the folder "Xliff" in the configured import directory are imported.
  This can be restricted to a single targetLanguage and package


```
#### xliff:update
Create new and update already existing xliff files in a target language to track changed source language labels

```
USAGE:
  ./flow xliff:update [<options>]

OPTIONS:
  --target-language    The target language for the translation. e.g. fr
  --package-key        e.g. Vendor.Package

DESCRIPTION:
  New translation units in the source language, are added to the target language xliff with state="new" 
  and translation units where the content of the source language changed are attributed with state="needs-translation"

```

### Translate Entities
#### entities:export
```
USAGE:
  ./flow entities:export <target language> <model class>

ARGUMENTS:
  --target-language    The target language for the translation. e.g. fr
  --model-class        'Vendor\Package\Domain\Model\MyModel'

DESCRIPTION:
  Exports properties annotated with Gedmo\Mapping\Annotation\Translatable into a xliff file.
  The default/source language is taken from the setting Kleisli.Traduki.sourceLanguage

```
### entities:import
```
USAGE:
  ./flow entities:import [<options>]

OPTIONS:
  --target-language    The target language for the translation. e.g. fr
  --model-class        'Vendor\Package\Domain\Model\MyModel'

DESCRIPTION:
  Imports properties annotated with Gedmo\Mapping\Annotation\Translatable from a translated
  xliff file, that was previously exported by entities:export.
  
  By default, all the files in the subfolder "Entities" in the import directory are imported. This
  can be restricted to a single targetLanguage and Model
```

## Kudos
Node exporter and importer are based on [Flownative.Neos.Trados](https://github.com/flownative/neos-trados). 
Originally I planned to do a PR to this package, but when I realised it will end in a major rewrite, I decided 
to publish it as a new package and extend it with xliff and entity handling.

The development of this package has significantly been funded by [Profolio](https://www.profolio.ch/) - a digital platform for career choice & career counseling
