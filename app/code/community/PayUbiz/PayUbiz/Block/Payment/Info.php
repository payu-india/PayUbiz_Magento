<?php
/**
 * Info.php
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
 * PayUbiz_Block_Payment_Info 
 */
class PayUbiz_PayUbiz_Block_Payment_Info extends Mage_Payment_Block_Info
{
    // {{{ _prepareSpecificInformation()
    /**
     * _prepareSpecificInformation 
     */
    protected function _prepareSpecificInformation( $transport = null )
    {
        $transport = parent::_prepareSpecificInformation( $transport );
        $payment = $this->getInfo();
        $pbInfo = Mage::getModel( 'payubiz/info' );
        
        if( !$this->getIsSecureMode() )
            $info = $pbInfo->getPaymentInfo( $payment, true );
        else
            $info = $pbInfo->getPublicPaymentInfo( $payment, true );

        return( $transport->addData( $info ) );
    }
    // }}}
}