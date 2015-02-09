<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Mtf\Fixture;

use Magento\Mtf\Handler\HandlerFactory;
use Magento\Mtf\Repository\RepositoryFactory;
use Magento\Mtf\Config; // Magento\Mtf\SystemConfig
use Magento\Mtf\System\Event\EventManagerInterface;
use Magento\Mtf\Fixture\InjectableFixture\Replacer;

/**
 * Class InjectableFixture
 *
 * Ensures correct data representation between the system under test and testing framework
 *
 * @api
 */
class InjectableFixture implements FixtureInterface
{
    /**
     * Fixture Default Data Set
     *
     * @var array
     */
    protected $defaultDataSet = [];

    /**
     * Fixture data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Fixture Configuration
     *
     * @var array
     */
    protected $dataConfig = [];

    /**
     * Repository Class Name
     *
     * @var string
     */
    protected $repositoryClass;

    /**
     * Configuration instance
     *
     * @var Config
     */
    protected $configuration;

    /**
     * RepositoryFactory instance
     *
     * @var RepositoryFactory
     */
    protected $repositoryFactory;

    /**
     * FixtureFactory instance
     *
     * @var FixtureFactory
     */
    protected $fixtureFactory;

    /**
     * HandlerFactory instance
     *
     * @var HandlerFactory
     */
    protected $handlerFactory;

    /**
     * Handler Interface Name
     *
     * @var string
     */
    protected $handlerInterface;

    /**
     * @var array
     */
    protected $sourceParamsFallback = ['source', 'fixture'];

    /**
     * @var \Magento\Mtf\System\Event\EventManagerInterface
     */
    protected $eventManager;

    /**
     * @var Replacer
     */
    protected $replacer;

    /**
     * Constructor
     *
     * @constructor
     * @param Config $configuration
     * @param RepositoryFactory $repositoryFactory
     * @param FixtureFactory $fixtureFactory
     * @param HandlerFactory $handlerFactory
     * @param EventManagerInterface $eventManager
     * @param Replacer $replacer
     * @param array $data
     * @param string $dataSet
     * @param bool $persist
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function __construct(
        Config $configuration,
        RepositoryFactory $repositoryFactory,
        FixtureFactory $fixtureFactory,
        HandlerFactory $handlerFactory,
        EventManagerInterface $eventManager,
        Replacer $replacer,
        array $data = [],
        $dataSet = '',
        $persist = false
    ) {
        $this->configuration = $configuration;
        $this->repositoryFactory = $repositoryFactory;
        $this->fixtureFactory = $fixtureFactory;
        $this->handlerFactory = $handlerFactory;
        $this->eventManager = $eventManager;
        $this->replacer = $replacer;

        if ($dataSet) {
            $data = $this->getDataFromRepository($dataSet, $data);
        }
        if (!$data) {
            $data = $this->defaultDataSet;
        }

        foreach ($data as $name => $value) {
            if (!isset($this->$name)) {
                continue;
            }

            $params = $this->$name;
            if ($value === null) {
                $value = isset($params['default_value']) ? $params['default_value'] : null;
            }

            $source = $this->getSourceParam($params);
            if ($source) {
                $value = $this->prepareSource($name, $value, $source);
            } else {
                $value = $this->skipEmptyValue($value);
            }

            if (null !== $value) {
                $this->data[$name] = $value;
            }
        }

        $this->data = $this->replacer->apply($this->data);
        $this->_applyPlaceholders($this->data, ['isolation' => mt_rand()]);
        if ($persist === true) {
            $this->persist();
        }
    }

    /**
     * Skip empty value of fixture data
     *
     * @param mixed $value
     * @return mixed
     */
    protected function skipEmptyValue($value)
    {
        if ('-' === $value) {
            return null;
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $subValue) {
                $data = $this->skipEmptyValue($subValue);
                if ($data !== null) {
                    $result[$key] = $data;
                }
            }
            return empty($result) ? null : $result;
        }
        return $value;
    }

    /**
     * Prepare source data
     *
     * @param string $fieldName
     * @param mixed $value
     * @param array $source
     * @return mixed
     */
    protected function prepareSource($fieldName, $value, array $source)
    {
        $value = $this->skipEmptyValue($value);
        if (null !== $value) {
            $params = &$this->$fieldName;
            $fixture = $this->fixtureFactory->create(
                $source['source'],
                [
                    'data' => $value,
                    'params' => $params,
                    'persist' => true
                ]
            );
            $params[$source['field']] = $fixture;
            $value = $fixture->getData();
        }
        return $value;
    }

    /**
     * Return source param
     *
     * @param array $params
     * @return null|array
     */
    protected function getSourceParam(array $params)
    {
        $param = null;
        foreach ($this->sourceParamsFallback as $val) {
            if (isset($params[$val])) {
                $param['field'] = $val;
                $param['source'] = $params[$val];
                break;
            }
        }
        return $param;
    }

    /**
     * Persists Fixture Data into application
     *
     * @return void
     */
    public function persist()
    {
        $this->eventManager->dispatchEvent(['persist_before'], [get_class($this)]);
        if (!empty($this->handlerInterface)) {
            $result = $this->handlerFactory->create($this->handlerInterface)->persist($this);
            if (!empty($result)) {
                foreach ($result as $key => $value) {
                    $this->data[$key] = $value;
                }
            }
        }
        $this->eventManager->dispatchEvent(['persist_after'], [get_class($this)]);
    }

    /**
     * Return prepared data set.
     *
     * @param string $key [optional]
     * @return mixed
     * @throws \RuntimeException
     */
    public function getData($key = null)
    {
        if (empty($this->data)) {
            throw new \RuntimeException('Data must be set');
        }
        if ($key === null) {
            return $this->data;
        }
        /* process a/b/c key as ['a']['b']['c'] */
        if (strpos($key, '/')) {
            $data = $this->getDataByPath($key);
        } else {
            $data = $this->getDataByKey($key);
        }
        return $data;
    }

    /**
     * Verify whether key is present in fixture data
     *
     * @param string $key [optional]
     * @return bool
     */
    public function hasData($key = null)
    {
        if (empty($this->data)) {
            return false;
        } elseif ($key === null) {
            return true;
        }
        /* process a/b/c key as ['a']['b']['c'] */
        if (strpos($key, '/')) {
            return $this->getDataByPath($key) === null ? false : true;
        } else {
            return $this->getDataByKey($key) === null ? false : true;
        }
    }

    /**
     * Return data field configuration
     *
     * @param string $key
     * @return array
     * @throws \RuntimeException
     */
    public function getDataFieldConfig($key)
    {
        if (!isset($this->$key)) {
            throw new \RuntimeException(sprintf('Data key "%s" is missed', $key));
        }
        return $this->$key;
    }

    /**
     * Return data set configuration settings
     *
     * @return array
     */
    public function getDataConfig()
    {
        return $this->dataConfig;
    }

    /**
     * Find data from repository by data set name
     *
     * @param string $dataSet
     * @param array $data
     * @return array
     */
    public function getDataFromRepository($dataSet, array $data = [])
    {
        if (empty($this->repositoryClass)) {
            return $result = $data;
        } else {
            $repository = $this->repositoryFactory->get($this->repositoryClass);
            return $result = array_replace_recursive($repository->get($dataSet), $data);
        }
    }

    /**
     * Get object data by path
     *
     * Method consider the path as chain of keys: a/b/c => ['a']['b']['c']
     *
     * @param string $path
     * @return mixed
     */
    protected function getDataByPath($path)
    {
        $keys = explode('/', $path);
        $data = $this->data;
        foreach ($keys as $key) {
            if (is_array($data) && isset($data[$key])) {
                $data = $data[$key];
            } else {
                return null;
            }
        }
        return $data;
    }

    /**
     * Get object data by particular key
     *
     * @param string $key
     * @return mixed
     */
    protected function getDataByKey($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Recursively apply placeholders to each data element
     *
     * @param array $data
     * @param array $placeholders
     * @return void
     */
    protected function _applyPlaceholders(array & $data, array $placeholders)
    {
        if ($placeholders) {
            $replacePairs = [];
            foreach ($placeholders as $pattern => $replacement) {
                $replacePairs['%' . $pattern . '%'] = $replacement;
            }
            $callback = function (&$value) use ($replacePairs) {
                foreach ($replacePairs as $pattern => $replacement) {
                    if (is_string($value) && strpos($value, $pattern) !== false) {
                        if (is_callable($replacement)) {
                            $param = trim($pattern, '%');
                            $replacement = $replacement($param);
                        }

                        $value = str_replace($pattern, $replacement, $value);
                    }
                }
            };
            array_walk_recursive($data, $callback);
        }
    }
}