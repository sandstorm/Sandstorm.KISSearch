<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Tests\Unit;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\FrameworkAbstraction\DefaultQueryObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Query\Configuration\ResultFilterConfiguration;
use Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\Query\Model\SearchResultTypeName;
use Sandstorm\KISSearch\Api\QueryTool;
use Sandstorm\KISSearch\Neos\NeosContentSearchResultType;
use Sandstorm\KISSearch\Neos\Query\NeosContentSource;
use Sandstorm\KISSearch\Neos\Query\NeosDocumentQuery;

class NeosDocumentQueryTest extends UnitTestCase
{
    public function test_createDefaultQuery_MariaDB_globalLimit(): void
    {
        $source = new NeosContentSource();
        $filterAndAggregator = new NeosDocumentQuery();
        $objectProvider = new DefaultQueryObjectInstanceProvider(
            ['neos-content-source' => $source],
            ['neos-content-filter' => $filterAndAggregator],
            ['neos-content-type-aggregator' => $filterAndAggregator]
        );
        $endpoint = new SearchEndpointConfiguration(
            'testEndpoint',
            [NeosContentSearchResultType::OPTION_CONTENT_REPOSITORY => 'default'],
            [
                'testFilter' => new ResultFilterConfiguration(
                    'testFilter',
                    'neos-content-filter',
                    SearchResultTypeName::fromString('neos-document'),
                    [],
                    ['neos-content-source']
                )
            ],
            ['neos-document' => 'neos-content-type-aggregator']
        );

        $searchQuery = SearchQuery::create(
            DatabaseType::MARIADB,
            $objectProvider,
            $endpoint
        );

        $sql = QueryTool::createSearchQuerySQL(
            DatabaseType::MARIADB,
            $searchQuery
        );

        var_dump($sql);
    }
}