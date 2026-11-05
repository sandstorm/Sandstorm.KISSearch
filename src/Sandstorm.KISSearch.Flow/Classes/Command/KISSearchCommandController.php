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
use Sandstorm\KISSearch\Flow\DatabaseAdapter\DoctrineDatabaseAdapterService;
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
    protected DoctrineDatabaseAdapterService $databaseAdapter;

    #[Inject]
    protected EntityManagerInterface $entityManager;

    /**
     * Show which search endpoints exist, i.e. what you can query
     *
     * A search endpoint is a named query preset from
     * Sandstorm.KISSearch.query.endpoints. It defines what is searched (search
     * sources), how results are filtered (result filters), which result types come
     * back (e.g. "neos-document") and default values for filter parameters.
     *
     * Shipped endpoints:
     *   default       Neos documents of content repository "default", all workspaces
     *   default-live  like "default", but only the "live" workspace
     *
     * Use the identifier with kissearch:printendpoint, kissearch:printsearchquery,
     * kissearch:query, or in Fusion/PHP when executing a search.
     *
     * @return void
     */
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
     * Show what an endpoint searches and which parameters and limits a query needs
     *
     * Prints the endpoint as resolved from Settings.yaml:
     *   - query options, e.g. the content repository
     *   - every filter: filter class reference, result type, required search
     *     sources, default parameters and filter options
     *   - every type aggregator: aggregator reference and options
     *
     * Read it before calling kissearch:query or the PHP/Fusion API:
     *   - Filter parameters are prefixed with the filter identifier and "__",
     *     e.g. filter "neos" + parameter "workspace" = "neos__workspace".
     *   - Every result type listed here needs a limit (--type-limits).
     *
     * @param string $endpoint Endpoint identifier, see kissearch:listendpoints (e.g. "default")
     * @return void
     */
    public function printEndpointCommand(string $endpoint): void
    {
        $endpointConfig = $this->searchEndpoints->getEndpointConfiguration($endpoint);

        $this->outputLine(sprintf('### Endpoint: %s', $endpoint));
        $this->outputLine(sprintf("Query options:\n%s", json_encode($endpointConfig->getQueryOptions(), JSON_PRETTY_PRINT)));
        $this->outputLine('Filters:');
        foreach ($endpointConfig->getFilters() as $filter) {
            $this->outputLine(sprintf('  * Filter: %s', $filter->getFilterIdentifier()));
            $this->outputLine(sprintf('     - Filter reference: %s', $filter->getResultFilterReference()));
            $this->outputLine(sprintf('     - Result type: %s', $filter->getResultType()->getName()));
            $this->outputLine(sprintf("     - Required sources:\n%s", json_encode($filter->getRequiredSources(), JSON_PRETTY_PRINT)));
            $this->outputLine(sprintf("     - Default parameters:\n%s", json_encode($filter->getDefaultParameters(), JSON_PRETTY_PRINT)));
            $this->outputLine(sprintf("     - Filter options:\n%s", json_encode($filter->getFilterOptions(), JSON_PRETTY_PRINT)));
        }
        $this->outputLine("Type aggregators:\n");
        foreach ($endpointConfig->getTypeAggregators() as $resultType => $aggregator) {
            $this->outputLine(sprintf('  * Aggregator for type: %s', $resultType));
            $this->outputLine(sprintf('     - Aggregator reference: %s', $aggregator->getTypeAggregatorRef()));
            $this->outputLine(sprintf("     - Aggregator options:\n%s", json_encode($aggregator->getAggregatorOptions(), JSON_PRETTY_PRINT)));
        }
    }

    /**
     * Show which search schemas exist, i.e. which search indexes KISSearch maintains
     *
     * A search schema from Sandstorm.KISSearch.schemas is a set of database objects
     * (tables, full-text indexes, procedures, functions) that KISSearch creates
     * next to your data, plus a refresher that fills them.
     *
     * Shipped schema:
     *   neos-default-cr  full-text index for the Neos content repository "default"
     *
     * Use the identifier with kissearch:printschema or as --schema for
     * kissearch:refresh and kissearch:printrefresh.
     *
     * @return void
     */
    public function listSchemasCommand(): void
    {
        $allSchemas = $this->searchSchemas->getSchemaConfiguration()->getAllSchemaIds();
        if (count($allSchemas) === 0) {
            $this->outputLine("No KISSearch schemas configured!");
        } else {
            $this->outputLine("List KISSearch schemas:");
            foreach ($allSchemas as $schema) {
                $this->outputLine(" - $schema");
            }
        }
    }

    /**
     * Show which content a search schema indexes (content repository, node type filters)
     *
     * Prints the schema class (creates and drops the SQL schema), the refresher
     * class (fills the index) and the options of the schema.
     *
     * Options of the shipped Neos schema "neos-default-cr":
     *   contentRepository     content repository to index, e.g. "default"
     *   excludedNodeTypes     node types that are never indexed, e.g. Neos.Neos:Shortcut
     *   baseDocumentNodeType  only documents of this type (or subtypes) are indexed
     *   baseContentNodeType   only content of this type (or subtypes) is indexed
     *
     * Exits with code 1 if the schema does not exist.
     *
     * @param string $schema Schema identifier, see kissearch:listschemas (e.g. "neos-default-cr")
     * @return void
     */
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

    /**
     * Dry run of kissearch:schemainit: print the SQL that creates the search index
     *
     * Prints the CREATE statements of all configured schemas between
     * "-- START SCRIPT" and "-- END SCRIPT", without touching the database.
     * Use it to review the SQL or to hand it to someone who applies it manually.
     *
     * For the Neos schema this creates, per content repository <cr>:
     *   - sandstorm_kissearch_search_buckets_<cr>: extracted text per node,
     *     with full-text indexes
     *   - sandstorm_kissearch_nodes_and_their_documents_<cr>: every node mapped
     *     to its closest document, per workspace
     *   - procedures that fill both tables (called by kissearch:refresh)
     *   - helper functions for node type and dimension checks
     *
     * Note: the Neos schema currently generates MariaDB/MySQL SQL only.
     *
     * @param string|null $database Database type to generate SQL for: MySQL, MariaDB or PostgreSQL (case-sensitive). Defaults to the setting Sandstorm.KISSearch.databaseType
     * @return void
     */
    public function printSchemaCreateCommand(?string $database = null): void
    {
        $this->printScript($database, 'CREATE schema', function (DatabaseType $databaseType) {
            return SchemaTool::createSchemaSql(
                $databaseType,
                $this->searchSchemas->getSchemaConfiguration(),
                $this->instanceProvider
            );
        });
    }

    /**
     * Dry run of kissearch:schemadrop: print the SQL that removes the search index
     *
     * Prints the DROP statements of all configured schemas between
     * "-- START SCRIPT" and "-- END SCRIPT", without touching the database.
     *
     * All statements use "if exists", so the script also runs on a database
     * without KISSearch schema. It also removes search columns that older
     * KISSearch versions added to the content repository's node table.
     *
     * @param string|null $database Database type to generate SQL for: MySQL, MariaDB or PostgreSQL (case-sensitive). Defaults to the setting Sandstorm.KISSearch.databaseType
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
     * Dry run of kissearch:refresh: print the SQL that rebuilds the search index
     *
     * Prints the statements kissearch:refresh would execute between
     * "-- START SCRIPT" and "-- END SCRIPT", without touching the database.
     * For the Neos schema these are the calls of the two procedures that
     * kissearch:schemacreate created; the indexing logic itself is inside them
     * (see kissearch:printschemacreate).
     *
     * @param string|null $schema Only this schema, see kissearch:listschemas (e.g. "neos-default-cr"). Defaults to all schemas
     * @param string|null $database Database type to generate SQL for: MySQL, MariaDB or PostgreSQL (case-sensitive). Defaults to the setting Sandstorm.KISSearch.databaseType
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
     * Create the search tables, indexes and procedures; fails if they already exist
     *
     * Executes the CREATE statements of all configured schemas (see
     * kissearch:printschemacreate) in the database configured for Flow.
     *
     * Fails if the schema already exists, because full-text indexes and
     * procedures cannot be created twice. For deployments use
     * kissearch:schemacreate, which drops the old schema first.
     *
     * The search index is empty afterwards: run kissearch:refresh to fill it.
     *
     * @return void
     */
    public function schemaInitCommand(): void
    {
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        $this->outputLine("Initializing KISSearch schema for $databaseType->value database ...");

        $sql = SchemaTool::createSchemaSql(
            $databaseType,
            $this->searchSchemas->getSchemaConfiguration(),
            $this->instanceProvider
        );
        $this->executeSqlInTransaction($sql);
        $this->outputLine("done!");
    }

    /**
     * Re-create the search tables and procedures (drop + create); run on every deployment
     *
     * Executes the DROP and then the CREATE statements of all configured schemas.
     * The indexing SQL is generated from your NodeType configuration, so run it
     *   - after installing KISSearch
     *   - after NodeType changes: new node types, changed supertypes, or changed
     *     search settings (properties.*.search.fulltextExtractor)
     *   - after changing Sandstorm.KISSearch.schemas in Settings.yaml
     * Running it without changes is safe.
     *
     * The search index is empty afterwards and searches find nothing until you
     * run kissearch:refresh.
     *
     * @return void
     */
    public function schemaCreateCommand(): void
    {
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        $this->outputLine("Creating KISSearch schema for $databaseType->value database (first drop, then create) ...");

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


    /**
     * Remove all KISSearch tables, indexes and procedures from the database
     *
     * Executes the DROP statements of all configured schemas (see
     * kissearch:printschemadrop). Use it to uninstall KISSearch or to clean up
     * before applying the schema manually. Neos content is not changed.
     *
     * Searches fail until you run kissearch:schemacreate and kissearch:refresh.
     *
     * @return void
     */
    public function schemaDropCommand(): void
    {
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        $this->outputLine("Dropping KISSearch schema for $databaseType->value database ...");

        $sql = SchemaTool::dropSchemaSql(
            $databaseType,
            $this->searchSchemas->getSchemaConfiguration(),
            $this->instanceProvider
        );
        $this->executeSqlInTransaction($sql);
        $this->outputLine("done!");
    }

    /**
     * Rebuild the search index from the current content, so new and changed content is found
     *
     * Rebuilds the index completely from the content repository; there is no
     * incremental update. For the Neos schema this
     *   1. extracts the searchable text of every node into four buckets
     *      (critical, major, normal, minor) as configured in the NodeTypes
     *   2. maps every node to its closest document, per workspace, with site,
     *      dimension values and hidden/removed state
     *
     * Content created or changed after the last refresh is not found.
     * Refresh on publish (Sandstorm.KISSearch.Neos.refresher.autoRefreshEnabled)
     * is experimental and disabled by default. So run this command after
     * kissearch:schemacreate and regularly, e.g. as a cron job.
     *
     * @param string|null $schema Only this schema, see kissearch:listschemas (e.g. "neos-default-cr"). Defaults to all schemas
     * @return void
     */
    public function refreshCommand(?string $schema = null): void
    {
        $databaseType = $this->databaseTypeDetector->detectDatabase();
        $this->outputLine("Refreshing KISSearch dependencies for $databaseType->value database ...");

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

    /**
     * Print the SQL that a search on an endpoint runs, to debug it in a database client
     *
     * Builds the same SQL as kissearch:query and prints it without executing it.
     * Inputs stay as named placeholders, so you can run the SQL with your own
     * values, e.g. with EXPLAIN:
     *   :search_query        the prepared search term
     *   :global_limit        maximum number of results in total
     *   :limit_<type>        limit per result type, e.g. :limit_neos_document
     *   :<filter>__<param>   filter parameters, e.g. :neos__workspace
     *
     * Note: the SQL is currently generated for MariaDB/MySQL only.
     *
     * @param string $endpoint Endpoint identifier, see kissearch:listendpoints (e.g. "default")
     * @param string|null $options JSON object that overrides the endpoint's queryOptions, e.g. '{"contentRepository": "default"}'
     * @param string|null $database Database type to generate SQL for: MySQL, MariaDB or PostgreSQL (case-sensitive). Defaults to the setting Sandstorm.KISSearch.databaseType
     * @return void
     */
    public function printSearchQueryCommand(string $endpoint, ?string $options = null, ?string $database = null): void
    {
        $searchEndpointConfiguration = $this->searchEndpoints->getEndpointConfiguration($endpoint);

        $queryOptionsArray = $this->parseJsonArgToArray(
            $options,
            'Example usage: --options \'{"contentRepository": "default"}\''
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

    /**
     * Search from the command line to test an endpoint and its ranking
     *
     * Runs a search against the database and prints a table with identifier,
     * type, title, URL, score and match count, sorted by score, plus the query
     * time. Requires kissearch:schemacreate and kissearch:refresh.
     *
     * Search term: lowercased, umlauts converted (ä -> ae), special characters
     * removed. Every word must match the beginning of a word, so "neo cms"
     * finds "Neos CMS".
     *
     * Limits: every result type of the endpoint needs a limit. Without
     * --type-limits you are asked for each type (default 10). --limit caps the
     * total; if it is lower than the sum of the type limits, the results with
     * the lowest score are dropped.
     *
     * Parameters override the endpoint's defaultParameters. For the shipped
     * endpoints (filter "neos"):
     *   neos__workspace            workspace name; without it, endpoint "default"
     *                              searches all workspaces
     *   neos__dimension_values     e.g. {"language": "en_US"}
     *   neos__site_node            only these site(s), e.g. "neosdemo"
     *   neos__exclude_site_node    all sites except these
     *   neos__root_node            node aggregate id; only documents below it
     *   neos__document_node_types  only these document node types
     *   neos__content_node_types   only matches in these content node types
     *
     * Example:
     *   ./flow kissearch:query default-live "hello world" --type-limits '{"neos-document": 10}' --params '{"neos__dimension_values": {"language": "en_US"}}'
     *
     * @param string $endpoint Endpoint identifier, see kissearch:listendpoints (e.g. "default")
     * @param string $query The search term
     * @param string|null $typeLimits JSON object with the maximum number of results per result type, e.g. '{"neos-document": 20}'
     * @param string|null $params JSON object with filter parameters, see above, e.g. '{"neos__workspace": "live"}'
     * @param string|null $options JSON object that overrides the endpoint's queryOptions, e.g. '{"contentRepository": "default"}'
     * @param int|null $limit Maximum number of results in total. Defaults to the sum of --type-limits
     * @param bool $showMetaData Add a column with each result's group and aggregate metadata (e.g. matched nodes)
     * @return void
     * @throws \Exception
     */
    public function queryCommand(
        string $endpoint,
        string $query,
        ?string $typeLimits = null,
        ?string $params = null,
        ?string $options = null,
        ?int $limit = null,
        bool $showMetaData = false
    ): void {

        // ### first, load your endpoint configuration
        // In this case, we use the shipped Flow service.
        $searchEndpointConfiguration = $this->searchEndpoints->getEndpointConfiguration($endpoint);

        if ($typeLimits === null) {
            // if not given, prompt for each individual
            $resultLimitsArray = [];
            foreach ($searchEndpointConfiguration->getResultTypeNames() as $resultTypeName) {
                $resultLimitsArray[$resultTypeName] = $this->output->askAndValidate(
                    "Please specify the result limit for '" . $resultTypeName . "' (10): ",
                    function($value) {
                        return intval($value);
                    },
                    2,
                    '10'
                );
            }
        } else {
            // given as JSON string in the command
            $resultLimitsArray = $this->parseJsonArgToArray(
                $typeLimits,
                'Example usage: --type-limits \'{"neos-document": 20}\''
            );
        }

        $paramsArray = $this->parseJsonArgToArray(
            $params,
            'Example usage: --params \'{"neos__workspace": "live", "neos__dimension_values": {"language": "en_US"}, "neos__site_node": "neosdemo"}\''
        );

        $queryOptionsArray = $this->parseJsonArgToArray(
            $options,
            'Example usage: --options \'{"contentRepository": "default"}\''
        );

        // ### 1. detect database type
        // may also be hard-coded in your project
        $databaseType = $this->databaseTypeDetector->detectDatabase();

        // ### 2. put user input into a SearchInput instance
        $input = new SearchInput(
            // the search query input
            $query,
            // the additional parameters, f.e. Neos workspace, etc.
            // may override default parameters configured in the endpoint
            $paramsArray,
            // limit per result type, f.e. ['neos-document' => 20, 'product' => 40]
            $resultLimitsArray,
            // (optional) global limit of all merged results
            // If not given, the sum of all limits per result type is used
            $limit
        );

        // ### 3. create the search query
        $searchQuery = SearchQuery::create(
            $databaseType,
            $this->instanceProvider,
            $searchEndpointConfiguration,
            // override default query options configured in the endpoint
            $queryOptionsArray
        );

        // ### 4. execute the search query
        $results = QueryTool::executeSearchQuery(
            $databaseType,
            $searchQuery,
            $input,
            $this->databaseAdapter
        );

        $this->outputSearchResultsTable($results->getResults(), $query, $showMetaData);
        $this->outputLine('Query took ' . $results->getQueryExecutionTimeInMs() . ' ms');
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