<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations\Inject;
use Neos\Flow\Cli\CommandController;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\Query\Model\SearchInput;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\QueryTool;
use Sandstorm\KISSearch\Api\SchemaTool;
use Sandstorm\KISSearch\Flow\DatabaseAdapter\DoctrineSearchQueryDatabaseAdapter;
use Sandstorm\KISSearch\Flow\DatabaseTypeDetector;
use Sandstorm\KISSearch\Flow\FlowCDIObjectInstanceProvider;
use Sandstorm\KISSearch\Flow\FlowSearchEndpoints;
use Sandstorm\KISSearch\Flow\FlowSearchSchemas;
use Sandstorm\KISSearch\Neos\Schema\NeosContentSearchSchema;

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

    #[Inject]
    protected DoctrineSearchQueryDatabaseAdapter $databaseAdapter;

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

    public function printSearchQueryCommand(string $endpoint, ?string $database = null): void
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

        $this->outputLine("-- START OF QUERY");

        $query = SearchQuery::create($databaseType, $searchEndpointConfiguration, $this->instanceProvider);
        $sql = QueryTool::createSearchQuerySQL($databaseType, $query);
        $this->outputLine($sql);
        $this->outputLine("-- END OF QUERY");
    }

    public function refreshNodesAndTheirDocumentsCommand(): void
    {
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        switch ($databaseType) {
            case DatabaseType::MYSQL:
            case DatabaseType::MARIADB:
                $this->executeSqlInTransaction(
                    NeosContentSearchSchema::mariaDB_call_functionPopulateNodesAndTheirDocuments()
                );
                break;
            case DatabaseType::POSTGRES:
                throw new \Exception('Postgres not supported for now.');
        }
    }

    # Search API

    public function queryCommand(string $endpoint, string $query, string $resultLimits, ?string $params = null, ?int $limit = null, bool $showMetaData = false): void
    {
        if ($params !== null) {
            try {
                $paramsArray = json_decode($params, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $jsonError) {
                $this->outputLine('invalid --params value; cause: %s', [$jsonError->getMessage()]);
                $this->outputLine('Example usage: --params \'{"neosContentDimensionValues": {"language": ["en_US"]}, "neosContentSiteNodeName": "neosdemo"}\'');
                $this->sendAndExit(1);
            }
        } else {
            $paramsArray = [];
        }

        try {
            $resultLimitsArray = json_decode($resultLimits, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $jsonError) {
            $this->outputLine('invalid --result-limits value; cause: %s', [$jsonError->getMessage()]);
            $this->outputLine('Example usage: --result-limits \'{"neos-content": 20, "foo": 10}\'');
            $this->sendAndExit(1);
        }

        $input = new SearchInput(
            $query,
            $paramsArray,
            $resultLimitsArray,
            $limit
        );

        $searchEndpointConfiguration = $this->searchEndpoints->getEndpointConfiguration($endpoint);
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        $searchQuery = SearchQuery::create(
            $databaseType,
            $searchEndpointConfiguration,
            $this->instanceProvider
        );

        $results = QueryTool::executeSearchQuery(
            $databaseType,
            $searchQuery,
            $input,
            $this->databaseAdapter
        );

        $this->outputSearchResultsTable($results, $query, $showMetaData);
    }

    private function outputSearchResultsTable(array $results, string $query, bool $showMetaData): void
    {
        $headers = [
            'identifier' => 'Result Identifier',
            'type' => 'Result Type',
            'title' => 'Result Title',
            'url' => 'Result URL',
            'score' => 'Score',
            'matchCount' => 'Match Count'
        ];
        $tableRows = array_map(function (\JsonSerializable $result) use ($showMetaData) {
            $serialized = $result->jsonSerialize();
            if ($showMetaData) {
                $serialized['combinedMetaData'] = self::formatMetaData($serialized['groupMetaData'], $serialized['metaData']);
            }
            unset($serialized['groupMetaData']);
            unset($serialized['metaData']);
            return $serialized;
        }, $results);

        if ($showMetaData) {
            $headers['combinedMetaData'] = 'Meta Data';
        }

        $this->output->outputTable(
            $tableRows,
            $headers,
            "Search Results for '$query'"
        );
    }

    private static function formatMetaData(array $groupMetaData, array $metaData): string
    {
        $groupString = '';
        foreach ($groupMetaData as $key => $value) {
            $groupString .= self::formatMetaDataValue($key, $value, 2);
        }
        $aggString = '';
        foreach ($metaData as $key => $value) {
            $aggString .= self::formatMetaDataValue($key, $value, 2);
        }
        return "Group:\n" . $groupString . "\n" .
            "Aggregate:\n" . $aggString;
    }

    private static function formatMetaDataValue(string|int $key, mixed $value, int $indent): string
    {
        $prefix = str_repeat(' ', $indent) . $key . ': ';
        if ($value === null) {
            return $prefix . "null\n";
        }
        if (is_string($value)) {
            return $prefix . $value . "\n";
        }
        if (is_array($value)) {
            $out = $prefix . "\n";
            foreach ($value as $keyInner => $valueInner) {
                $out .= self::formatMetaDataValue($keyInner, $valueInner, $indent + 2);
            }
            return $out;
        }
        return $prefix . ((string) $value) . "\n";
    }

}