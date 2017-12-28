<?php
/**
 *
 * Copyright (c) 2011-2015 PayU India
 * 
 * @author     Ayush Mittal
 * @copyright  2011-2015 PayU India
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://www.payu.in
 * @category   PayUbiz
 * @package    PayUbiz
 */

class PayUbiz_PayUbiz_Model_Source_DemoModes
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'test', 'label' => 'Test'),
            array('value' => 'live', 'label' => 'Live'),
        );
    }
}