<?php

declare(strict_types=1);

namespace Klevu\Msi\Test\Integration;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\DeploymentConfig\Reader as DeploymentConfigReader;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Module\ModuleList;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class ModuleConfigTest extends TestCase
{
    /**
     * @var string
     */
    private $moduleName = 'Klevu_Msi';
    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function testTheModuleIsRegistered(): void
    {
        $registrar = new ComponentRegistrar();

        $this->assertArrayHasKey($this->moduleName, $registrar->getPaths(ComponentRegistrar::MODULE));
    }

    public function testModuleIsConfiguredAndEnabledInTheTestEnv(): void
    {
        $moduleList = $this->objectManager->create(ModuleList::class);

        $this->assertTrue($moduleList->has($this->moduleName), 'The Module is not enabled in the Test Env');
    }

    public function testModuleIsConfiguredAndEnabledInTheRealEnv(): void
    {
        $directoryList = $this->objectManager->create(DirectoryList::class, ['root' => BP]);
        $configReader = $this->objectManager->create(DeploymentConfigReader::class, [$directoryList]);
        $deploymentConfig = $this->objectManager->create(DeploymentConfig::class, [$configReader]);
        $moduleList = $this->objectManager->create(ModuleList::class, ['config' => $deploymentConfig]);

        $this->assertTrue($moduleList->has($this->moduleName), 'The Module is not enabled in the Real Env');
    }

    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
    }
}
