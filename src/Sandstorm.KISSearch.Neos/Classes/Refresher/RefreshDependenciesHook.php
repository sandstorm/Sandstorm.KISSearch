<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Refresher;

use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\Subscription\SubscriptionStatus;
use Neos\EventStore\Model\EventEnvelope;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Neos\Schema\NeosContentSearchSchema;

/**
 * Automatically refreshes the KISSearch neos search dependencies on node publish.
 * WARNING: Only enable this, if the command does not take longer than, let's say, 500ms.
 * (depending on the number of nodes in your DB)
 *
 * TODO make the update of "nodes and their documents" workspace specific (for performance reasons)
 */
class RefreshDependenciesHook implements CatchUpHookInterface
{

    public function __construct(
        private bool $enabled,
        private Connection $dbal,
        private ContentRepositoryId $contentRepositoryId,
        private DatabaseType $databaseType
    ) {
    }

    public function onAfterEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
    }

    public function onAfterBatchCompleted(): void
    {
        // nothing
    }

    public function onAfterCatchUp(): void
    {
        if (!$this->enabled) {
            // feature is disabled via configuration
            return;
        }
        $sql = match ($this->databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => $this->doRefresh_MariaDB(),
            DatabaseType::POSTGRES => throw new \RuntimeException('To be implemented'),
        };

        if ($sql === null) {
            return;
        }

        $this->dbal->executeQuery($sql);
    }

    private function doRefresh_MariaDB():?string {
        $schemaExists =  $this->dbal->executeQuery(NeosContentSearchSchema::mariaDB_exists_functionPopulateNodesAndTheirDocuments(
            $this->contentRepositoryId->value
        ))->fetchAssociative();

        if ($schemaExists['kissearch_schema_exists'] ?? false) {
            return NeosContentSearchSchema::mariaDB_call_functionPopulateNodesAndTheirDocuments(
                $this->contentRepositoryId->value
            );
        }
        return null;
    }

    public function onBeforeCatchUp(SubscriptionStatus $subscriptionStatus): void
    {
        // nothing
    }

    public function onBeforeEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        // nothing
    }
}
