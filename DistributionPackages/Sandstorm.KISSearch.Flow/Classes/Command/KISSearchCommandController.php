<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow\Command;

use Closure;
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

    public function printSchemaCommand(string $schema): void
    {
        $schemaConfig = $this->searchSchemas->getSchemaConfiguration()->getSchemaConfigurations()[$schema] ?? null;
        if ($schemaConfig === null) {
            $this->outputLine("ERROR: schema $schema does not exist");
            $this->sendAndExit(1);
        }

        $this->outputLine(sprintf('### Schema: %s', $schema));
        $this->outputLine(sprintf('Schema class: %s', $schemaConfig->getSchemaClass()));
        $this->outputLine(sprintf('Refresher class: %s', $schemaConfig->getRefresherClass()));
        $this->outputLine(sprintf("Options:\n%s", json_encode($schemaConfig->getOptions(), JSON_PRETTY_PRINT)));
    }

    public function printEndpointCommand(string $endpoint): void
    {
        $endpointConfig = $this->searchEndpoints->getEndpointConfiguration($endpoint);

        $this->outputLine(sprintf('### Endpoint: %s', $endpoint));
        $this->outputLine(sprintf("Query options:\n%s", json_encode($endpointConfig->getQueryOptions(), JSON_PRETTY_PRINT)));
        $this->outputLine(sprintf("Type aggregators:\n%s", json_encode($endpointConfig->getTypeAggregators(), JSON_PRETTY_PRINT)));
        $this->outputLine('Filters:');
        foreach ($endpointConfig->getFilters() as $filter) {
            $this->outputLine(sprintf('  * Filter: %s', $filter->getFilterIdentifier()));
            $this->outputLine(sprintf('     - Filter reference: %s', $filter->getResultFilterReference()));
            $this->outputLine(sprintf('     - Result type: %s', $filter->getResultType()->getName()));
            $this->outputLine(sprintf("     - Required sources:\n%s", json_encode($filter->getRequiredSources(), JSON_PRETTY_PRINT)));
            $this->outputLine(sprintf("     - Default parameters:\n%s", json_encode($filter->getDefaultParameters(), JSON_PRETTY_PRINT)));
        }
    }

    public function listEndpointsCommand(): void
    {
        $allEndpoints = $this->searchEndpoints->getAllEndpointIds();
        if (count($allEndpoints) === 0) {
            $this->outputLine("No KISSearch query endpoints configured!");
        } else {
            $this->outputLine("List KISSearch query endpoints:");
            foreach ($allEndpoints as $endpoint) {
                $this->outputLine(" - $endpoint");
            }
        }
    }

    /**
     * Prints out the SQL CREATE schema for all configured SearchSchemaInterfaces.
     *
     * @param string|null $database autodetected, if not given
     * @return void
     */
    public function printSchemaCreateCommand(?string $database = null): void
    {
        $this->printScript($database, 'CREATE schema', function (DatabaseType $databaseType) {
            SchemaTool::createSchemaSql(
                $databaseType,
                $this->searchSchemas->getSchemaConfiguration(),
                $this->instanceProvider
            );
        });
    }

    /**
     * Prints out the SQL DROP schema for all configured SearchSchemaInterfaces.
     *
     * @param string|null $database autodetected, if not given
     * @return void
     */
    public function printSchemaDropCommand(?string $database = null): void
    {
        $this->printScript($database, 'DROP schema', function (DatabaseType $databaseType) {
            return SchemaTool::dropSchemaSql(
                $databaseType,
                $this->searchSchemas->getSchemaConfiguration(),
                $this->instanceProvider
            );
        });
    }

    /**
     * Prints out the SQL REFRESH search dependencies for all configured SearchDependencyRefreshers.
     *
     * @param string|null $schema schema filter, refresh all if not given
     * @param string|null $database autodetected, if not given
     * @return void
     */
    public function printRefreshCommand(?string $schema = null, ?string $database = null): void
    {
        $this->printScript($database, 'REFRESH search dependencies', function (DatabaseType $databaseType) use ($schema
        ) {
            return SchemaTool::refreshSearchDependenciesSql(
                $databaseType,
                $this->searchSchemas->getSchemaConfiguration(),
                $this->instanceProvider,
                $schema
            );
        });
    }

    private function printScript(?string $database, string $scriptName, Closure $script): void
    {
        $this->outputLine("-- Printing KISSearch script $scriptName from CLI command");
        if ($database === null) {
            $databaseType = $this->databaseTypeDetector->detectDatabase();
            $this->outputLine("-- no explicit database type given, detected: $databaseType->value");
        } else {
            $databaseType = DatabaseType::from($database);
            $this->outputLine("-- explicit database type: $databaseType->value");
        }
        $this->outputLine('-- START SCRIPT');

        $sql = $script($databaseType);
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

        $sql = SchemaTool::createSchemaSql(
            $databaseType,
            $this->searchSchemas->getSchemaConfiguration(),
            $this->instanceProvider
        );
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

        $sql = SchemaTool::dropSchemaSql(
            $databaseType,
            $this->searchSchemas->getSchemaConfiguration(),
            $this->instanceProvider
        );
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

        $dropSql = SchemaTool::dropSchemaSql(
            $databaseType,
            $this->searchSchemas->getSchemaConfiguration(),
            $this->instanceProvider
        );
        $this->executeSqlInTransaction($dropSql);
        $createSql = SchemaTool::createSchemaSql(
            $databaseType,
            $this->searchSchemas->getSchemaConfiguration(),
            $this->instanceProvider
        );
        $this->executeSqlInTransaction($createSql);
        $this->outputLine("done!");
    }

    public function refreshCommand(?string $schema = null): void
    {
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        $this->outputLine("refreshing KISSearch dependencies for $databaseType->value database ...");

        $sql = SchemaTool::refreshSearchDependenciesSql(
            $databaseType,
            $this->searchSchemas->getSchemaConfiguration(),
            $this->instanceProvider,
            $schema
        );
        $this->executeSqlInTransaction($sql);
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

    public function printSearchQueryCommand(string $endpoint, ?string $options = null, ?string $database = null): void
    {
        $searchEndpointConfiguration = $this->searchEndpoints->getEndpointConfiguration($endpoint);

        $queryOptionsArray = $this->parseJsonArgToArray(
            $options,
            'Example usage: --options \'{"contentRepository": "default"}}\''
        );

        $this->outputLine("-- Printing KISSearch search query SQL for endpoint '$endpoint'");
        if ($database === null) {
            $databaseType = $this->databaseTypeDetector->detectDatabase();
            $this->outputLine("-- no explicit database type given, detected: $databaseType->value");
        } else {
            $databaseType = DatabaseType::from($database);
            $this->outputLine("-- explicit database type: $databaseType->value");
        }

        if (count($queryOptionsArray) > 0) {
            $this->outputLine('-- override query options:');
            $this->outputLine('-- ' . str_replace("\n", "\n-- ", json_encode($queryOptionsArray, JSON_PRETTY_PRINT)));
        }

        $this->outputLine('-- START OF QUERY');

        $query = SearchQuery::create(
            $databaseType,
            $this->instanceProvider,
            $searchEndpointConfiguration,
            $queryOptionsArray
        );
        $sql = QueryTool::createSearchQuerySQL($databaseType, $query);
        $this->outputLine($sql);
        $this->outputLine('-- END OF QUERY');
    }

    # Search API

    public function queryCommand(
        string $endpoint,
        string $query,
        string $resultLimits,
        ?string $params = null,
        ?string $options = null,
        ?int $limit = null,
        bool $showMetaData = false
    ): void {
        $resultLimitsArray = $this->parseJsonArgToArray(
            $resultLimits,
            'Example usage: --result-limits \'{"neos-content": 20, "foo": 10}\''
        );

        $paramsArray = $this->parseJsonArgToArray(
            $params,
            'Example usage: --params \'{"neosContentDimensionValues": {"language": ["en_US"]}, "neosContentSiteNodeName": "neosdemo"}\''
        );

        $queryOptionsArray = $this->parseJsonArgToArray(
            $options,
            'Example usage: --options \'{"contentRepository": "default"}}\''
        );

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
            $this->instanceProvider,
            $searchEndpointConfiguration,
            $queryOptionsArray
        );

        $results = QueryTool::executeSearchQuery(
            $databaseType,
            $searchQuery,
            $input,
            $this->databaseAdapter
        );

        $this->outputSearchResultsTable($results, $query, $showMetaData);
    }

    private function parseJsonArgToArray(?string $argJson, string $usageDescription): array
    {
        if ($argJson !== null) {
            try {
                return json_decode($argJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $jsonError) {
                $this->outputLine('invalid JSON input argument; cause: %s', [$jsonError->getMessage()]);
                $this->outputLine($usageDescription);
                $this->sendAndExit(1);
            }
        } else {
            return [];
        }
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
                $serialized['combinedMetaData'] = self::formatMetaData(
                    $serialized['groupMetaData'],
                    $serialized['metaData']
                );
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
        return $prefix . ((string)$value) . "\n";
    }

}