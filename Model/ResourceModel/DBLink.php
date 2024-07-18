<?php
namespace PayUIndia\Payu\Model\ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
 /**
 * Compatibility code for PHP 7.4
 * use \AllowDynamicProperties;
 * #[AllowDynamicProperties]
 *
*/
class DBLink extends AbstractDb
{
    const TABLE_NAME = 'payu_payment';    
    protected function _construct()
    {
        $this->_init(static::TABLE_NAME, 'entity_id');
    }
}
