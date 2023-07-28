<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;

#[Proxy(false)]
class PostgresSearchQueryBuilder
{

    public static function createFulltextIndex(SearchResultTypeName $searchResultTypeName, string $tableName, ColumnNamesByBucket $columnNames): string
    {
        // combined index
        $sqlQueries = [
            self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getAllColumnNames(), 'all')
        ];
        // single bucket index for weighting scores on individual columns
        $sqlQueries[] = self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getCritical(), 'critical');
        $sqlQueries[] = self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getMajor(), 'major');
        $sqlQueries[] = self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getNormal(), 'normal');
        $sqlQueries[] = self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getMinor(), 'minor');

        return implode("\n", array_filter($sqlQueries));
    }

    private static function createSingleFulltextIndex(SearchResultTypeName $searchResultTypeName, string $tableName, ?array $columnNames, string $indexSuffix): ?string
    {
        if (empty($columnNames)) {
            return null;
        }
        $columnNamesCommaSeparated = implode(', ', $columnNames);
        $indexSuffix = self::buildFulltextIndexName($searchResultTypeName, $indexSuffix);
        return <<<SQL
            create fulltext index $indexSuffix on $tableName ($columnNamesCommaSeparated);
        SQL;
    }

    /**
     * @param SearchResultTypeName $searchResultTypeName
     * @param string $indexSuffix
     * @return string
     */
    private static function buildFulltextIndexName(SearchResultTypeName $searchResultTypeName, string $indexSuffix): string
    {
        return sprintf("idx_%s_%s", $searchResultTypeName->getName(), $indexSuffix);
    }

    public static function buildInsertOrUpdateVersionHashQuery(string $searchResultTypeName, string $versionHash): string
    {
        // TODO SQL-injection possible! Although the values does not come from client / user input, maybe use parameters
        return <<<SQL
            insert into sandstorm_kissearch_migration_status (search_result_type_name, version_hash)
            values ('$searchResultTypeName', '$versionHash')
            on conflict (search_result_type_name) do update set version_hash = '$versionHash';
        SQL;
    }

    public static function buildDatabaseVersionQuery(): string
    {
        return <<<SQL
            select version() as version;
        SQL;
    }

}
