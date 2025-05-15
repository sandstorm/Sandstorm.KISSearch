<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;

interface SearchSourceInterface
{

    function getSearchingQueryPart(DatabaseType $databaseType): string;

}