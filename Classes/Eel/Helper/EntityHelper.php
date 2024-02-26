<?php
namespace Kleisli\Traduki\Eel\Helper;

use Gedmo\Translatable\TranslatableListener;
use Kleisli\Traduki\Service\XliffService;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Utility\Algorithms;

class EntityHelper implements ProtectedContextAwareInterface
{

    #[Flow\Inject]
    protected \Neos\Flow\I18n\Service $i18nService;

    public function reloadInCurrentLocale(object $object): object
    {
        $object->reloadInLocale($this->i18nService->findBestMatchingLocale($this->i18nService->getConfiguration()->getCurrentLocale()));
        return $object;
    }

    public function reloadInDefaultLocale(object $object): object
    {
        $object->reloadInLocale($this->i18nService->findBestMatchingLocale($this->i18nService->getConfiguration()->getDefaultLocale()));
        return $object;
    }

    public function isTranslatable(object $object): bool
    {
        return method_exists($object, 'getTranslations');
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
