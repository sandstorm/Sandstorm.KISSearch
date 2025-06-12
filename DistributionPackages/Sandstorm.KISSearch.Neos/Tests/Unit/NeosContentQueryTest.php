<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Tests\Unit;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\Query\Configuration\ResultFilterConfiguration;
use Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\Query\Model\SearchResultTypeName;
use Sandstorm\KISSearch\Api\QueryTool;
use Sandstorm\KISSearch\Neos\Query\NeosContentQuery;
use Sandstorm\KISSearch\Neos\Query\NeosContentStandaloneInstanceQuery;

class NeosContentQueryTest extends UnitTestCase
{
    public function test_createDefaultQuery_MariaDB_globalLimit(): void
    {
        $objectProvider = new NeosContentStandaloneInstanceQuery(NeosContentQuery::createInstance('default'));
        $endpoint = new SearchEndpointConfiguration(
            'testEndpoint',
            [
                'testFilter' => new ResultFilterConfiguration(
                    'testFilter',
                    'testQuery',
                    SearchResultTypeName::create('neos-document'),
                    [],
                    ['neos-content-source']
                )
            ],
            ['neos-document' => 'neos-content-type-aggregator']
        );

        $searchQuery = SearchQuery::create(DatabaseType::MARIADB, $endpoint, $objectProvider);

        $sql = QueryTool::createSearchQuerySQL(DatabaseType::MARIADB, $searchQuery);

        var_dump($sql);
    }
}