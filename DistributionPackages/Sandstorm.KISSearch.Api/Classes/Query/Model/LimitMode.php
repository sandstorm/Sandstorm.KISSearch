<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Model;

enum LimitMode: string
{

    case GLOBAL_LIMIT = 'global';
    case LIMIT_PER_RESULT_TYPE = 'result_type';

}
