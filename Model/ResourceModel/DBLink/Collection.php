<?php
namespace PayUIndia\Payu\Model\ResourceModel\DBLink;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;


class Collection extends AbstractCollection
{
    /**
     * Initialize resource collection
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init('PayUIndia\Payu\Model\DBLink', 'PayUIndia\Payu\Model\ResourceModel\DBLink');
    }
}