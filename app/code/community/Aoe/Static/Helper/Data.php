<?php

/**
 * Data helper
 *
 */
class Aoe_Static_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**@+
     * Purge modes (urls/tags)
     *
     * @var string
     */
    const MODE_PURGE_URLS = 'purgeVarnishUrl';
    const MODE_PURGE_TAGS = 'purgeVarnishTag';
    /**@-*/

    /** @var Aoe_Static_Model_Config */
    protected $_config = null;

    /**
     * Array of enabled cache adapters
     *
     * @var Aoe_Static_Model_Cache_Adapter_Interface[]
     */
    protected $_adapterInstances;

    /**
     * @return Aoe_Static_Model_Config
     */
    public function getConfig()
    {
        if (is_null($this->_config)) {
            $this->_config = Mage::getModel('aoestatic/config');
        }

        return $this->_config;
    }

    /**
     * @return Aoe_AsyncCache_Helper_Data
     */
    protected function _getAsyncCacheHelper()
    {
        return Mage::helper('aoeasynccache');
    }

    /**
     * Instantiate and cache active adapters
     *
     * @return Aoe_Static_Model_Cache_Adapter_Interface[]
     */
    protected function _getAdapterInstances()
    {
        if (is_null($this->_adapterInstances)) {
            $this->_adapterInstances = array();

            $selectedAdapterKeys = Mage::getStoreConfig('dev/aoestatic/purgeadapter');
            foreach ($this->trimExplode(',', $selectedAdapterKeys) as $key) {
                $adapters = $this->getConfig()->getAdapters();
                if (!isset($adapters[$key])) {
                    Mage::throwException('Could not find adapter configuration for adapter "'.$key.'"');
                }

                $adapter = $adapters[$key];
                $adapterInstance = Mage::getSingleton($adapter['model']);
                if (!$adapterInstance instanceof Aoe_Static_Model_Cache_Adapter_Interface) {
                    Mage::throwException('Adapter "'.$key.'" does not implement Aoe_Static_Model_Cache_Adapter_Interface');
                }
                $adapterInstance->setConfig($adapter['config']);

                $this->_adapterInstances[$key] = $adapterInstance;
            }
        }

        return $this->_adapterInstances;
    }

    /**
     * Call purgeAll on all adapter instances
     *
     * @return array
     */
    public function purgeAll()
    {
        // if "Aoe Static" cache type is not enabled on admin - do anything
        if (!Mage::app()->useCache('aoestatic')) {
            return array();
        }

        $result = array();
        foreach ($this->_getAdapterInstances() as $adapter) {
            /** @var Aoe_Static_Model_Cache_Adapter_Interface $adapter */
            $result = array_merge($result, $adapter->purgeAll());
        }
        return $result;
    }

    /**
     * Call purge on every adapter with given URLs
     *
     * @param array $urls
     * @param bool $queue
     * @return array
     */
    public function purge(array $urls, $queue = true)
    {
        // if "Aoe Static" cache type is not enabled on admin - do anything
        if (!Mage::app()->useCache('aoestatic')) {
            return array();
        }

        $urls = array_filter($urls);
        if ($this->getConfig()->useAsyncCache() && $queue) {
            // queue if async cache is enabled in config and not forced to purge directly
            $this->_getAsyncCacheHelper()->addJob(Aoe_Static_Helper_Data::MODE_PURGE_URLS, $urls, true);

            return array();
        } else {
            return $this->purgeDirectly($urls);
        }
    }

    /**
     * Purge urls with all enabled adapters
     *
     * @param array $urls
     * @return array
     */
    public function purgeDirectly(array $urls)
    {
        $result = array();
        foreach ($this->_getAdapterInstances() as $adapter) {
            $result = array_merge($result, $adapter->purge($urls));
        }

        return $result;
    }

    /**
     * Purge given tag(s)
     *
     * @param string|array $tags
     * @param bool         $withStore
     * @param bool         $queue
     * @return array
     */
    public function purgeTags($tags, $withStore = false, $queue = true)
    {
        // if "Aoe Static" cache type is not enabled on admin - do anything
        if (!Mage::app()->useCache('aoestatic')) {
            return array();
        }

        if (!is_array($tags)) {
            $tags = array($tags);
        }

        /** @var Aoe_Static_Model_Cache_Control $cacheControl */
        $cacheControl = Mage::getSingleton('aoestatic/cache_control');
        foreach ($tags as $k => $v) {
            $tags[$k] = $cacheControl->normalizeTag($v, $withStore);
        }

        if ($this->getConfig()->useAsyncCache() && $queue) {
            $this->_getAsyncCacheHelper()->addJob(Aoe_Static_Helper_Data::MODE_PURGE_TAGS, $tags, true);

            return array();
        } else {
            return $this->purgeTagsDirectly($tags);
        }
    }

    /**
     * Purge tags with all enabled adapters
     *
     * @param array $tags
     * @return array
     */
    public function purgeTagsDirectly(array $tags)
    {
        $result = array();
        foreach ($this->_getAdapterInstances() as $adapter) {
            $result = array_merge($result, $adapter->purgeTags($tags));
        }

        return $result;
    }

    /**
     * Trim explode
     *
     * @param $delimiter
     * @param $string
     * @param bool $removeEmptyValues
     * @return array
     */
    public function trimExplode($delimiter, $string, $removeEmptyValues = false)
    {
        $explodedValues = explode($delimiter, $string);
        $result = array_map('trim', $explodedValues);
        if ($removeEmptyValues) {
            $temp = array();
            foreach ($result as $value) {
                if ($value !== '') {
                    $temp[] = $value;
                }
            }
            $result = $temp;
        }
        return $result;
    }
}
