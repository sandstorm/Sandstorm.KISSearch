<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Tests\Unit;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\Schema\Configuration\SearchSchemaConfiguration;
use Sandstorm\KISSearch\Api\SchemaTool;
use Sandstorm\KISSearch\Neos\Schema\NeosContentStandaloneInstanceSchema;

class NeosContentSearchSchemaTest extends UnitTestCase
{

    public function test_createDefaultSchema_MariaDB(): void
    {
        $schemaConfig = new SearchSchemaConfiguration(
            ['neos-content' => '\Sandstorm\KISSearch\Neos\Schema\NeosContentSearchSchema']
        );
        $schemaSql = SchemaTool::createSchemaSql(
            DatabaseType::MARIADB,
            $schemaConfig,
            new NeosContentStandaloneInstanceSchema()
        );

        var_dump($schemaSql);
    }

}