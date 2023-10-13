<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
enum SearchQueryType
{
    case GLOBAL_LIMIT;
    case LIMIT_PER_RESULT_TYPE;
}
