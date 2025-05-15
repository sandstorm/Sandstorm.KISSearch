<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations\Inject;
use Neos\Flow\Cli\CommandController;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
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
    public function printSchemaDropCommand(?string $databaseType = null): void
    {
        $this->outputLine('-- Printing KISSearch DROP schema from CLI command');
        if ($databaseType === null) {
            $databaseType = $this->databaseTypeDetector->detectDatabase();
            $this->outputLine("-- no explicit database type given, detected: $databaseType->value");
        } else {
            $databaseType = DatabaseType::from($databaseType);
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
        $this->executeSql($sql);
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
        $this->executeSql($sql);
        $this->outputLine("done!");
    }

    private function executeSql(string $sql): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement($sql);
    }

}