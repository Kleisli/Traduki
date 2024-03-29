<?php
namespace Kleisli\Traduki\Service;

/*
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Utility\Files;

/**
 * The Export Service
 *
 * @Flow\Scope("singleton")
 */
class ExportService extends AbstractService
{

    #[Flow\InjectConfiguration(path: "export.workspace")]
    protected string $sourceWorkspaceName;

    /**
     * The XMLWriter that is used to construct the export.
     *
     * @var \XMLWriter
     */
    protected $xmlWriter;

    #[Flow\Inject]
    protected NodeTypeManager $nodeTypeManager;

    #[Flow\Inject]
    protected NodeDataRepository $nodeDataRepository;

    #[Flow\Inject]
    protected ContentDimensionCombinator $contentDimensionCombinator;

    #[Flow\Inject]
    protected ConfigurationManager $configurationManager;

    protected ContentContext $contentContext;

    protected string $startingPoint;

    protected NodeInterface $startingPointNode;

    protected Site $site;

    protected string $sourceLanguage;

    #[Flow\InjectConfiguration(path: "sourceLanguage")]
    protected string $defaultSourceLanguage;

    protected array $sourceContexts = [];

    protected ?string $targetLanguage;

    protected ?\DateTime $modifiedAfter;

    protected bool $ignoreHidden;

    protected string $documentTypeFilter;

    protected string $contentTypeFilter;

    protected int $depth;

    /**
     * @param string $startingPoint
     * @param string|null $sourceLanguage
     * @param string|null $targetLanguage
     * @param \DateTime|null $modifiedAfter
     * @param bool $ignoreHidden
     */
    public function initialize(string $startingPoint,
                               string $sourceLanguage = null,
                               string $targetLanguage = null,
                               \DateTime $modifiedAfter = null,
                               bool $ignoreHidden = true,
                               $documentTypeFilterPreset = 'default',
                               $contentTypeFilterPreset = 'default')
    {
        $this->startingPoint = $startingPoint;
        $this->sourceLanguage = $sourceLanguage ?? $this->defaultSourceLanguage;
        $this->targetLanguage = $targetLanguage;
        $this->modifiedAfter = $modifiedAfter;
        $this->ignoreHidden = $ignoreHidden;
        $this->documentTypeFilter = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            "Kleisli.Traduki.export.documentTypeFilterPresets.".$documentTypeFilterPreset
        );
        $this->contentTypeFilter = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            "Kleisli.Traduki.export.contentTypeFilterPresets.".$contentTypeFilterPreset
        );

        $allowedContentCombinations = $this->getAllowedContentCombinationsForSourceLanguage($this->sourceLanguage);
        /** @var ContentContext $contentContext */
        $contentContext = $this->contentContextFactory->create([
            'workspaceName' => $this->sourceWorkspaceName,
            'invisibleContentShown' => !$this->ignoreHidden,
            'removedContentShown' => false,
            'inaccessibleContentShown' => !$this->ignoreHidden,
            'dimensions' => [$this->languageDimension => [$this->sourceLanguage]]
        ]);
        $this->contentContext = $contentContext;

        foreach ($allowedContentCombinations as $contentDimensions) {
            /** @var ContentContext $sourceContext */
            $sourceContext = $this->contentContextFactory->create([
                'invisibleContentShown' => $contentContext->isInvisibleContentShown(),
                'removedContentShown' => false,
                'inaccessibleContentShown' => $contentContext->isInaccessibleContentShown(),
                'dimensions' => $contentDimensions
            ]);
            $this->sourceContexts[] = $sourceContext;
        }

        $startingPointNode = $this->contentContext->getNodeByIdentifier($startingPoint);
        if($startingPointNode === null){
            $startingPointNode = $this->contentContext->getNode('/sites/' . $this->startingPoint);
            if ($startingPointNode === null) {
                throw new \RuntimeException(sprintf('Could not find node "%s"', $this->startingPoint), 1473241812);
            }
        }

        $this->startingPointNode = $startingPointNode;
        $pathArray = explode('/', $this->startingPointNode->findNodePath());
        $this->site = $this->siteRepository->findOneByNodeName($pathArray[2]);

        if($this->workspaceRepository->findOneByName($this->sourceWorkspaceName) === null){
            throw new \RuntimeException(sprintf('Could not find workspace "%s"', $this->sourceWorkspaceName), 14732418113);
        }

    }

    /**
     * Fetches the site with the given name and exports it into XML.
     *
     * @return string
     * @throws \Exception
     */
    public function exportToString(): string
    {
        $this->xmlWriter = new \XMLWriter();
        $this->xmlWriter->openMemory();
        $this->xmlWriter->setIndent(true);

        $this->exportToXmlWriter();

        return $this->xmlWriter->outputMemory(true);
    }

    /**
     * Export into the given file.
     *
     * @param string $pathAndFilename Path to where the export output should be saved to
     * @return void
     * @throws \Exception
     */
    public function exportToFile(string $pathAndFilename)
    {
        Files::createDirectoryRecursively(pathinfo($this->exportDirectory.$pathAndFilename,  PATHINFO_DIRNAME));
        Files::createDirectoryRecursively($this->importDirectory);

        $this->xmlWriter = new \XMLWriter();
        $this->xmlWriter->openUri($this->exportDirectory.$pathAndFilename);
        $this->xmlWriter->setIndent(true);

        $this->exportToXmlWriter();

        $this->xmlWriter->flush();
    }

    /**
     * Export to the XMLWriter.
     *
     * @return void
     * @throws \Exception
     */
    protected function exportToXmlWriter()
    {
        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->startElement('content');

        $this->xmlWriter->writeAttribute('name', $this->site->getName());
        $this->xmlWriter->writeAttribute('sitePackageKey', $this->site->getSiteResourcesPackageKey());
        switch ($this->formatVersion){
            case '1.0':
                $this->xmlWriter->writeAttribute('workspace', $this->sourceWorkspaceName);
                break;
            case '2.0':
                $this->xmlWriter->writeAttribute('sourceWorkspace', $this->sourceWorkspaceName);
                break;
            default:
                throw new \RuntimeException(sprintf('Tried to export unsupported format version (%s).', $this->formatVersion), 1634721624);
        }

        $this->xmlWriter->writeAttribute('sourceLanguage', $this->sourceLanguage);
        if ($this->targetLanguage !== null) {
            $this->xmlWriter->writeAttribute('targetLanguage', $this->targetLanguage);
        }
        if ($this->modifiedAfter !== null) {
            $this->xmlWriter->writeAttribute('modifiedAfter', $this->modifiedAfter->format('c'));
        }

        $this->xmlWriter->startElement('nodes');
        $this->xmlWriter->writeAttribute('formatVersion', $this->formatVersion);
        switch ($this->formatVersion){
            case '1.0':
                $this->exportNodes($this->startingPointNode->findNodePath());
                break;
            case '2.0':
                $this->exportDocuments($this->startingPointNode->findNodePath());
                break;
            default:
                throw new \RuntimeException(sprintf('Tried to export unsupported format version (%s).', $this->formatVersion), 1634721624);
        }

        $this->xmlWriter->endElement(); // nodes

        $this->xmlWriter->endElement();
        $this->xmlWriter->endDocument();
    }

    /**
     * Exports the node data of all nodes in the given sub-tree
     * by writing them to the given XMLWriter.
     *
     * @param string $startingPointNodePath path to the root node of the sub-tree to export.
     * @return void
     * @throws \Exception
     */
    protected function exportNodes(string $startingPointNodePath)
    {
        $contentContext = $this->contentContext;
        $this->securityContext->withoutAuthorizationChecks(function () use ($startingPointNodePath, $contentContext) {
            $nodeDataList = $this->findNodeDataListToExport($startingPointNodePath, $contentContext);
            $this->exportNodeDataList($nodeDataList);
        });
    }

    /**
     * Find all nodes of the specified workspace lying below the path specified by
     * (and including) the given starting point.
     *
     * @param string $startingPointNodePath Absolute path specifying the starting point
     * @param ContentContext $contentContext
     * @param string|null $nodeTypeFilter
     * @return array<NodeData>
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    protected function findNodeDataListToExport(string $startingPointNodePath, ContentContext $contentContext, string $nodeTypeFilter = null): array
    {

        /** @var NodeData[] $nodeDataList */
        $nodeDataList = [];
        /** @var ContentContext $sourceContext */
        foreach ($this->sourceContexts as $sourceContext) {

            // when exporting 1.0 or Documents in 2.0
            // add starting point and get nodes recursively
            if($this->formatVersion == '1.0' || $nodeTypeFilter == $this->documentTypeFilter){
                $nodeDataList = array_merge(
                    $nodeDataList,
                    [$sourceContext->getNode($startingPointNodePath)->getNodeData()],
                    $this->nodeDataRepository->findByParentAndNodeTypeRecursively($startingPointNodePath, $nodeTypeFilter, $contentContext->getWorkspace(), $sourceContext->getDimensions(), $contentContext->isRemovedContentShown() ? null : false)
                );
            }elseif($nodeTypeFilter == $this->contentTypeFilter){
                $nodeDataList = array_merge(
                    $nodeDataList,
                    $this->nodeDataRepository->findByParentAndNodeType($startingPointNodePath, $nodeTypeFilter, $contentContext->getWorkspace(), $sourceContext->getDimensions(), $contentContext->isRemovedContentShown() ? null : false)
                );
            }
        }

        $uniqueNodeDataList = [];
        usort($nodeDataList, function (NodeData $node1, NodeData $node2) {
            if ($node1->getDimensionValues()[$this->languageDimension][0] === $this->sourceLanguage) {
                return 1;
            }
            if ($node2->getDimensionValues()[$this->languageDimension][0] === $this->sourceLanguage) {
                return -1;
            }

            return 0;
        });
        foreach ($nodeDataList as $nodeData) {
            $uniqueNodeDataList[$nodeData->getIdentifier()] = $nodeData;
        }

        $nodeDataList = array_filter(array_values($uniqueNodeDataList), function (NodeData $nodeData) {
            /** @var ContentContext $sourceContext */
            foreach ($this->sourceContexts as $sourceContext) {
                if ($sourceContext->getDimensions()[$this->languageDimension][0] !== $this->sourceLanguage) {
                    continue;
                }
                if ($nodeData->getDimensionValues()[$this->languageDimension][0] !== $this->sourceLanguage) {
                    // "reload" nodedata in correct dimension
                    $node = $sourceContext->getNodeByIdentifier($nodeData->getIdentifier());
                    if ($node === null || $node->getNodeData() === null) {
                        continue;
                    }
                    $nodeData = $node->getNodeData();
                }

                if (!$sourceContext->isInvisibleContentShown()) {
                    // filter out node if any of the parents is hidden
                    $parent = $nodeData;
                    while ($parent !== null) {
                        if ($parent->isHidden()) {
                            return false;
                        }
                        $parentNode = $sourceContext->getNode($parent->getParentPath());
                        if (!$parentNode instanceof NodeInterface
                            || $parentNode->findNodePath() == '/'
                            || $parentNode->getNodeData()->getDimensionValues() === []) {
                            break;
                        }
                        $parent = $parentNode->getNodeData();
                    }
                }
            }

            return $nodeData !== null;
        });

        // when exporting 1.0 or Documents in 2.0
        // sort by parent path, then by index else sort only by index
        if($this->formatVersion == '1.0' || $nodeTypeFilter == $this->documentTypeFilter){
            // if we sort by index first, it will become the secondary sorting afterwards
            usort($nodeDataList,
                function (NodeData $node1, NodeData $node2) {
                    return $node1->getIndex() <=> $node2->getIndex();
                }
            );
            // Sort nodeDataList by path, replacing "/" with "!" (the first visible ASCII character)
            // because there may be characters like "-" in the node path
            // that would break the sorting order
            usort($nodeDataList,
                function (NodeData $node1, NodeData $node2) {
                    return strcmp(
                        str_replace("/", "!", $node1->getParentPath()),
                        str_replace("/", "!", $node2->getParentPath())
                    );
                }
            );
        }elseif($nodeTypeFilter == $this->contentTypeFilter){
            usort($nodeDataList,
                function (NodeData $node1, NodeData $node2) {
                    return $node1->getIndex() <=> $node2->getIndex();
                }
            );
        }

        return $nodeDataList;
    }

    /**
     * Exports the given Nodes into the XML structure, contained in <nodes> </nodes> tags.
     *
     * @param array<NodeData> $nodeDataList The nodes to export
     * @return void The result is written directly into $this->xmlWriter
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     */
    protected function exportNodeDataList(array &$nodeDataList)
    {
        foreach ($nodeDataList as $nodeData) {
            $this->writeNode($nodeData);
        }
    }

    /**
     * Exports the node data of all nodes in the given sub-tree
     * by writing them to the given XMLWriter.
     *
     * @param string $startingPointNodePath path to the root node of the sub-tree to export. The specified node will not be included, only its sub nodes.
     * @return void
     * @throws \Exception
     */
    protected function exportDocuments(string $startingPointNodePath)
    {
        $contentContext = $this->contentContext;
        $this->securityContext->withoutAuthorizationChecks(function () use ($startingPointNodePath, $contentContext) {
            $nodeDataList = $this->findNodeDataListToExport($startingPointNodePath, $contentContext, $this->documentTypeFilter);
            if(count($nodeDataList) > 0) {
                $this->exportNodeDataList($nodeDataList);
            }
        });
    }

    /**
     * Exports the node data of all nodes in the given sub-tree
     * by writing them to the given XMLWriter.
     *
     * @param string $startingPointNodePath path to the root node of the sub-tree to export.
     * @return void
     * @throws \Exception
     */
    protected function exportContent(string $startingPointNodePath)
    {
        $contentContext = $this->contentContext;
        $this->securityContext->withoutAuthorizationChecks(function () use ($startingPointNodePath, $contentContext) {
            $nodeDataList = $this->findNodeDataListToExport($startingPointNodePath, $contentContext, $this->contentTypeFilter);
            if(count($nodeDataList) > 0) {
                $this->exportNodeDataList($nodeDataList);
            }
        });
    }

    /**
     * Write a single node into the XML structure
     *
     * @param NodeData $nodeData The node data
     * @return void The result is written directly into $this->xmlWriter
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     */
    protected function writeNode(NodeData $nodeData)
    {
        $this->xmlWriter->startElement('node');
        if($this->formatVersion == '2.0') {
            $this->xmlWriter->writeAttribute('nodeType', $nodeData->getNodeType()->getName());
        }
        $this->xmlWriter->writeAttribute('nodeName', $nodeData->getName());
        $this->xmlWriter->writeAttribute('identifier', $nodeData->getIdentifier());

        if($this->debug) {
            $this->xmlWriter->writeAttribute('sortingIndex', $nodeData->getIndex());
        }

        /** @var ContentContext $sourceContext */

        $nodeVariants = $this->contentContext->getNodeVariantsByIdentifier($nodeData->getIdentifier());
        foreach ($nodeVariants as $nodeVariant){
            if($nodeVariant->getDimensions()[$this->languageDimension][0] == $this->sourceLanguage) {
                $this->writeVariant($nodeVariant->getNodeData());
            }
        }

        if($this->formatVersion == '2.0'){
            $this->xmlWriter->startElement('childNodes');
            $this->exportContent($nodeData->getPath());
            $this->xmlWriter->endElement();
        }

        $this->xmlWriter->endElement(); // "node"
    }

    /**
     * Write a node variant into the XML structure
     *
     * @param NodeData $nodeData
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     */
    protected function writeVariant(NodeData $nodeData)
    {
        $this->xmlWriter->startElement('variant');
        if($this->formatVersion == '1.0') {
            $this->xmlWriter->writeAttribute('nodeType', $nodeData->getNodeType()->getName());
        }
        if($this->debug) {
            $this->xmlWriter->writeAttribute('nodeType', $nodeData->getNodeType()->getName());
            $this->xmlWriter->writeAttribute('identifier', $nodeData->getIdentifier());
            $this->xmlWriter->writeAttribute('nodeName', $nodeData->getName());
            $this->xmlWriter->writeAttribute('dimensionsHash', $nodeData->getDimensionsHash());
        }

        $this->writeDimensions($nodeData);
        $this->writeProperties($nodeData);

        $this->xmlWriter->endElement();
    }

    /**
     * Write dimensions and their values into the XML structure.
     *
     * @param NodeData $nodeData
     * @return void
     */
    protected function writeDimensions(NodeData $nodeData)
    {
        $this->xmlWriter->startElement('dimensions');
        foreach ($nodeData->getDimensionValues() as $dimensionKey => $dimensionValues) {
            foreach ($dimensionValues as $dimensionValue) {
                if($dimensionKey != $this->languageDimension) {
                    $this->xmlWriter->writeElement($dimensionKey, $dimensionValue);
                }
            }
        }
        $this->xmlWriter->endElement();
    }

    /**
     * Write properties and their values into the XML structure.
     *
     * @param NodeData $nodeData
     * @return void
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     */
    protected function writeProperties(NodeData $nodeData)
    {
        $this->xmlWriter->startElement('properties');
        $nodeType = $nodeData->getNodeType();

        foreach ($nodeData->getProperties() as $propertyName => $propertyValue) {
            if ($nodeType->hasConfiguration('properties.' . $propertyName)) {
                $options = $nodeType->getConfiguration('options.Kleisli.Traduki.properties.' . $propertyName);
                if (isset($options['skip']) && $options['skip'] === true) {
                    continue;
                }

                $declaredPropertyType = $nodeType->getPropertyType($propertyName);
                if ($declaredPropertyType === 'string') {
                    $this->writeStringProperty($propertyName, $propertyValue);
                }
            }
        }
        $this->xmlWriter->endElement();
    }

    /**
     * Writes out a single string property into the XML structure.
     *
     * @param string $propertyName The name of the property
     * @param string $propertyValue The value of the property
     */
    protected function writeStringProperty(string $propertyName, string $propertyValue)
    {
        $this->xmlWriter->startElement($propertyName);
        $this->xmlWriter->writeAttribute('type', 'string');
        if ($propertyValue !== '' && $propertyValue !== null) {
            $this->xmlWriter->startCData();
            $this->xmlWriter->text($propertyValue);
            $this->xmlWriter->endCData();
        }
        $this->xmlWriter->endElement();
    }

    protected function getAllowedContentCombinationsForSourceLanguage(string $sourceLanguage): array
    {
        $allAllowedContentCombinations = $this->contentDimensionCombinator->getAllAllowedCombinations();

        return array_filter($allAllowedContentCombinations, function ($combination) use ($sourceLanguage) {
            return (isset($combination[$this->languageDimension]) && $combination[$this->languageDimension][0] === $sourceLanguage);
        });
    }

    /**
     * @return NodeInterface
     */
    public function getStartingPointNode(): NodeInterface
    {
        return $this->startingPointNode;
    }

}
