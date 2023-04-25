<?php
namespace Kleisli\Traduki\Command;

/*
 * This file is part of the Kleisli.Traduki package.
 */

use Kleisli\Traduki\Service\XliffService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class XliffCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var XliffService
     */
    protected $xliffService;

    /**
     * @Flow\InjectConfiguration(path = "export.directory")
     * @var string
     */
    protected string $exportDirectory;

    /**
     * @Flow\InjectConfiguration(path = "import.directory")
     * @var string
     */
    protected string $importDirectory;

    /**
     * Merge all xliff files of a package into one file
     *
     * The source language is taken from the setting Kleisli.Traduki.sourceLanguage
     *
     * @param string $targetLanguage The target language for the translation. e.g. fr
     * @param string $packageKey e.g. Vendor.Package
     *
     * @return void
     */
    public function exportCommand(string $targetLanguage, string $packageKey)
    {
        $exportDirectory = $this->exportDirectory.'Xliff/'.$targetLanguage;
        $filePath = $exportDirectory.'/'.$packageKey.'.xlf';
        Files::createDirectoryRecursively($exportDirectory);

        $xlfWriter = new \XMLWriter();
        $xlfWriter->openUri($filePath);
        $xlfWriter->setIndent(true);
        $xlfWriter->setIndentString('    ');

        $xlfWriter = $this->xliffService->mergePackageTranslations($xlfWriter, $packageKey, $targetLanguage);

        $xlfWriter->flush();

        $this->outputLine('The Xliff file was written to '. $filePath);
    }


    /**
     * Split and import a merged xliff files of a package
     *
     * By default, all the files in the subfolder "Entities" in the import directory are imported. This
     * can be restricted to a single targetLanguage and package
     *
     * @param string $targetLanguage The target language for the translation. e.g. fr
     * @param string $packageKey e.g. Vendor.Package
     *
     * @return void
     */
    public function importCommand(string $targetLanguage = '', string $packageKey = '')
    {
        $importDirectory = $this->importDirectory.'Xliff';
        if($targetLanguage){
            $importDirectory .= '/'.$targetLanguage;
        }
        $suffix = ($packageKey != '') ? $packageKey.'.xlf' : '.xlf';

        $this->xliffService->importPackageTranslations($importDirectory, $suffix);

    }

    /**
     * Update already exported xliff files to track changed source language label
     *
     * After merging and exporting the xliff files of a package you can run xliff:update
     * to add new translation units (-> state="new") and detect translation units where
     * the content of the source language changed (-> state="needs-translation")
     *
     * @param string $targetLanguage The target language for the translation. e.g. fr
     * @param string $packageKey e.g. Vendor.Package
     *
     * @return void
     */
    public function updateCommand(string $targetLanguage = '', string $packageKey = '')
    {
        $this->xliffService->updatePackageTranslations($packageKey, $targetLanguage);

        $this->outputLine('Updated Xliff files in '.$this->xliffService->getTranslationsPath($packageKey, $languageCode));
    }
}
