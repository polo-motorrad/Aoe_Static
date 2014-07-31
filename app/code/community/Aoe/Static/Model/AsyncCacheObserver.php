<?php

class Aoe_Static_Model_AsyncCacheObserver
{
    /**@+
     * Purge urls/tags message patterns
     *
     * @var string
     */
    const MODE_PURGE_URLS_MESSAGE_PATTERN = '[ASYNCCACHE URL] MODE: %s, DURATION: %s sec, TAGS: %s';
    const MODE_PURGE_TAGS_MESSAGE_PATTERN = '[ASYNCCACHE TAG] MODE: %s, DURATION: %s sec, TAGS: %s';
    /**@-*/

    /**
     * Log errors to the system.log
     *
     * @param array $errors
     */
    protected function _logErrors(array $errors)
    {
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                Mage::log($error);
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function processJobs(Varien_Event_Observer $observer)
    {
        /** @var $jobCollection Aoe_AsyncCache_Model_JobCollection */
        $jobCollection = $observer->getData('jobCollection');
        if (!$jobCollection) {
            return;
        }

        /** @var Aoe_Static_Helper_Data $helper */
        $helper = Mage::helper('aoestatic');

        foreach ($jobCollection as $job) {
            /** @var $job Aoe_AsyncCache_Model_Job */
            if (!$job->getIsProcessed() &&
                ($job->getMode() == Aoe_Static_Helper_Data::MODE_PURGE_URLS
                 || $job->getMode() == Aoe_Static_Helper_Data::MODE_PURGE_TAGS
                )
            ) {
                $startTime = time();
                switch ($job->getMode()) {
                    case Aoe_Static_Helper_Data::MODE_PURGE_URLS:
                        $messagePattern = self::MODE_PURGE_URLS_MESSAGE_PATTERN;
                        $errors = $helper->purgeDirectly($job->getTags());
                        break;
                    case Aoe_Static_Helper_Data::MODE_PURGE_TAGS:
                        $messagePattern = self::MODE_PURGE_TAGS_MESSAGE_PATTERN;
                        $errors = $helper->purgeTagsDirectly($job->getTags());
                        break;
                }
                $job->setDuration(time() - $startTime);
                $job->setIsProcessed(true);

                $this->_logErrors($errors);
                Mage::log(
                    sprintf($messagePattern, $job->getMode(), $job->getDuration(), implode(', ', $job->getTags()))
                );
            }
        }
    }
}
