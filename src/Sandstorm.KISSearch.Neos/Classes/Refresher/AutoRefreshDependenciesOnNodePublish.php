<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Refresher;

use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactoryDependencies;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactoryInterface;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookInterface;
use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Flow\DatabaseTypeDetector;

#[Scope('singleton')]
class AutoRefreshDependenciesOnNodePublish implements CatchUpHookFactoryInterface
{

    #[InjectConfiguration(path: 'refresher.autoRefreshEnabled', package: 'Sandstorm.KISSearch.Neos')]
    protected ?bool $enabled;

    public function __construct(
        private Connection $connection,
        private DatabaseTypeDetector $databaseTypeDetector
    )
    {
    }

    public function build(CatchUpHookFactoryDependencies $dependencies): CatchUpHookInterface
    {
        if ($this->enabled === null) {
            throw new \RuntimeException("wrong configuration for auto-refresh KISSearch Neos dependencies; enabled must be a boolean, but was null");
        }
        return new RefreshDependenciesHook(
            $this->enabled,
            $this->connection,
            $dependencies->contentRepositoryId,
            $this->databaseTypeDetector->detectDatabase()
        );
    }
}