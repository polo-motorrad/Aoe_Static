<?php

/**
 * CallController
 * Renders the block that are requested via an ajax call
 *
 * @author Fabrizio Branca <fabrizio.branca@aoemedia.de>
 */
class Aoe_Static_CallController extends Mage_Core_Controller_Front_Action
{
    /**
     * This action is called by an ajax request
     *
     * @author Fabrizio Branca <fabrizio.branca@aoemedia.de>
     */
    public function indexAction()
    {
        $response = array();
        $response['sid'] = Mage::getModel('core/session')->getEncryptedSessionId();

        $currentProductId = $this->getRequest()->getParam('currentProductId', false);
        if ($currentProductId) {
            Mage::getSingleton('catalog/session')->setLastViewedProductId($currentProductId);

            $product = Mage::getModel('catalog/product')->load($currentProductId);
            if ($product) {
                Mage::register('product', $product);
            }
        }

        $this->loadLayout();
        $layout = $this->getLayout();

        $requestedBlockNames = $this->getRequest()->getParam('blocks');
        if (is_array($requestedBlockNames)) {
            foreach ($requestedBlockNames as $requestedBlockName) {
                $tmpBlock = $layout->getBlock($requestedBlockName);
                if ($tmpBlock) {
                    $response['blocks'][$requestedBlockName] = $tmpBlock->toHtml();
                } else {
                    $response['blocks'][$requestedBlockName] = 'BLOCK NOT FOUND';
                }
            }
        }
        $this->getResponse()->setBody(Zend_Json::encode($response));
    }
}
