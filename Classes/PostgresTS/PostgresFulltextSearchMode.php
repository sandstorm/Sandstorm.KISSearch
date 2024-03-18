<?php

namespace Sandstorm\KISSearch\PostgresTS;

enum PostgresFulltextSearchMode: string
{

    case DEFAULT = 'default';
    case CONTENT_DIMENSION = 'contentDimension';

}
