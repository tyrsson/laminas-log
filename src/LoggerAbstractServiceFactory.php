<?php

/**
 * @see       https://github.com/laminas/laminas-log for the canonical source repository
 * @copyright https://github.com/laminas/laminas-log/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-log/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Log;

use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Logger abstract service factory.
 *
 * Allow to configure multiple loggers for application.
 */
class LoggerAbstractServiceFactory implements AbstractFactoryInterface
{
    /**
     * @var array
     */
    protected $config;

    /**
     * Configuration key holding logger configuration
     *
     * @var string
     */
    protected $configKey = 'log';

    /**
     * @param  ServiceLocatorInterface $services
     * @param  string                  $name
     * @param  string                  $requestedName
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $services, $name, $requestedName)
    {
        $config = $this->getConfig($services);
        if (empty($config)) {
            return false;
        }

        return isset($config[$requestedName]);
    }

    /**
     * @param  ServiceLocatorInterface $services
     * @param  string                  $name
     * @param  string                  $requestedName
     * @return Logger
     */
    public function createServiceWithName(ServiceLocatorInterface $services, $name, $requestedName)
    {
        $config  = $this->getConfig($services);
        $config  = $config[$requestedName];
        $this->processConfig($config, $services);
        return new Logger($config);
    }

    /**
     * Retrieve configuration for loggers, if any
     *
     * @param  ServiceLocatorInterface $services
     * @return array
     */
    protected function getConfig(ServiceLocatorInterface $services)
    {
        if ($this->config !== null) {
            return $this->config;
        }

        if (!$services->has('Config')) {
            $this->config = array();
            return $this->config;
        }

        $config = $services->get('Config');
        if (!isset($config[$this->configKey])) {
            $this->config = array();
            return $this->config;
        }

        $this->config = $config[$this->configKey];
        return $this->config;
    }

    protected function processConfig(&$config, ServiceLocatorInterface $services)
    {
        if (!isset($config['writers'])) {
            return;
        }

        foreach ($config['writers'] as $index => $writerConfig) {
            if (!isset($writerConfig['name'])
                || strtolower($writerConfig['name']) != 'db'
            ) {
                continue;
            }
            if (!isset($writerConfig['options'])
                || !isset($writerConfig['options']['db'])
            ) {
                continue;
            }
            if (!is_string($writerConfig['options']['db'])) {
                continue;
            }
            if (!$services->has($writerConfig['options']['db'])) {
                continue;
            }

            // Retrieve the DB service from the service locator, and
            // inject it into the configuration.
            $db = $services->get($writerConfig['options']['db']);
            $config['writers'][$index]['options']['db'] = $db;
        }
    }
}
