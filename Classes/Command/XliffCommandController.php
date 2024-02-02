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

    #[Flow\Inject]
    protected XliffService $xliffService;

    #[Flow\InjectConfiguration(path: "export.directory")]
    protected string $exportDirectory;

    #[Flow\InjectConfiguration(path: "import.directory")]
    protected string $importDirectory;

    /**
     * Update and Merge all xliff files of a package into one file
     *
     * The source language is taken from the setting Kleisli.Traduki.sourceLanguage
     *
     * @param string $targetLanguage The target language code for the translation. e.g. fr
     * @param string $packageKey e.g. Vendor.Package
     *
     * @return void
     */
    public function exportCommand(string $targetLanguage, string $packageKey): void
    {
        $this->updateCommand($targetLanguage, $packageKey);

        $exportDirectory = $this->exportDirectory.'Xliff/'.str_replace('-', '/', $targetLanguage);
        $filePath = $exportDirectory.'/'.$packageKey.'.xlf';
        Files::createDirectoryRecursively($exportDirectory);

        $xlfWriter = new \XMLWriter();
        $xlfWriter->openUri($filePath);
        $xlfWriter->setIndent(true);
        $xlfWriter->setIndentString('    ');

        $xlfWriter = $this->xliffService->mergePackageTranslations($xlfWriter, $packageKey, $targetLanguage);

        $xlfWriter->flush();

        $this->outputLine('The merged Xliff file was written to '. $filePath);
    }


    /**
     * Split and import a merged xliff files of a package
     *
     * By default, all the files in the folder "Xliff" in the configured import directory are imported.
     * This can be restricted to a single targetLanguage and package
     *
     * @param string|null $subFolderPath restrict importing to a subfolder path within the Xliff-Import directory
     * @param string $fileNameSuffix e.g. "Vendor.Package.xlf", default value is ".xlf"
     *
     * @return void
     */
    public function importCommand(?string $subFolderPath = null, string $fileNameSuffix = '.xlf'): void
    {
        $importDirectory = $this->importDirectory.'Xliff';
        if($subFolderPath){
            $importDirectory .= '/'.$subFolderPath;
        }

        $this->xliffService->importPackageTranslations($importDirectory, $fileNameSuffix);

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
    public function updateCommand(string $targetLanguage = '', string $packageKey = ''): void
    {
        $this->xliffService->updatePackageTranslations($packageKey, $targetLanguage);

        $this->outputLine('Updated Xliff files in '.$this->xliffService->getTranslationsPath($packageKey, str_replace('-', '/', $targetLanguage)));
    }
}
