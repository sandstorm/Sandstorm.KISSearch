<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

interface SearchResultTypeInterface
{

    function getName(): SearchResultTypeName;

    function buildUrlToResultPage(SearchResult $searchResult): ?string;

    function getDatabaseMigration(DatabaseType $databaseType): DatabaseMigrationInterface;

    function getSearchQueryProvider(DatabaseType $databaseType): SearchQueryProviderInterface;

}
