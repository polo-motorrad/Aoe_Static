<?php

/**
 * Beforebodyend block
 *
 * @author Fabrizio Branca
 */
class Aoe_Static_Block_Beforebodyend extends Mage_Core_Block_Template
{
    /**
     * Get Aoe_Static ajax call url
     *
     * @return string
     */
    public function getAjaxCallUrl()
    {
        $params = Mage::app()->getStore()->isCurrentlySecure() ? array('_secure' => 1) : array();

        return Mage::getUrl('aoestatic/call/index', $params);
    }

    /**
     * Get full action name of current page
     *
     * @return string
     */
    public function getFullActionName()
    {
        return $this->getAction()->getFullActionName();
    }

    /**
     * Get id of current store
     *
     * @return int
     */
    public function getCurrentStoreId()
    {
        return Mage::app()->getStore()->getId();
    }

    /**
     * Get id of current website
     *
     * @return int
     */
    public function getCurrentWebsiteId()
    {
        return Mage::app()->getWebsite()->getId();
    }

    /**
     * Get current product (if there is one in registry) id, otherwise return 0
     *
     * @return int
     */
    public function getCurrentProductId()
    {
        $product = Mage::registry('product');
        if ($product && $product->getId()) {
            return $product->getId();
        }

        return 0;
    }
}
