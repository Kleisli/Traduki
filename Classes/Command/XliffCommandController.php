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
     * An example command
     *
     * The comment of this command method is also used for Flow's help screens. The first line should give a very short
     * summary about what the command does. Then, after an empty line, you should explain in more detail what the command
     * does. You might also give some usage example.
     *
     * It is important to document the parameters with param tags, because that information will also appear in the help
     * screen.
     *
     * @param string $packageKey This argument is required
     * @param string $languageCode This argument is optional
     * @return void
     */
    public function exportCommand(string $packageKey, string $languageCode)
    {
        $exportDirectory = $this->exportDirectory.'Xliff/'.$languageCode;
        $filePath = $exportDirectory.'/'.$packageKey.'.xlf';
        Files::createDirectoryRecursively($exportDirectory);

        $xlfWriter = new \XMLWriter();
        $xlfWriter->openUri($filePath);
        $xlfWriter->setIndent(true);
        $xlfWriter->setIndentString('    ');

        $xlfWriter = $this->xliffService->mergePackageTranslations($xlfWriter, $packageKey, $languageCode);

        $xlfWriter->flush();

        $this->outputLine('The Xliff file was written to '. $filePath);
    }


    /**
     * An example command
     *
     * The comment of this command method is also used for Flow's help screens. The first line should give a very short
     * summary about what the command does. Then, after an empty line, you should explain in more detail what the command
     * does. You might also give some usage example.
     *
     * It is important to document the parameters with param tags, because that information will also appear in the help
     * screen.
     *
     * @return void
     */
    public function importCommand()
    {
        $this->xliffService->importPackageTranslations($this->importDirectory);

    }

    /**
     * An example command
     *
     * The comment of this command method is also used for Flow's help screens. The first line should give a very short
     * summary about what the command does. Then, after an empty line, you should explain in more detail what the command
     * does. You might also give some usage example.
     *
     * It is important to document the parameters with param tags, because that information will also appear in the help
     * screen.
     *
     * @param string $packageKey This argument is required
     * @param string $languageCode This argument is optional
     * @return void
     */
    public function updateCommand(string $packageKey, string $languageCode)
    {
        $this->xliffService->updatePackageTranslations($packageKey, $languageCode);

        $this->outputLine('Updated Xliff files in '.$this->xliffService->getTranslationsPath($packageKey, $languageCode));
    }
}
