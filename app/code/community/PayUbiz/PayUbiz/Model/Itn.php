<?php
/**
 * Itn.php
 *
 * Copyright (c) 2011-2015 PayU india
 * 
 * @author     Ayush Mittal
 * @copyright  2011-2015 PayU India
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://www.payu.in
 * @category   PayUbiz
 * @package    PayUbiz
 */

/**
 * Mage_Paypal_Model_Itn
 */
class Mage_Paypal_Model_Itn
{
    // {{{ getWriteLog()
    /**
     * getWriteLog
     */
	public function getWriteLog( $data )
    {
		$text = "\n";
		$text .= "RESPONSE: From PayU[". date("Y-m-d H:i:s") ."]"."\n";
		
        foreach( $_REQUEST as $key => $val )
			$text .= $key."=>".$val."\n";

		$file = dirname( dirname( __FILE__ ) ) ."/Logs/notify.txt";
		
		$handle = fopen( $file, 'a' );
		fwrite( $handle, $text );
		fclose( $handle );
	}
    // }}}
}