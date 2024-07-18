<?php
namespace PayUIndia\Payu\Setup;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PayUIndia\Payu\Model\ResourceModel\DBLink;

/**
 * Compatibility code for PHP 7.4
 * use \AllowDynamicProperties;
 * #[AllowDynamicProperties]
 *
*/

class UpgradeSchema implements  UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup,ModuleContextInterface $context)
    {
        $setup->startSetup();
        $table = $setup->getConnection()->newTable($setup->getTable(DBLink::TABLE_NAME));
        $table->addColumn(
                'entity_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'primary'  => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'quote_id',
                Table::TYPE_INTEGER,
                [
                    'identity' => true,
                    'unique'   => true,
                    'nullable' => false
                ]
            )
            ->addColumn(
                'order_id',
                Table::TYPE_INTEGER,
                [
                    'nullable' => true
                ]
            )
            ->addColumn(
                'increment_order_id',
                Table::TYPE_TEXT,
                32,
                [
                    'nullable' => true
                ]
            )            
            ->addColumn(
                'payu_payment_id',
                Table::TYPE_TEXT,
                50,
                [
                    'nullable' => true
                ]
            )            
            ->addColumn(
                'payu_amount',
                Table::TYPE_INTEGER,
                20,
                [
                    'nullable' => true,
                    'comment'  => 'Payu payment amount'
                ]
            )            
            ->addColumn(
                'order_placed',
                Table::TYPE_BOOLEAN,
                1,
                [
                    'nullable' => false,
                    'default' => 0
                ]
            )            
            ->addColumn(
                'email',
                Table::TYPE_TEXT,
                255,
                [
                    'nullable' => true,
                    'comment'  => 'payment email'
                ]
            )
            ->addColumn(
                'contact',
                Table::TYPE_TEXT,
                25,
                [
                    'nullable' => true,
                    'comment'  => 'payment contact'
                ]
            )
            ->addIndex(
                'quote_id',
                ['quote_id', 'payu_payment_id'],
                [
                    'type'      => AdapterInterface::INDEX_TYPE_UNIQUE,
                    'nullable'  => false,
                ]
            )
            ->addIndex(
                'increment_order_id',
                ['increment_order_id'],
                [
                    'type'      => AdapterInterface::INDEX_TYPE_UNIQUE,
                ]
            );
        $setup->getConnection()->createTable($table);        
        $setup->endSetup();
    }
}
