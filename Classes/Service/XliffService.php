<?php

namespace Kleisli\Traduki\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Xliff\Service\XliffReader;
use Neos\Flow\Package\PackageManager;
use Neos\Utility\Files;
use Neos\Flow\I18n\Service as LocalizationService;

/**
 *
 * @Flow\Scope("singleton")
 */
class XliffService
{

    #[Flow\InjectConfiguration(path: "sourceLanguage")]
    protected string $sourceLanguage;

    /**
     * A relative path for translations inside the package resources.
     */
    protected string $xliffBasePath = 'Private/Translations/';

    #[Flow\Inject]
    protected XliffReader $xliffReader;

    #[Flow\Inject]
    protected LocalizationService $localizationService;

    #[Flow\Inject]
    protected PackageManager $packageManager;


    /**
     *
     * @param \XMLWriter $xmlWriter
     * @param string $packageKey 'MyVendor.PackageName'
     * @param string $languageCode like 'en', 'de' or 'fr'
     * @return \XMLWriter
     */
    public function mergePackageTranslations(\XMLWriter $xmlWriter, $packageKey, $languageCode): \XMLWriter
    {
        $xmlWriter->startDocument('1.0', 'utf-8');
        $xmlWriter->startElement('xliff');
        $xmlWriter->writeAttribute('version', '1.2');
        $xmlWriter->writeAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');

        $package = $this->packageManager->getPackage($packageKey);
        $sources = [];
        $translationPath = $package->getResourcesPath() . $this->xliffBasePath;
        $languageFolder = str_replace('-', '/', $languageCode);

        if (!is_dir($translationPath. $languageFolder)) {
            $xmlWriter->endElement(); // xliff
            $xmlWriter->endDocument();
            return $xmlWriter;
        }

        foreach (Files::readDirectoryRecursively($translationPath. $languageFolder, '.xlf') as $filePath) {
            //remove translation path from path
            $source = trim(str_replace($translationPath, '', $filePath), '/');
            //remove language part from path
            $source = trim(substr($source, strpos($source, '/')), '/');
            //remove file extension
            $source = substr($source, 0, strrpos($source, '.'));

            $this->xliffReader->readFiles(
                $filePath,
                function (\XMLReader $xmlReader, $offset, $version) use ($packageKey, &$xmlWriter, $source, $languageCode) {
                    $targetPackageKey = $packageKey;
                    if ($version === '1.2') {
                        //in xliff v1.2 the packageKey or source can be overwritten via attributes
                        $targetPackageKey = $xmlReader->getAttribute('product-name') ?: $packageKey;
                        $source = $xmlReader->getAttribute('original') ?: $source;
                    }
                    if ($packageKey !== $targetPackageKey) {
                        return;
                    }
                    $xmlWriter->startElement('file');
                    $xmlWriter->writeAttribute('datatype', 'plaintext');
                    $xmlWriter->writeAttribute('product-name', $targetPackageKey);
                    $xmlWriter->writeAttribute('original', $source);
                    $xmlWriter->writeAttribute('source-language', $xmlReader->getAttribute('source-language') ?: $this->sourceLanguage);
                    $xmlWriter->writeAttribute('target-language', $xmlReader->getAttribute('target-language') ?: $languageCode);

                    $xmlWriter->writeRaw($xmlReader->readInnerXml());

                    $xmlWriter->endElement(); // file
                }
            );
        }

        $xmlWriter->endElement(); // xliff
        $xmlWriter->endDocument();
        return $xmlWriter;
    }

    /**
     *
     * @param string $packageKey 'MyVendor.PackageName'
     * @param string $languageCode like 'en', 'de' or 'fr'
     * @return void
     */
    public function updatePackageTranslations($packageKey, $languageCode)
    {
        $sourcePath = $this->getTranslationsPath($packageKey, $this->sourceLanguage);
        $translationPath = $this->getTranslationsPath($packageKey);

        $targetLanguageFolder = str_replace('-', '/', $languageCode);
        Files::createDirectoryRecursively($translationPath .$targetLanguageFolder);

        foreach (Files::readDirectoryRecursively($sourcePath, '.xlf') as $sourceFilePath) {

            $targetFilePath = str_replace('/'.$this->sourceLanguage.'/', '/'.$targetLanguageFolder.'/', $sourceFilePath);
            Files::createDirectoryRecursively(pathinfo($targetFilePath, PATHINFO_DIRNAME));

            if(!is_file($targetFilePath)){

                $targetXliff = new \DOMDocument();
                $targetXliff->load($sourceFilePath);
                $targetXliff->preserveWhiteSpace = false;
                $targetXliff->formatOutput = true;

                $targetLanguageAttribute = new \DOMAttr('target-language', $languageCode);

                $fileElements = $targetXliff->getElementsByTagName('file');
                /** @var \DOMNode $fileElement */
                foreach ($fileElements as $fileElement) {
                    $fileElement->appendChild($targetLanguageAttribute);
                }

                $transUnitElements = $targetXliff->getElementsByTagName('trans-unit');
                /** @var \DOMNode $transUnitElement */
                foreach ($transUnitElements as $transUnitElement) {
                    $target = new \DOMElement('target');
                    $transUnitElement->appendChild($target);
                    $target->setAttribute('state', 'new');
                }

                $targetXliff->save($targetFilePath);
            }else{
                $sourceXliffArray = $this->getXliffAsArray($sourceFilePath);
                $targetXliffArray = $this->getXliffAsArray($targetFilePath);

                $xlfWriter = new \XMLWriter();
                $xlfWriter->openUri($targetFilePath);
                $xlfWriter->setIndent(true);
                $xlfWriter->setIndentString('    ');
                $xlfWriter->startDocument('1.0', 'utf-8');
                $xlfWriter->startElement('xliff');
                $xlfWriter->writeAttribute('version', '1.2');
                $xlfWriter->writeAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');

                $xlfWriter->startElement('file');
                $xlfWriter->writeAttribute('original', $this->getOriginalString($translationPath, $sourceFilePath));
                $xlfWriter->writeAttribute('product-name', $packageKey);
                $xlfWriter->writeAttribute('source-language', $this->sourceLanguage);
                $xlfWriter->writeAttribute('datatype', 'plaintext');

                if($languageCode != $this->sourceLanguage) {
                    $xlfWriter->writeAttribute('target-language', $languageCode);
                }

                $xlfWriter->startElement('body');

                foreach ($sourceXliffArray as $id => $translation) {

                    $xlfWriter->startElement('trans-unit');
                    $xlfWriter->writeAttribute('id', $id);
                    $xlfWriter->writeAttribute('xml:space', "preserve");

                    if(!isset($translation['source'])){
                        $translation['source'] = $translation['target'];
                    }

                    $xlfWriter->startElement('source');
                    if (str_contains($translation['source'], '<')) {
                        $xlfWriter->writeCdata($translation['source']);
                    } else {
                        $xlfWriter->text($translation['source']);
                    }
                    $xlfWriter->endElement(); // source

                    if($languageCode != $this->sourceLanguage) {
                        $xlfWriter->startElement('target');
                        if (!isset($targetXliffArray[$id])) {
                            $xlfWriter->writeAttribute('state', 'new');
                        } else {
                            if ($targetXliffArray[$id]['source'] != $translation['source']) {
                                $xlfWriter->writeAttribute('state', 'needs-translation');
                            } else {
                                if (isset($targetXliffArray[$id]['state'])) {
                                    $xlfWriter->writeAttribute('state', $targetXliffArray[$id]['state']);
                                }
                            }
                            if(str_contains($targetXliffArray[$id]['target'], '<')){
                                $xlfWriter->writeCdata($targetXliffArray[$id]['target']);
                            }else{
                                $xlfWriter->text($targetXliffArray[$id]['target']);
                            }
                        }
                        $xlfWriter->fullEndElement(); // target
                    }

                    $xlfWriter->endElement(); // trans-unit
                }

                $xlfWriter->endElement(); // body
                $xlfWriter->endElement(); // file
                $xlfWriter->endElement(); // xliff

                //$xlfWriter->flush();
            }
        }
    }


    /**
     *
     *
     * @param string $importDirectory the directory from where to import the xlf files
     * @param string $suffix file suffix
     *
     * @return void
     */
    public function importPackageTranslations(string $importDirectory, string $suffix = '.xlf'): void
    {
        foreach (Files::readDirectoryRecursively($importDirectory, $suffix) as $importFilePath) {
            $importDocument = new \DOMDocument();
            $importDocument->load($importFilePath);
            $fileElements = $importDocument->getElementsByTagName('file');
            /** @var \DOMElement $fileElement */
            foreach ($fileElements as $fileElement) {
                $packageKey =  $fileElement->getAttribute('product-name');
                $filePath = $fileElement->getAttribute('original');
                $targetLanguage = $fileElement->getAttribute('target-language');

                $package = $this->packageManager->getPackage($packageKey);
                $targetFilePath = $package->getResourcesPath() . $this->xliffBasePath . str_replace(['-', '_'], '/', $targetLanguage) . '/' . $filePath . '.xlf';

                $targetDirectory = pathinfo($targetFilePath, PATHINFO_DIRNAME);
                // create directory if not exists
                Files::createDirectoryRecursively($targetDirectory);

                $xlfWriter = new \XMLWriter();
                $xlfWriter->openUri($targetFilePath);
                $xlfWriter->setIndent(true);
                $xlfWriter->setIndentString('    ');
                $xlfWriter->startDocument('1.0', 'utf-8');
                $xlfWriter->startElement('xliff');
                $xlfWriter->writeAttribute('version', '1.2');
                $xlfWriter->writeAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');

                $xlfWriter->writeRaw("\n    ");
                $xlfWriter->writeRaw(simplexml_import_dom($fileElement)->asXML());
                $xlfWriter->writeRaw("\n");

                $xlfWriter->endElement();

            }
        }
    }

    /**
     * @param $filePath
     * @return array
     */
    public function getXliffAsArray($filePath){

        $xliffArray = [];

        $xliff = new \DOMDocument();
        $xliff->load($filePath);

        $transUnitElements = $xliff->getElementsByTagName('trans-unit');
        /** @var \DOMElement $transUnitElement */
        foreach ($transUnitElements as $transUnitElement) {
            $id = $transUnitElement->getAttribute('id');
            if($transUnitElement->getElementsByTagName('source')->item(0)) {
                $xliffArray[$id]['source'] = $transUnitElement->getElementsByTagName('source')->item(0)->nodeValue;
            }
            /** @var \DOMElement $targetElement */
            $targetElement = $transUnitElement->getElementsByTagName('target')->item(0);
            if($targetElement) {
                $xliffArray[$id]['target'] = $targetElement->nodeValue;
                $xliffArray[$id]['state'] = $targetElement->getAttribute('state');
            }
        }

        return $xliffArray;
    }

    /**
     * @param string $packageKey 'MyVendor.PackageName'
     * @param string $languageCode like 'en', 'de' or 'fr'
     * @return string
     * @throws \Neos\Flow\Package\Exception\UnknownPackageException
     */
    public function getTranslationsPath($packageKey, $languageCode = ''){
        $package = $this->packageManager->getPackage($packageKey);
        return $package->getResourcesPath() . $this->xliffBasePath . str_replace('-', '/', $languageCode);
    }

    /**
     * @param string $translationPath
     * @param string $filePath
     * @return string
     * @throws \Neos\Flow\Package\Exception\UnknownPackageException
     */
    protected function getOriginalString(string $translationPath, string $filePath): string {

        //remove translation path from path
        $original = trim(str_replace($translationPath, '', $filePath), '/');
        //remove language part from path
        $original = trim(substr($original, strpos($original, '/')), '/');
        //remove file extension
        return substr($original, 0, strrpos($original, '.'));
}


}
