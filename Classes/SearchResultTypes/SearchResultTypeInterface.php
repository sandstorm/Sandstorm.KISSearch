<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

interface SearchResultTypeInterface
{

    const QUERY_PARAM_SEARCH_TERM = 'searchTerm';
    const QUERY_PARAM_LIMIT = 'limit';

    function getName(): SearchResultTypeName;

    function buildUrlToResultPage(SearchResultIdentifier $searchResultIdentifier): string;

    function getDatabaseMigration(DatabaseType $databaseType): DatabaseMigrationInterface;

    function getResultSearchingQueryPart(DatabaseType $databaseType): string;

    function getResultMergingQueryPart(DatabaseType $databaseType): string;

}
