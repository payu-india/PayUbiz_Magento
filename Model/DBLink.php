<?php

namespace PayUIndia\Payu\Model;

use Magento\Cron\Exception;
use Magento\Framework\Model\AbstractModel;

class DBLink extends AbstractModel
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\PayUIndia\Payu\Model\ResourceModel\DBLink::class);
    }
    
}
