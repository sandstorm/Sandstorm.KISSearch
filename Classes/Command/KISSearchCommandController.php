<?php

namespace Sandstorm\KISSearch\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use JsonSerializable;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Configuration\ConfigurationManager;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseType;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\MySQLSearchQueryBuilder;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypesRegistry;
use Sandstorm\KISSearch\SearchResultTypes\UnsupportedDatabaseException;
use Sandstorm\KISSearch\Service\SearchQuery;
use Sandstorm\KISSearch\Service\SearchService;
use Throwable;

class KISSearchCommandController extends CommandController
{

    private const MIGRATION_NOT_JET_APPLIED = 0;
    private const MIGRATION_STATUS_UP_TO_DATE = 1;
    private const MIGRATION_OUTDATED = 2;

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

    public function checkVersionCommand(bool $printSuccess = true): void
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);
        $this->internalCheckDatabaseVersion($databaseType, $printSuccess);
    }

    private function internalCheckDatabaseVersion(DatabaseType $databaseType, bool $printSuccess): void
    {
        // TODO remove hotfix
        $hotfixDisableTimedHiddenBeforeAfter = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.hotfixDisableTimedHiddenBeforeAfter'
        );

        $requiredVersion = match ($databaseType) {
            DatabaseType::MARIADB => $hotfixDisableTimedHiddenBeforeAfter ? '10.5.0' : '10.6.0',
            DatabaseType::MYSQL => '8.0',
            DatabaseType::POSTGRES => throw new UnsupportedDatabaseException('Postgres will be supported soon <3', 1690063470),
            default => throw new UnsupportedDatabaseException(
                "Version check does not support database of type '$databaseType->name'",
                1690118087
            )
        };
        $actualVersion = $this->getActualDatabaseVersion($databaseType);
        $versionRequirementsFulfilled = version_compare($actualVersion, $requiredVersion, '>=');
        if ($versionRequirementsFulfilled) {
            if ($printSuccess) {
                $this->outputLine('<success>Minimal version requirements for %s database fulfilled, have fun with KISSearch!</success>', [$databaseType->value]);
                $this->outputLine("Required minimal %s version is '%s'; your version: '%s'", [$databaseType->value, $requiredVersion, $actualVersion]);
            }
        } else {
            $this->outputLine('<error>Minimal version requirements for %s database not fulfilled!</error>', [$databaseType->value]);
            $this->outputLine("Required minimal %s version is '%s'; but was: '%s'", [$databaseType->value, $requiredVersion, $actualVersion]);
            $this->sendAndExit(1);
        }
    }

    private function getActualDatabaseVersion(DatabaseType $databaseType): string
    {
        $sql = match ($databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => MySQLSearchQueryBuilder::buildDatabaseVersionQuery(),
            DatabaseType::POSTGRES => throw new UnsupportedDatabaseException('Postgres will be supported soon <3', 1690063470),
            default => throw new UnsupportedDatabaseException(
                "Version check does not support database of type '$databaseType->name'",
                1690118078
            )
        };
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('version', 0);

        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        return $query->getSingleResult()[0];
    }

    public function migrateCommand(bool $print = false): void
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);
        $this->internalCheckDatabaseVersion($databaseType, false);

        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();

        $this->executeMigration(<<<SQL
            create table if not exists sandstorm_kissearch_migration_status (
                search_result_type_name varchar(255) primary key not null,
                version_hash            varchar(255) not null
            );
        SQL
        );

        $migrationScripts = [];
        foreach ($searchResultTypes as $searchResultTypeName => $searchResultType) {
            $databaseMigration = $searchResultType->getDatabaseMigration($databaseType);
            $this->outputLine('Migrating up for search result type: %s', [$searchResultTypeName]);
            // migration hash logic to prevent migration for up-to-date databases
            $actualVersionHash = $databaseMigration->versionHash();
            $migrationStatus = $this->getMigrationStatus($searchResultTypeName, $actualVersionHash);
            if ($migrationStatus === self::MIGRATION_NOT_JET_APPLIED || $migrationStatus === self::MIGRATION_OUTDATED) {
                // SQL comment
                $migrationScripts[] = '-- #####################################################################';
                if ($migrationStatus === self::MIGRATION_OUTDATED) {
                    $this->outputLine('  - type %s is outdated; remove and reapply migration', [$searchResultTypeName]);
                    $migrationScripts[] = sprintf('-- removing outdated schema for search result type: %s', $searchResultTypeName);
                    $migrationScripts[] = $databaseMigration->down();
                } else {
                    $this->outputLine('  - type %s is not jet applied; perform migration', [$searchResultTypeName]);
                }
                $migrationScripts[] = sprintf('-- migration (up) for search result type: %s', $searchResultTypeName);
                $migrationScripts[] = sprintf('--   version hash: %s', $actualVersionHash);
                // insert or update migration version
                $migrationScripts[] = self::buildInsertOrUpdateVersionHashSql($databaseType, $searchResultTypeName, $actualVersionHash);
                $migrationScripts[] = $databaseMigration->up();
                $migrationScripts[] = sprintf('-- END: migration (up) for search result type: %s', $searchResultTypeName);
            } else {
                $this->outputLine('  - type %s is already up to date; skipping migration', [$searchResultTypeName]);
            }
        }

        if (empty($migrationScripts)) {
            $this->outputLine('Everything up to date; no migration needs to be applied');
        } else {
            $migrateUpScript = implode("\n", $migrationScripts);
            if ($print) {
                $this->outputScript($migrateUpScript);
            } else {
                $this->executeMigration($migrateUpScript);
            }
        }
    }

    private static function buildInsertOrUpdateVersionHashSql(DatabaseType $databaseType, string $searchResultTypeName, string $versionHash): string
    {
        return match ($databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => MySQLSearchQueryBuilder::buildInsertOrUpdateVersionHashQuery($searchResultTypeName, $versionHash),
            DatabaseType::POSTGRES => throw new UnsupportedDatabaseException('Postgres will be supported soon <3', 1690063470),
            default => throw new UnsupportedDatabaseException(
                "Migration does not support database of type '$databaseType->name'",
                1690063479
            )
        };
    }

    private function getMigrationStatus(string $searchResultTypeName, string $actualVersionHash): int
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('is_up_to_date', 0, 'boolean');
        $query = $this->entityManager->createNativeQuery(
            <<<SQL
                select ms.version_hash = :actualVersionHash as is_up_to_date
                from sandstorm_kissearch_migration_status ms
                where ms.search_result_type_name = :searchResultTypeName
                limit 1;
            SQL,
            $rsm
        );
        $query->setParameters([
            'actualVersionHash' => $actualVersionHash,
            'searchResultTypeName' => $searchResultTypeName
        ]);
        $result = $query->getOneOrNullResult();
        if (empty($result)) {
            return self::MIGRATION_NOT_JET_APPLIED;
        } else if ($result[0] === true) {
            return self::MIGRATION_STATUS_UP_TO_DATE;
        } else {
            return self::MIGRATION_OUTDATED;
        }
    }

    public function removeCommand(bool $print = false): void
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);
        $this->internalCheckDatabaseVersion($databaseType, false);

        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();

        $migrationScripts = [
            <<<SQL
                drop table if exists sandstorm_kissearch_migration_status;
            SQL
        ];
        foreach ($searchResultTypes as $searchResultTypeName => $searchResultType) {
            $databaseMigration = $searchResultType->getDatabaseMigration($databaseType);
            $this->outputLine('Migrating down for search result type: %s', [$searchResultTypeName]);
            // SQL comment
            // migration hash logic to prevent migration for up-to-date databases


            $migrationScripts[] = '-- #####################################################################';
            $migrationScripts[] = sprintf('-- migration (down) for search result type: %s', $searchResultTypeName);
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

    public function searchCommand(string $query, int $limit = 50, ?string $additionalParams = null, bool $showMetaData = false): void
    {
        $additionalParamsArray = json_decode($additionalParams, true);
        $this->printSearchQueryInput($query, $limit, $additionalParamsArray);
        $startTime = microtime(true);
        $results = $this->searchService->search(new SearchQuery($query, $limit, $additionalParamsArray));
        $endTime = microtime(true);
        $resultCount = count($results);
        $searchQueryDuration = floor(($endTime - $startTime) * 1000);
        $this->outputLine("found $resultCount results after $searchQueryDuration ms");
        $this->outputSearchResultsTable($results, $query, $showMetaData, [
            'identifier' => 'Result Identifier',
            'type' => 'Result Type',
            'title' => 'Result Title',
            'score' => 'Score',
            'matchCount' => 'Match Count'
        ]);
    }

    public function searchFrontendCommand(string $query, int $limit = 50, ?string $additionalParams = null, bool $showMetaData = false): void
    {
        $additionalParamsArray = json_decode($additionalParams, true);
        $this->printSearchQueryInput($query, $limit, $additionalParamsArray);
        $startTime = microtime(true);
        $results = $this->searchService->searchFrontend(new SearchQuery($query, $limit, $additionalParamsArray));
        $endTime = microtime(true);
        $resultCount = count($results);
        $searchQueryDuration = floor(($endTime - $startTime) * 1000);
        $this->outputLine("found $resultCount results after $searchQueryDuration ms");
        $this->outputSearchResultsTable($results, $query, $showMetaData, [
            'identifier' => 'Result Identifier',
            'type' => 'Result Type',
            'title' => 'Result Title',
            'url' => 'Document URL',
            'score' => 'Score',
            'matchCount' => 'Match Count'
        ]);
    }

    private function outputSearchResultsTable(array $results, string $query, bool $showMetaData, array $headers): void
    {
        $tableRows = array_map(function (JsonSerializable $result) use ($showMetaData) {
            $serialized = $result->jsonSerialize();
            if ($showMetaData) {
                $serialized['groupMetaData'] = json_encode($serialized['groupMetaData'], JSON_PRETTY_PRINT);
                $serialized['aggregateMetaData'] = json_encode($serialized['aggregateMetaData'], JSON_PRETTY_PRINT);
            } else {
                unset($serialized['groupMetaData']);
                unset($serialized['aggregateMetaData']);
            }
            return $serialized;
        }, $results);

        if ($showMetaData) {
            $headers['groupMetaData'] = 'Group Meta Data';
            $headers['aggregateMetaData'] = 'Aggregate Meta Data';
        }

        $this->output->outputTable(
            $tableRows,
            $headers,
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
