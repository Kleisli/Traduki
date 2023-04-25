# Traduki
Export/import translations of nodes, xliff files and entities in Neos CMS and Flow applications 

## Configuration Settings
- `sourceLanguage` the default language all the translations are based on
- `export.directory` the directory all exports are written to, default: 'Data/Traduki/Export/'
- `export.workspace` the workspace to read the nodes fron, default: 'live' 
- `export.documentTypeFilter` the filter for document nodes to export, default: 'Neos.Neos:Document'
- `export.contentTypeFilter`  the filter for content nodes to export, default: '!Neos.Neos:Document'
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
  ./flow nodes:export [<options>] <starting point> <source language>

ARGUMENTS:
  --starting-point     The node with which to start the export: as identifier
                       or the path relative to the site node.
  --source-language    The language to use as base for the export.

OPTIONS:
  --target-language    The target language for the translation, optional.
  --filename           Path and filename to the XML file to create.
  --modified-after     
  --ignore-hidden      

DESCRIPTION:
  This command exports a specific node tree including all content into an XML format.
  To filter Document or Content nodeTypes to be exported, use the settings
  - Kleisli.Traduki.export.documentTypeFilter
  - Kleisli.Traduki.export.contentTypeFilter

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
```
USAGE:
  ./flow xliff:export <target language> <package key>

ARGUMENTS:
  --target-language    The target language for the translation. e.g. fr
  --package-key        e.g. Vendor.Package

DESCRIPTION:
  The source language is taken from the setting Kleisli.Traduki.sourceLanguage

```
#### xliff:import
```
USAGE:
  ./flow xliff:import [<options>]

OPTIONS:
  --target-language    The target language for the translation. e.g. fr
  --package-key        e.g. Vendor.Package

DESCRIPTION:
  By default, all the files in the subfolder "Entities" in the import directory are imported. This
  can be restricted to a single targetLanguage and package

```
#### xliff:update
```
USAGE:
  ./flow xliff:update [<options>]

OPTIONS:
  --target-language    The target language for the translation. e.g. fr
  --package-key        e.g. Vendor.Package

DESCRIPTION:
  After merging and exporting the xliff files of a package you can run xliff:update
  to add new translation units (-> state="new") and detect translation units where
  the content of the source language changed (-> state="needs-translation")

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
