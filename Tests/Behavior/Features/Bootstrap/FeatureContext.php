<?php

use Behat\Behat\Context\Context;
use Neos\Behat\Tests\Behat\FlowContextTrait;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Service\AuthorizationService;
use Neos\ContentRepository\Tests\Behavior\Features\Bootstrap\NodeAuthorizationTrait;
use Neos\ContentRepository\Tests\Behavior\Features\Bootstrap\NodeOperationsTrait;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Tests\Behavior\Features\Bootstrap\IsolatedBehatStepsTrait;
use Neos\Flow\Tests\Behavior\Features\Bootstrap\SecurityOperationsTrait;
use Neos\Flow\Utility\Environment;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\SiteImportService;
use Neos\Neos\Service\PublishingService;
use Neos\Neos\Tests\Functional\Command\BehatTestHelper;
use Neos\Utility\Files;
use Neos\Utility\ObjectAccess;

require_once(__DIR__ . '/../../../../../../Packages/Application/Neos.Behat/Tests/Behat/FlowContextTrait.php');
require_once(__DIR__ . '/../../../../../../Packages/Framework/Neos.Flow/Tests/Behavior/Features/Bootstrap/IsolatedBehatStepsTrait.php');
require_once(__DIR__ . '/../../../../../../Packages/Framework/Neos.Flow/Tests/Behavior/Features/Bootstrap/SecurityOperationsTrait.php');
require_once(__DIR__ . '/../../../../../../Packages/Application/Neos.ContentRepository/Tests/Behavior/Features/Bootstrap/NodeOperationsTrait.php');
require_once(__DIR__ . '/../../../../../../Packages/Application/Neos.ContentRepository/Tests/Behavior/Features/Bootstrap/NodeAuthorizationTrait.php');

/**
 * Features context
 */
class FeatureContext implements Context
{
    use FlowContextTrait;
    use NodeOperationsTrait;
    use NodeAuthorizationTrait;
    use SecurityOperationsTrait;
    use IsolatedBehatStepsTrait;

    /**
     * @var string
     */
    protected $behatTestHelperObjectName = BehatTestHelper::class;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var Environment
     */
    protected $environment;

    public function __construct()
    {
        if (self::$bootstrap === null) {
            self::$bootstrap = $this->initializeFlow();
        }
        $this->objectManager = self::$bootstrap->getObjectManager();
        $this->environment = $this->objectManager->get(Environment::class);

        $this->nodeAuthorizationService = $this->objectManager->get(AuthorizationService::class);
        $this->setupSecurity();
    }

    /**
     * @return PublishingService $publishingService
     */
    private function getPublishingService()
    {
        return $this->getObjectManager()->get(PublishingService::class);
    }

    /**
     * @Given /^foo$/
     */
    public function foo() {
        echo "fooooo";
    }

    /**
     * @Given /^I imported the site "([^"]*)"$/
     */
    public function iImportedTheSite($packageKey)
    {
        /** @var NodeDataRepository $nodeDataRepository */
        $nodeDataRepository = $this->objectManager->get(NodeDataRepository::class);
        /** @var ContextFactoryInterface $contextFactory */
        $contextFactory = $this->objectManager->get(ContextFactoryInterface::class);
        $contentContext = $contextFactory->create(['workspace' => 'live']);
        ObjectAccess::setProperty($nodeDataRepository, 'context', $contentContext, true);

        /** @var SiteImportService $siteImportService */
        $siteImportService = $this->objectManager->get(SiteImportService::class);
        $siteImportService->importFromPackage($packageKey);
        $this->persistAll();
    }

    /**
     * Clear the content cache. Since this could be needed for multiple Flow contexts, we have to do it on the
     * filesystem for now. Using a different cache backend than the FileBackend will not be possible with this approach.
     *
     * @BeforeScenario @fixtures
     */
    public function clearContentCache()
    {
        $directories = array_merge(
            glob(FLOW_PATH_DATA . 'Temporary/*/Cache/Data/Neos_Fusion_Content'),
            glob(FLOW_PATH_DATA . 'Temporary/*/*/Cache/Data/Neos_Fusion_Content')
        );
        if (is_array($directories)) {
            foreach ($directories as $directory) {
                Files::removeDirectoryRecursively($directory);
            }
        }
    }

    /**
     * @BeforeScenario @fixtures
     */
    public function removeTestSitePackages()
    {
        $directories = glob(FLOW_PATH_PACKAGES . 'Sites/Test.*');
        if (is_array($directories)) {
            foreach ($directories as $directory) {
                Files::removeDirectoryRecursively($directory);
            }
        }
    }

    /**
     * @BeforeScenario @fixtures
     */
    public function resetContextFactory()
    {
        /** @var ContextFactoryInterface $contextFactory */
        $contextFactory = $this->objectManager->get(ContextFactoryInterface::class);
        ObjectAccess::setProperty($contextFactory, 'contextInstances', [], true);
    }

    /**
     * @BeforeScenario @fixtures
     */
    public function resetContentDimensionConfiguration()
    {
        $this->resetContentDimensions();
    }

    /**
     * @Given /^I have the site "([^"]*)"$/
     */
    public function iHaveTheSite($siteName)
    {
        $site = new Site($siteName);
        $site->setSiteResourcesPackageKey('Neos.Demo');
        /** @var SiteRepository $siteRepository */
        $siteRepository = $this->objectManager->get(SiteRepository::class);
        $siteRepository->add($site);

        $this->persistAll();
    }

}
