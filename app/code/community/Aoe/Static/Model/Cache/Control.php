<?php

class Aoe_Static_Model_Cache_Control
{
    /** @var string */
    const TAG_DELIMITER = ' ';

    /** @var string */
    const PART_DELIMITER = '-';

    /**
     * Array of tag types Aoe_Static currently supports
     *
     * @var array
     */
    protected static $_supportedTagTypes = array('product', 'category', 'page', 'block');

    /**
     * Tags for tag-based purging
     *
     * @var array
     */
    protected $_tags = array();

    /**
     * Minimum maxage
     *
     * @var int
     */
    protected $_maxAge = 0;

    /**
     * Switch to disable sending out of cache headers
     *
     * @var bool
     */
    protected $_enabled = true;

    /**
     * @return $this
     */
    public function enable()
    {
        $this->_enabled = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function disable()
    {
        $this->_enabled = false;
        return $this;
    }

    /**
     * Compute minimum max-age
     *
     * @param int|array $maxAge
     */
    public function addMaxAge($maxAge)
    {
        if (!is_array($maxAge)) {
            $maxAge = array($maxAge);
        }

        foreach ($maxAge as $timestamp) {
            if ($timestamp > 0 && (!$this->_maxAge || ($timestamp < $this->_maxAge))) {
                $this->_maxAge = $timestamp;
            }
        }
    }

    /**
     * Load specific max-age from database
     *
     * @param $request Mage_Core_Controller_Request_Http
     */
    public function addCustomUrlMaxAge($request)
    {
        // apply custom max-age from db
        $urls = array($request->getRequestString());
        $alias = $request->getAlias(Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS);
        if ($alias) {
            $urls[] = $alias;
        }
        /** @var $customUrlModel Aoe_Static_Model_CustomUrl */
        $customUrlModel = Mage::getModel('aoestatic/customUrl');
        $customUrlModel->setStoreId(Mage::app()->getStore()->getId());
        $customUrlModel->loadByRequestPath($urls);

        if ($customUrlModel->getId() && $customUrlModel->getMaxAge()) {
            $this->addMaxAge($customUrlModel->getMaxAge());
        }
    }

    /**
     * Normalize tag and add to current cache
     *
     * @param string $tag
     */
    protected function _normalizeAndAddTag($tag)
    {
        $appendStoreId = !Mage::app()->isSingleStoreMode();
        $tag = $this->normalizeTag($tag, $appendStoreId);

        if (!isset($this->_tags[$tag])) {
            $this->_tags[$tag] = 0;
        }
        $this->_tags[$tag]++;
    }

    /**
     * Add tag(s) to current cache
     *
     * @param $tags array|string
     * @return $this
     */
    public function addTag($tags)
    {
        if (!is_array($tags)) {
            $tags = array($tags);
        }

        foreach ($tags as $tag) {
            $this->_normalizeAndAddTag($tag);
        }

        return $this;
    }

    /**
     * Parse the requested tag and return a clean version
     *
     * @param string $tag
     * @param bool   $appendStoreId
     *
     * @return string
     */
    public function normalizeTag($tag, $appendStoreId = false)
    {
        if (is_array($tag)) {
            $tag = implode(self::PART_DELIMITER, $tag);
        }

        $tag = str_replace(array("\r\n", "\r", "\n", self::TAG_DELIMITER), '_', strtoupper(trim($tag)));

        if ($appendStoreId) {
            $tag .= self::PART_DELIMITER . Mage::app()->getStore()->getId();
        }

        return $tag;
    }

    /**
     * Apply cache-headers if enabled is true (default)
     *
     * @return $this
     */
    public function applyCacheHeaders()
    {
        if ($this->_enabled && $this->_maxAge) {
            $maxAge = (int) $this->_maxAge;
            $response = Mage::app()->getResponse();
            $response->setHeader('Cache-Control', 'max-age=' . $maxAge, true);
            $response->setHeader('Expires', gmdate("D, d M Y H:i:s", time() + $maxAge) . ' GMT', true);
            $response->setHeader('X-Tags', implode(self::TAG_DELIMITER, $this->_getTags()));
            $response->setHeader('X-Aoestatic', 'cache', true);
            $response->setHeader('X-Aoestatic-Lifetime', (int) $maxAge, true);
        }

        return $this;
    }

    /**
     * Get current category layer
     *
     * @return Mage_Catalog_Model_Layer
     */
    protected function _getLayer()
    {
        $layer = Mage::registry('current_layer');
        if ($layer) {
            return $layer;
        }

        return Mage::getSingleton('catalog/layer');
    }

    /**
     * Collect all product related tags
     */
    protected function _collectProductTags()
    {
        if (Mage::registry('product')) {
            $this->addTag('product-' . Mage::registry('product')->getId());
        }

        $layer = $this->_getLayer();
        if ($layer && $layer->getCurrentCategory()->getId() != $layer->getCurrentStore()->getRootCategoryId()
            && $layer->apply()->getProductCollection()
        ) {
            $ids = $layer->getProductCollection()->getLoadedIds();
            $tags = array();
            foreach ($ids as $id) {
                $tags[] = 'product-' . $id;
            }
            $this->addTag($tags);
        }
    }

    /**
     * Collect all category related tags
     */
    protected function _collectCategoryTags()
    {
        if (Mage::registry('current_category')) {
            /** @var Mage_Catalog_Model_Category $currentCategory */
            $currentCategory = Mage::registry('current_category');
            $this->addTag('category-' . $currentCategory->getId());
        }
    }

    /**
     * Collect all tags from all generated blocks
     *
     * @param Mage_Core_Controller_Varien_Action $controllerAction
     */
    protected function _collectBlockTags(Mage_Core_Controller_Varien_Action $controllerAction)
    {
        $blocks = $controllerAction->getLayout()->getAllBlocks();
        foreach ($blocks as $block) {
            $this->collectTagsFromBlock($block);
        }
    }

    /**
     * Collect various possible tags from current products and category/layer pages
     *
     * @param Mage_Core_Controller_Varien_Action $controllerAction
     * @return $this
     */
    public function collectTags(Mage_Core_Controller_Varien_Action $controllerAction)
    {
        $this->_collectProductTags();
        $this->_collectCategoryTags();
        $this->_collectBlockTags($controllerAction);

        return $this;
    }

    /**
     * Collect all tags from block
     *
     * @param Mage_Core_Block_Abstract $block
     */
    public function collectTagsFromBlock(Mage_Core_Block_Abstract $block)
    {
        if ($block instanceof Mage_Cms_Block_Block) {
            if ($block->getBlock() && is_numeric($block->getBlock()->getId())) {
                $this->addTag('block-' . $block->getBlock()->getId());
            } elseif ($block->getBlockId() && is_numeric($block->getBlockId())) {
                $this->addTag('block-' . $block->getBlockId());
            }
        } elseif ($block instanceof Mage_Cms_Block_Page) {
            if ($block->getPageId()) {
                $this->addTag('page-' . $block->getPageId());
            } elseif ($block->getPage()) {
                $this->addTag('page-' . $block->getPage()->getId());
            } else {
                $this->addTag('page-' . Mage::getSingleton('cms/page')->getId());
            }
        } elseif (($block instanceof Mage_Catalog_Block_Product_Abstract) && $block->getProductCollection()) {
            $tags = array();
            foreach ($block->getProductCollection()->getLoadedIds() as $id) {
                $tags[] = 'product-' . $id;
            }
            $this->addTag($tags);
        }

        $blockCacheTags = $block->getCacheTags();
        $this->addTag($this->filterAndNormalizeTags($blockCacheTags));
    }

    /**
     * Filter tags using self::$_supportedTagTypes and normalize them to needed format
     *
     * @param array $tags
     * @return array
     */
    public function filterAndNormalizeTags(array $tags)
    {
        $normalizedTags = array();
        foreach ($tags as $tag) {
            // catalog_product_100 or catalog_category_186
            $tagFields = explode('_', $tag);
            if (count($tagFields) == 3) {
                if (in_array($tagFields[1], self::$_supportedTagTypes)) {
                    $normalizedTags[] = $this->normalizeTag(array($tagFields[1], $tagFields[2]), false);
                }
            }
        }

        return $normalizedTags;
    }

    /**
     * Sort tags and return
     *
     * @return array
     */
    protected function _getTags()
    {
        $tags = array_keys($this->_tags);
        sort($tags);

        return $tags;
    }
}
