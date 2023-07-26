<?php

namespace Sandstorm\KISSearch;

use Neos\Flow\Core;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Neos\Domain\Service\SiteImportService;
use Neos\Neos\Service\PublishingService;
use Sandstorm\KISSearch\SearchResultTypes\NeosContent\NeosContentSearchResultType;

class Package extends BasePackage
{
    public function boot(Core\Bootstrap $bootstrap): void
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(PublishingService::class, 'nodePublished', NeosContentSearchResultType::class, 'onNodePublished', false);
        $dispatcher->connect(SiteImportService::class, 'siteImported', NeosContentSearchResultType::class, 'onSiteImported');
    }

}
