<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Log;

use Zend\Log\LoggerAbstractServiceFactory;
use Zend\Log\ProcessorPluginManager;
use Zend\Log\Writer\Noop;
use Zend\Log\WriterPluginManager;
use Zend\Log\Writer\Db as DbWriter;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\ServiceManager;

/**
 * @group      Zend_Log
 */
class LoggerAbstractServiceFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceManager;

    /**
     * Set up LoggerAbstractServiceFactory and loggers configuration.
     *
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        $this->serviceManager = new ServiceManager(['abstract_factories' => [LoggerAbstractServiceFactory::class]]);
        $this->serviceManager->setService('Config', [
            'log' => [
                'Application\Frontend' => [],
                'Application\Backend'  => [],
            ],
        ]);
    }

    /**
     * @return array
     */
    public function providerValidLoggerService()
    {
        return [
            ['Application\Frontend'],
            ['Application\Backend'],
        ];
    }

    /**
     * @return array
     */
    public function providerInvalidLoggerService()
    {
        return [
            ['Logger\Application\Unknown'],
            ['Logger\Application\Frontend'],
            ['Application\Backend\Logger'],
        ];
    }

    /**
     * @param string $service
     * @dataProvider providerValidLoggerService
     */
    public function testValidLoggerService($service)
    {
        $actual = $this->serviceManager->get($service);
        $this->assertInstanceOf('Zend\Log\Logger', $actual);
    }

    /**
     * @dataProvider providerInvalidLoggerService
     *
     * @param string $service
     */
    public function testInvalidLoggerService($service)
    {
        $this->setExpectedException(ServiceNotFoundException::class);
        $this->serviceManager->get($service);
    }

    /**
     * @group 5254
     */
    public function testRetrievesDatabaseServiceFromServiceManagerWhenEncounteringDbWriter()
    {
        $db = $this->getMockBuilder('Zend\Db\Adapter\Adapter')
            ->disableOriginalConstructor()
            ->getMock();

        $serviceManager = new ServiceManager(['abstract_factories' => [LoggerAbstractServiceFactory::class]]);
        $serviceManager->setService('Db\Logger', $db);
        $serviceManager->setService('Config', [
            'log' => [
                'Application\Log' => [
                    'writers' => [
                        [
                            'name'     => 'db',
                            'priority' => 1,
                            'options'  => [
                                'separator' => '_',
                                'column'    => [],
                                'table'     => 'applicationlog',
                                'db'        => 'Db\Logger',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $logger = $serviceManager->get('Application\Log');
        $this->assertInstanceOf('Zend\Log\Logger', $logger);
        $writers = $logger->getWriters();
        $found   = false;

        foreach ($writers as $writer) {
            if ($writer instanceof DbWriter) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Did not find expected DB writer');
        $this->assertAttributeSame($db, 'db', $writer);
    }

    /**
     * @group 4455
     */
    public function testWillInjectWriterPluginManagerIfAvailable()
    {
        $writers = new WriterPluginManager(new ServiceManager());
        $mockWriter = $this->getMock('Zend\Log\Writer\WriterInterface');
        $writers->setService('CustomWriter', $mockWriter);

        $services = new ServiceManager(['abstract_factories' => [LoggerAbstractServiceFactory::class]]);
        $services->setService('LogWriterManager', $writers);
        $services->setService('Config', [
            'log' => [
                'Application\Frontend' => [
                    'writers' => [['name' => 'CustomWriter']],
                ],
            ],
        ]);

        $log = $services->get('Application\Frontend');
        $logWriters = $log->getWriters();
        $this->assertEquals(1, count($logWriters));
        $writer = $logWriters->current();
        $this->assertSame($mockWriter, $writer);
    }

    /**
     * @group 4455
     */
    public function testWillInjectProcessorPluginManagerIfAvailable()
    {
        $processors = new ProcessorPluginManager(new ServiceManager());
        $mockProcessor = $this->getMock('Zend\Log\Processor\ProcessorInterface');
        $processors->setService('CustomProcessor', $mockProcessor);

        $services = new ServiceManager(['abstract_factories' => [LoggerAbstractServiceFactory::class]]);
        $services->setService('LogProcessorManager', $processors);
        $services->setService('Config', [
            'log' => [
                'Application\Frontend' => [
                    'writers'    => [['name' => Noop::class]],
                    'processors' => [['name' => 'CustomProcessor']],
                ],
            ],
        ]);

        $log = $services->get('Application\Frontend');
        $logProcessors = $log->getProcessors();
        $this->assertEquals(1, count($logProcessors));
        $processor = $logProcessors->current();
        $this->assertSame($mockProcessor, $processor);
    }
}
