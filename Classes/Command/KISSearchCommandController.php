<?php

namespace Sandstorm\KISSearch\Command;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Neos\Flow\Annotations\Inject;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseType;
use Sandstorm\KISSearch\SearchResultTypes\NeosContent\NeosContentSearchResultType;
use Throwable;

class KISSearchCommandController extends CommandController
{

    #[Inject]
    protected NeosContentSearchResultType $neos;

    #[Inject]
    protected ConfigurationManager $configurationManager;

    #[Inject]
    protected EntityManagerInterface $entityManager;

    public function migrateCommand(bool $print = false): void
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);

        $migrateUpScript = $this->neos->getDatabaseMigration($databaseType)->up();

        if ($print) {
            $this->outputScript($migrateUpScript);
        } else {
            $this->executeMigration($migrateUpScript);
        }
    }

    public function removeCommand(bool $print = false): void
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);

        $migrateDownScript = $this->neos->getDatabaseMigration($databaseType)->down();

        if ($print) {
            $this->outputScript($migrateDownScript);
        } else {
            $this->executeMigration($migrateDownScript);
        }
    }

    /**
     * @param string $migrationScript
     * @return void
     * @throws Throwable
     */
    private function executeMigration(string $migrationScript): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement($migrationScript);
        /* FIXME gives error "There is no active transaction"
        $connection->transactional(function() use ($connection, $migrationScript) {
            $connection->executeStatement($migrationScript);
        });
        */
    }

    /**
     * @param string $migrationScript
     * @return void
     */
    public function outputScript(string $migrationScript): void
    {
        $this->output->outputLine(str_replace('\\', '\\\\', $migrationScript));
        $this->output->outputLine();
    }

}
