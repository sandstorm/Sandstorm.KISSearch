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
use Sandstorm\KISSearch\Neos\Query\NeosContentQuery;

class NeosContentQueryTest extends UnitTestCase
{
    public function test_createDefaultQuery_MariaDB_globalLimit(): void
    {
        $neosContentQueryInstance = NeosContentQuery::createInstance('default');
        $objectProvider = new DefaultQueryObjectInstanceProvider(
            ['neos-content-source' => $neosContentQueryInstance],
            ['neos-content-filter' => $neosContentQueryInstance],
            ['neos-content-type-aggregator' => $neosContentQueryInstance]
        );
        $endpoint = new SearchEndpointConfiguration(
            'testEndpoint',
            [
                'testFilter' => new ResultFilterConfiguration(
                    'testFilter',
                    'neos-content-filter',
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