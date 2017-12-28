<?php
/**
 * Form.php
 *
 * Copyright (c) 2011-2015 PayU India
 * @author     Ayush Mittal
 * @copyright  2011-2015 PayU India
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://www.payu.in
 * @category   PayUbiz
 * @package    PayUbiz
 */

/**
 * PayUbiz_Block_Form 
 */
class PayUbiz_PayUbiz_Block_Form extends Mage_Payment_Block_Form
{
    // {{{ _construct()
    /**
     * _construct() 
     */    
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate( 'payubiz/form.phtml' );
    }
    // }}}
}