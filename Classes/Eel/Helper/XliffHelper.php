<?php
namespace Kleisli\Traduki\Eel\Helper;

use Kleisli\Traduki\Service\XliffService;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Utility\Algorithms;

class XliffHelper implements ProtectedContextAwareInterface
{

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
     * @param string $packageKey
     * @param string $path
     * @return false|string
     */
    public function asJson(string $packageKey, string $path)
    {
        $package = $this->packageManager->getPackage($packageKey);
        $filePath = $package->getResourcesPath() . 'Private/Translations/de/' . $path;
        $xliffArray = $this->xliffService->getXliffAsArray($filePath);

        $jsonArray = [];
        foreach(array_keys($xliffArray) as $id){
            $jsonArray[$id] = $this->translator->translateById($id, [], null, null, substr($path, 0, -4), $packageKey);
        }
        return json_encode($jsonArray);
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }

}
