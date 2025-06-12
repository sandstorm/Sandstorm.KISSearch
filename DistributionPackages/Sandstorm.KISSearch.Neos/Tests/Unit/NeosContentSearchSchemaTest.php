<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Tests\Unit;

use Neos\Flow\Tests\UnitTestCase;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\FrameworkAbstraction\DefaultSchemaObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Schema\Configuration\SearchSchemaConfiguration;
use Sandstorm\KISSearch\Api\SchemaTool;
use Sandstorm\KISSearch\Neos\Schema\Model\FulltextExtractionMode;
use Sandstorm\KISSearch\Neos\Schema\Model\NodePropertyFulltextExtraction;
use Sandstorm\KISSearch\Neos\Schema\Model\NodeTypesSearchConfiguration;
use Sandstorm\KISSearch\Neos\Schema\NeosContentSearchSchema;

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
            new DefaultSchemaObjectInstanceProvider([
                '\Sandstorm\KISSearch\Neos\Schema\NeosContentSearchSchema' => NeosContentSearchSchema::createInstance(
                    self::mockNodeTypeSearchConfiguration(),
                    'default'
                )
            ])
        );

        var_dump($schemaSql);
    }

    private static function mockNodeTypeSearchConfiguration(): NodeTypesSearchConfiguration
    {
        return new NodeTypesSearchConfiguration(
            ['Dev.Site:SomeDocument'],
            ['Dev.Site:SomeContent'],
            [
                'Dev.Site:SomeDocument' => ['Dev.Site:SomeDocument', 'Neos.Neos:Document'],
                'Dev.Site:SomeContent' => ['Dev.Site:SomeContent', 'Neos.Neos:Content'],
            ],
            [
                'Dev.Site:SomeDocument' => [
                    'title' => new NodePropertyFulltextExtraction('title', FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET)
                ]
            ],
            [],
            [],
            []
        );
    }

}