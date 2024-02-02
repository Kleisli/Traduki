<?php
namespace Kleisli\Traduki\ViewHelpers;

use Doctrine\Common\Collections\Collection;
use Kleisli\Traduki\Service\XliffService;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Package\PackageManager;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use Neos\Flow\Annotations as Flow;

class XliffAsJsonViewHelper extends AbstractViewHelper {

    /**
     * @Flow\Inject
     * @var XliffService
     */
    protected $xliffService;

    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     */
    public function initializeArguments() {
        $this->registerArgument('packageKey', 'string', 'e.g. Vendor.Package', true);
        $this->registerArgument('path', 'string', 'e.g. NodeTypes/MyType.xlf', true);
    }

    /**
     */
    public function render() : string {
        $package = $this->packageManager->getPackage($this->arguments['packageKey']);
        $filePath = $package->getResourcesPath() . 'Private/Translations/de/' . $this->arguments['path'];
        $xliffArray = $this->xliffService->getXliffAsArray($filePath);

        $jsonArray = [];
        foreach(array_keys($xliffArray) as $id){
            $jsonArray[$id] = $this->translator->translateById($id, [], null, null, substr($this->arguments['path'], 0, -4), $this->arguments['packageKey']);
        }
        return json_encode($jsonArray);
    }

}
