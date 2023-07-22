<?php

namespace Sandstorm\KISSearch\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Configuration\ConfigurationManager;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseType;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultFrontend;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypesRegistry;
use Sandstorm\KISSearch\Service\SearchQuery;
use Sandstorm\KISSearch\Service\SearchService;
use Throwable;

class KISSearchCommandController extends CommandController
{

    private readonly SearchResultTypesRegistry $searchResultTypesRegistry;

    private readonly ConfigurationManager $configurationManager;

    private readonly EntityManagerInterface $entityManager;

    private readonly SearchService $searchService;

    /**
     * @param SearchResultTypesRegistry $searchResultTypesRegistry
     * @param ConfigurationManager $configurationManager
     * @param EntityManagerInterface $entityManager
     * @param SearchService $searchService
     */
    public function __construct(SearchResultTypesRegistry $searchResultTypesRegistry, ConfigurationManager $configurationManager, EntityManagerInterface $entityManager, SearchService $searchService)
    {
        parent::__construct();
        $this->searchResultTypesRegistry = $searchResultTypesRegistry;
        $this->configurationManager = $configurationManager;
        $this->entityManager = $entityManager;
        $this->searchService = $searchService;
    }


    public function migrateCommand(bool $print = false): void
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);
        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();

        $migrationScripts = [];
        foreach ($searchResultTypes as $searchResultTypeName => $searchResultType) {
            $databaseMigration = $searchResultType->getDatabaseMigration($databaseType);
            $this->outputLine('Migrating up for search result type: %s', [$searchResultTypeName]);
            // SQL comment
            $migrationScripts[] = '-- #####################################################################';
            $migrationScripts[] = sprintf('-- migration (up) for search result type: %s', $searchResultTypeName);
            // TODO implement migration hash logic to prevent migration for up-to-date databases
            $migrationScripts[] = $databaseMigration->up();
            $migrationScripts[] = sprintf('-- END: migration (up) for search result type: %s', $searchResultTypeName);
        }

        $migrateUpScript = implode("\n", $migrationScripts);

        if ($print) {
            $this->outputScript($migrateUpScript);
        } else {
            $this->executeMigration($migrateUpScript);
        }
    }

    public function removeCommand(bool $print = false): void
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);
        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();

        $migrationScripts = [];
        foreach ($searchResultTypes as $searchResultTypeName => $searchResultType) {
            $databaseMigration = $searchResultType->getDatabaseMigration($databaseType);
            $this->outputLine('Migrating down for search result type: %s', [$searchResultTypeName]);
            // SQL comment
            $migrationScripts[] = '-- #####################################################################';
            $migrationScripts[] = sprintf('-- migration (down) for search result type: %s', $searchResultTypeName);
            // TODO implement migration hash logic to prevent migration for up-to-date databases
            $migrationScripts[] = $databaseMigration->down();
            $migrationScripts[] = sprintf('-- END: migration (down) for search result type: %s', $searchResultTypeName);
        }

        $migrateDownScript = implode("\n", $migrationScripts);

        if ($print) {
            $this->outputScript($migrateDownScript);
        } else {
            $this->executeMigration($migrateDownScript);
        }
    }

    /**
     * @param string $migrationScript
     * @return void
     * @throws Throwable
     */
    private function executeMigration(string $migrationScript): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement($migrationScript);
        /* FIXME gives error "There is no active transaction"
        $connection->transactional(function() use ($connection, $migrationScript) {
            $connection->executeStatement($migrationScript);
        });
        */
    }

    /**
     * @param string $migrationScript
     * @return void
     */
    private function outputScript(string $migrationScript): void
    {
        $this->output->outputLine();
        $this->output->outputLine('######### START OF SQL OUTPUT:');
        $this->output->outputLine();
        $this->output->outputLine($migrationScript);
        $this->output->outputLine();
    }

    public function searchCommand(string $query, int $limit = 50, ?string $additionalParams = null): void
    {
        $additionalParamsArray = json_decode($additionalParams, true);
        $this->printSearchQueryInput($query, $limit, $additionalParamsArray);
        $startTime = microtime(true);
        $results = $this->searchService->search(new SearchQuery($query, $limit, $additionalParamsArray));
        $endTime = microtime(true);
        $resultCount = count($results);
        $searchQueryDuration = floor(($endTime - $startTime) * 1000);
        $this->outputLine("found $resultCount results after $searchQueryDuration ms");

        $tableRows = array_map(function (SearchResult $result) {
            $serialized = $result->jsonSerialize();
            $serialized['metaData'] = json_encode($serialized['metaData'], JSON_PRETTY_PRINT);
            return $serialized;
        }, $results);

        $this->output->outputTable(
            $tableRows,
            [
                'identifier' => 'Result Identifier',
                'type' => 'Result Type',
                'title' => 'Result Title',
                'score' => 'Match Score',
                'metaData' => 'Meta Data'
            ],
            "Search Results for '$query'"
        );
    }

    public function searchFrontendCommand(string $query, int $limit = 50, ?string $additionalParams = null): void
    {
        $additionalParamsArray = json_decode($additionalParams, true);
        $this->printSearchQueryInput($query, $limit, $additionalParamsArray);
        $startTime = microtime(true);
        $results = $this->searchService->searchFrontend(new SearchQuery($query, $limit, $additionalParamsArray));
        $endTime = microtime(true);
        $resultCount = count($results);
        $searchQueryDuration = floor(($endTime - $startTime) * 1000);
        $this->outputLine("found $resultCount results after $searchQueryDuration ms");

        $tableRows = array_map(function (SearchResultFrontend $result) {
            $serialized = $result->jsonSerialize();
            $serialized['metaData'] = json_encode($serialized['metaData'], JSON_PRETTY_PRINT);
            return $serialized;
        }, $results);

        $this->output->outputTable(
            $tableRows,
            [
                'identifier' => 'Result Identifier',
                'type' => 'Result Type',
                'title' => 'Result Title',
                'url' => 'Document URL',
                'score' => 'Match Score',
                'metaData' => 'Meta Data'
            ],
            "Search Results for '$query'"
        );
    }

    private function printSearchQueryInput(string $query, int $limit, ?array $additionalParams): void
    {
        if ($additionalParams === null) {
            $this->output("Searching for '$query' with limit $limit ... ");
        } else {
            $additionalParamsReadable = [];
            foreach ($additionalParams as $name => $value) {
                $additionalParamsReadable[] = $name . '=' . $value;
            }
            $additionalParamsString = implode(', ', $additionalParamsReadable);
            $this->output("Searching for '$query' with limit $limit with additional params: $additionalParamsString ... ");
        }
    }

}
