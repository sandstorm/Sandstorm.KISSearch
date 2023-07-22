<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

interface SearchResultTypeInterface
{

    const QUERY_PARAM_SEARCH_TERM = 'searchTerm';
    const QUERY_PARAM_LIMIT = 'limit';

    function getName(): SearchResultTypeName;

    function buildUrlToResultPage(SearchResult $searchResult): string;

    function getDatabaseMigration(DatabaseType $databaseType): DatabaseMigrationInterface;

    function getSearchQueryProvider(DatabaseType $databaseType): SearchQueryProviderInterface;

}
