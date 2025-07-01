<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\DBAbstraction;

enum DatabaseType: string
{
    case MYSQL = 'MySQL';
    case MARIADB = 'MariaDB';
    case POSTGRES = 'PostgreSQL';

}
