<?php
/**
 * Request.php
 *
 * Copyright (c) 2010-2011 PayU India
 * 
 * @author     Ayush Mittal
 * @copyright  2011-2015 PayU India
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://www.payu.in
 * @category   PayUbiz
 * @package    PayUbiz
 */


class PayUbiz_PayUbiz_Block_Request extends Mage_Core_Block_Abstract
{
    
    protected function _toHtml()
    {
        $standard = Mage::getModel( 'payubiz/standard' );
        $form = new Varien_Data_Form();
        $form->setAction( $standard->getPayUbizUrl() )
            ->setId( 'payubiz_checkout' )
            ->setName( 'payubiz_checkout' )
            ->setMethod( 'POST' )
            ->setUseContainer( true );
        
        foreach( $standard->getStandardCheckoutFormFields() as $field=>$value )
            $form->addField( $field, 'hidden', array( 'name' => $field, 'value' => $value, 'size' => 200 ) );
        
        $html = '<html><body>';
        $html.= $this->__( 'You will be redirected to PayUbiz Payment Gateway in a few seconds.' );
        $html.= $form->toHtml();
		#echo $html;exit;
        $html.= '<script type="text/javascript">document.getElementById( "payubiz_checkout" ).submit();</script>';
        $html.= '</body></html>';
       return $html;
    }


}