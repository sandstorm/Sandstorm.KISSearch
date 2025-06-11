<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations\Inject;
use Neos\Flow\Cli\CommandController;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\Query\Model\LimitMode;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\QueryTool;
use Sandstorm\KISSearch\Api\SchemaTool;
use Sandstorm\KISSearch\Flow\DatabaseTypeDetector;
use Sandstorm\KISSearch\Flow\FlowCDIObjectInstanceProvider;
use Sandstorm\KISSearch\Flow\FlowSearchEndpoints;
use Sandstorm\KISSearch\Flow\FlowSearchSchemas;

class KISSearchCommandController extends CommandController
{

    #[Inject]
    protected FlowSearchEndpoints $searchEndpoints;

    #[Inject]
    protected FlowSearchSchemas $searchSchemas;

    #[Inject]
    protected DatabaseTypeDetector $databaseTypeDetector;

    #[Inject]
    protected FlowCDIObjectInstanceProvider $instanceProvider;

    #[Inject]
    protected EntityManagerInterface $entityManager;

    /**
     * Prints out the SQL CREATE schema for all configured SearchSchemaInterfaces.
     *
     * @param string|null $databaseType autodetected, if not given
     * @return void
     */
    public function printSchemaCreateCommand(?string $databaseType = null): void
    {
        $this->outputLine('-- Printing KISSearch CREATE schema from CLI command');
        if ($databaseType === null) {
            $databaseType = $this->databaseTypeDetector->detectDatabase();
            $this->outputLine("-- no explicit database type given, detected: $databaseType->value");
        } else {
            $databaseType = DatabaseType::from($databaseType);
            $this->outputLine("-- explicit database type: $databaseType->value");
        }
        $this->outputLine('-- START SCRIPT');

        $sql = SchemaTool::createSchemaSql($databaseType, $this->searchSchemas->getSchemaConfiguration(), $this->instanceProvider);
        $this->outputLine($sql);

        $this->outputLine('-- END SCRIPT');
    }

    /**
     * Prints out the SQL DROP schema for all configured SearchSchemaInterfaces.
     *
     * @param string|null $databaseType autodetected, if not given
     * @return void
     */
    public function printSchemaDropCommand(?string $database = null): void
    {
        $this->outputLine('-- Printing KISSearch DROP schema from CLI command');
        if ($database === null) {
            $databaseType = $this->databaseTypeDetector->detectDatabase();
            $this->outputLine("-- no explicit database type given, detected: $databaseType->value");
        } else {
            $databaseType = DatabaseType::from($database);
            $this->outputLine("-- explicit database type: $databaseType->value");
        }
        $this->outputLine('-- START SCRIPT');

        $sql = SchemaTool::dropSchemaSql($databaseType, $this->searchSchemas->getSchemaConfiguration(), $this->instanceProvider);
        $this->outputLine($sql);

        $this->outputLine('-- END SCRIPT');
    }

    /**
     * Applies the SQL CREATE schema for all configured SearchSchemaInterfaces.
     *
     * @return void
     */
    public function schemaCreateCommand(): void
    {
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        $this->outputLine("creating KISSearch schema for $databaseType->value database ...");

        $sql = SchemaTool::createSchemaSql($databaseType, $this->searchSchemas->getSchemaConfiguration(), $this->instanceProvider);
        $this->executeSqlInTransaction($sql);
        $this->outputLine("done!");
    }

    /**
     * Applies the SQL DROP schema for all configured SearchSchemaInterfaces.
     *
     * @return void
     */
    public function schemaDropCommand(): void
    {
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        $this->outputLine("dropping KISSearch schema for $databaseType->value database ...");

        $sql = SchemaTool::dropSchemaSql($databaseType, $this->searchSchemas->getSchemaConfiguration(), $this->instanceProvider);
        $this->executeSqlInTransaction($sql);
        $this->outputLine("done!");
    }

    /**
     * Resets the SQL Schema by applying the DROP and CREATE schema for all configured SearchSchemaInterfaces.
     *
     * @return void
     */
    public function schemaResetCommand(): void
    {
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        $this->outputLine("resetting KISSearch schema for $databaseType->value database ...");

        $dropSql = SchemaTool::dropSchemaSql($databaseType, $this->searchSchemas->getSchemaConfiguration(), $this->instanceProvider);
        $this->executeSqlInTransaction($dropSql);
        $createSql = SchemaTool::createSchemaSql($databaseType, $this->searchSchemas->getSchemaConfiguration(), $this->instanceProvider);
        $this->executeSqlInTransaction($createSql);
        $this->outputLine("done!");
    }

    private function executeSqlInTransaction(string $sql): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement($sql);
        /*
         * FIXME why is this not working? ERROR: There is no active transaction.
        $connection->beginTransaction();
        $success = false;
        try {
            $connection->executeStatement($sql);
            $success = true;
        } finally {
            if ($success === false) {
                $connection->rollBack();
            }
        }
        try {
            $connection->commit();
        } catch (\Throwable $e) {
            $this->outputLine("ERROR: {$e->getMessage()}");
            $connection->rollBack();
        }
         */
    }

    public function printSearchQueryCommand(string $endpoint, ?string $database = null, ?string $limitMode = null): void
    {
        $searchEndpointConfiguration = $this->searchEndpoints->getEndpointConfiguration($endpoint);

        $this->outputLine("-- Printing KISSearch search query SQL for endpoint '$endpoint'");
        if ($database === null) {
            $databaseType = $this->databaseTypeDetector->detectDatabase();
            $this->outputLine("-- no explicit database type given, detected: $databaseType->value");
        } else {
            $databaseType = DatabaseType::from($database);
            $this->outputLine("-- explicit database type: $databaseType->value");
        }

        if ($limitMode === null) {
            $limitModeValue = LimitMode::GLOBAL_LIMIT;
        } else {
            $limitModeValue = LimitMode::from($limitMode);
        }
        $this->outputLine("-- limit mode: $limitModeValue->value");
        $this->outputLine("-- START OF QUERY");

        $query = SearchQuery::create($databaseType, $searchEndpointConfiguration, $this->instanceProvider);
        $sql = QueryTool::createSearchQuerySQL($databaseType, $query, $limitModeValue);
        $this->outputLine($sql);
        $this->outputLine("-- END OF QUERY");
    }

}