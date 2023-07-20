<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

interface DatabaseMigrationInterface
{

    function versionHash(): string;
    function up(): string;
    function down(): string;

}
