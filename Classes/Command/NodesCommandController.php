<?php
namespace Kleisli\Traduki\Command;

/*
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Kleisli\Traduki\Service\ExportService;
use Kleisli\Traduki\Service\ImportService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * The Export Command Controller
 *
 * @Flow\Scope("singleton")
 */
class NodesCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var ExportService
     */
    protected $exportService;

    /**
     * @Flow\Inject
     * @var ImportService
     */
    protected $importService;

    /**
     * Export document and content nodes
     *
     * This command exports a specific node tree including all content into an XML format.
     * To filter Document or Content nodeTypes to be exported, use the settings
     * - Kleisli.Traduki.export.documentTypeFilterPreset
     * - Kleisli.Traduki.export.contentTypeFilterPreset
     * and add your own filter presets
     *
     *
     * @param string $startingPoint The node with which to start the export: as identifier or the path relative to the site node.
     * @param string|null $sourceLanguage overwrite the default source language to use as base for the export.
     * @param string|null $targetLanguage The target language for the translation
     * @param string|null $filename Path and filename to the XML file to create. default will be generated from the starting point node label
     * @param string|null $modifiedAfter export only nodes modified after this date
     * @param boolean $ignoreHidden do not export hidden nodes, default: true
     * @param string $documentFilter preset key of the document type filter, default: default
     * @param string $contentFilter preset key of the content type filter, default: default
     * @return void
     * @throws \Exception
     */
    public function exportCommand(string $startingPoint, string $sourceLanguage = null, string $targetLanguage = null,
                                  string $filename = null, string $modifiedAfter = null, bool $ignoreHidden = true,
                                  string $documentFilter = 'default', string $contentFilter = 'default')
    {
        if ($modifiedAfter !== null) {
            $modifiedAfter = new \DateTime($modifiedAfter);
        }

        $this->exportService->initialize($startingPoint, $sourceLanguage, $targetLanguage, $modifiedAfter, $ignoreHidden,
            $documentFilter, $contentFilter);

        try {
            if ($filename === null) {
                $filename = ($documentFilter != 'default' ? $documentFilter.'_' : '').$this->exportService->getStartingPointNode()->getProperty('uriPathSegment').'.xml';
            }

            $this->exportService->exportToFile('Nodes/'.$targetLanguage.'/'.$filename);
            $this->outputLine('<success>The tree starting at "%s" has been exported to "%s".</success>', [$this->exportService->getStartingPointNode()->getLabel(), $this->exportService->getExportDirectory().'Nodes/'.$targetLanguage.'/'.$filename]);

        } catch (\Exception $exception) {
            $this->outputLine('<error>%s</error>', [$exception->getMessage()]);
        }
        $this->outputLine('Peak memory used: %s', [$this->humanReadableFileSize(memory_get_peak_usage())]);
    }

    /**
     * Import sites content (e.g. nodes:import --filename "acme.com.xml" --workspace "czech-review")
     *
     * This command imports translated content from XML.
     *
     * @param string $filename Path and filename to the XML file to import.
     * @param string|null $targetLanguage The target language for the translation, optional if included in XML.
     * @param string $workspace A workspace to import into, optional but recommended
     */
    public function importCommand(string $filename, string $targetLanguage = null, string $workspace = 'live')
    {
        try {
            $importedLanguage = $this->importService->importFromFile($filename, $workspace, $targetLanguage);
            $this->outputLine('<success>The file "%s" has been imported to language "%s" in workspace "%s".</success>', [$filename, $importedLanguage, $workspace]);
        } catch (\Exception $exception) {
            $this->outputLine('<error>%s</error>', [$exception->getMessage()]);
        }
        $this->outputLine('Peak memory used: %s', [$this->humanReadableFileSize(memory_get_peak_usage())]);
    }

    /**
     * @param $size
     * @return string
     */
    private function humanReadableFileSize($size)
    {
        if ($size >= 1073741824) {
            $fileSize = round($size / 1024 / 1024 / 1024,1) . 'GB';
        } elseif ($size >= 1048576) {
            $fileSize = round($size / 1024 / 1024,1) . 'MB';
        } elseif($size >= 1024) {
            $fileSize = round($size / 1024,1) . 'KB';
        } else {
            $fileSize = $size . ' bytes';
        }
        return $fileSize;
    }
}
