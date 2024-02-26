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
use Neos\Flow\Exception;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Persistence\RepositoryInterface;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Utility\Files;
use Neos\Utility\ObjectAccess;

/**
 * The Export Command Controller
 *
 * @Flow\Scope("singleton")
 */
class EntitiesCommandController extends CommandController
{

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
     * @Flow\InjectConfiguration(path = "sourceLanguage")
     * @var string
     */
    protected string $sourceLanguage;

    /**
     * @Flow\Inject
     */
    protected ReflectionService $reflectionService;

    /**
     * @Flow\Inject
     */
    protected \Milly\Tools\Service\ClassMappingService $classMappingService;

    /**
     * @Flow\Inject
     */
    protected PersistenceManagerInterface $persistenceManager;

    /**
     * export Gedmo\Translatable entities to xliff
     *
     * Exports properties annotated with Gedmo\Mapping\Annotation\Translatable into a xliff file.
     * The default/source language is taken from the setting Kleisli.Traduki.sourceLanguage
     *
     * @param string $targetLanguage The target language for the translation. e.g. fr
     * @param string $modelClass 'Vendor\Package\Domain\Model\MyModel'
     *
     * @return void
     * @throws \Exception
     */
    public function exportCommand(string $targetLanguage, string $modelClass)
    {
        $exportDirectory = $this->exportDirectory.'Entities/'.$targetLanguage;
        $filePath = $exportDirectory.'/'.$this->classToFilename($modelClass).'.xlf';
        Files::createDirectoryRecursively($exportDirectory);

        $xlfWriter = new \XMLWriter();
        $xlfWriter->openUri($filePath);
        $xlfWriter->setIndent(true);
        $xlfWriter->setIndentString('    ');

        $propertyNames = $this->reflectionService->getPropertyNamesByAnnotation($modelClass, 'Gedmo\Mapping\Annotation\Translatable');
        $repository = $this->objectManager->get($this->classMappingService->getRepositoryClassByModel($modelClass));
        $all = $repository->findAll();

        $xlfWriter->startDocument('1.0', 'utf-8');
        $xlfWriter->startElement('xliff');
        $xlfWriter->writeAttribute('version', '1.2');
        $xlfWriter->writeAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');

        foreach ($all as $entity){
            $targetLanguageAttribute = new \DOMAttr('target-language', $targetLanguage);
            $xlfWriter->startElement('file');
            $xlfWriter->writeAttribute('product-name', $modelClass);
            $xlfWriter->writeAttribute('original', $this->persistenceManager->getIdentifierByObject($entity));
            $xlfWriter->writeAttribute('source-language', $this->sourceLanguage);

            if($targetLanguage != $this->sourceLanguage) {
                $xlfWriter->writeAttribute('target-language', $targetLanguage);
            }

            $xlfWriter->writeAttribute('datatype', 'plaintext');

            $xlfWriter->startElement('body');

            foreach ($propertyNames as $propertyName) {
                $xlfWriter->startElement('trans-unit');
                $xlfWriter->writeAttribute('id', $propertyName);
                $xlfWriter->writeAttribute('xml:space', "preserve");

                $xlfWriter->startElement('source');
                $xlfWriter->writeCdata(ObjectAccess::getProperty($entity, $propertyName));
                $xlfWriter->endElement(); // source

                if($targetLanguage != $this->sourceLanguage) {
                    $xlfWriter->startElement('target');
                    $propertyValue = $entity->getPropertyInLocale($propertyName, $targetLanguage);
                    if($propertyValue){
                        $xlfWriter->writeAttribute('state', 'translated');
                    }else{
                        $xlfWriter->writeAttribute('state', 'new');
                    }
                    $xlfWriter->writeCdata($propertyValue);

                    $xlfWriter->fullEndElement(); // target
                }
                $xlfWriter->endElement(); // trans-unit
            }
            $xlfWriter->endElement(); // body
            $xlfWriter->endElement(); // file
        }
        $xlfWriter->endElement(); // xliff

        $xlfWriter->flush();

        $this->outputLine('The Xliff file was written to '. $filePath);

    }

    /**
     * import Gedmo\Translatable entities from xliff
     *
     * Imports properties annotated with Gedmo\Mapping\Annotation\Translatable from a translated
     * xliff file, that was previously exported by entities:export.
     *
     * By default, all the files in the subfolder "Entities" in the import directory are imported. This
     * can be restricted to a single targetLanguage and Model
     *
     * @param string $targetLanguage The target language for the translation. e.g. fr
     * @param string $modelClass 'Vendor\Package\Domain\Model\MyModel'
     *
     * @return void
     */
    public function importCommand(string $targetLanguage = '', string $modelClass = '')
    {
        $importDirectory = $this->importDirectory.'Entities';
        if($targetLanguage){
            $importDirectory .= '/'.$targetLanguage;
        }
        $suffix = ($modelClass != '') ? $this->classToFilename($modelClass).'.xlf' : '.xlf';

        foreach (Files::readDirectoryRecursively($importDirectory, $suffix) as $importFilePath) {
            $importDocument = new \DOMDocument();
            $importDocument->load($importFilePath);
            $fileElements = $importDocument->getElementsByTagName('file');
            /** @var \DOMElement $fileElement */
            foreach ($fileElements as $fileElement) {
                $modelClass =  $fileElement->getAttribute('product-name');
                $identifier = $fileElement->getAttribute('original');
                $targetLanguage = $fileElement->getAttribute('target-language');

                $repository = $this->objectManager->get($this->classMappingService->getRepositoryClassByModel($modelClass));
                $entity = $repository->findByIdentifier($identifier);
                $entity->reloadInLocale($targetLanguage);

                $transUnits = $fileElement->getElementsByTagName('trans-unit');
                /** @var \DOMElement $transUnit */
                foreach ($transUnits as $transUnit){
                    if($transUnit->getElementsByTagName('target')->item(0)->textContent) {
                        ObjectAccess::setProperty($entity, $transUnit->getAttribute('id'), $transUnit->getElementsByTagName('target')->item(0)->textContent);
                    }
                }
                $repository->update($entity);

            }
        }

    }

    /**
     * @param $class
     * @return string
     */
    private function classToFilename($class){
        return str_replace('\\', '_', $class);
    }

}
