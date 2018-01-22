<?php
/**
 * Standard.php
 * 
 * @author     payubiz
 * @copyright  2011-2017 PayU India
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link       http://www.payu.in
 * @category   PayUbiz
 * @package    PayUbiz_PayUbiz
 */

/**
 * PayUbiz_PayUbiz_Model_Standard
 */
class PayUbiz_PayUbiz_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'payubiz';
    protected $_formBlockType = 'payubiz/form';
    protected $_infoBlockType = 'payubiz/payment_info';
    protected $_order;
    
    protected $_isGateway              = true;
    protected $_canAuthorize           = true;
    protected $_canCapture             = true;
    protected $_canCapturePartial      = false;
    protected $_canRefund              = false;
    protected $_canVoid                = true;
    protected $_canUseInternal         = true;
    protected $_canUseCheckout         = true;
    protected $_canUseForMultishipping = true;
    protected $_canSaveCc              = false;

    public function getCheckout()
    {
        return Mage::getSingleton( 'checkout/session' );
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }
  
    public function getConfig()
    {
        return Mage::getSingleton( 'payubiz/config' );
    }
   
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl( 'payubiz/redirect/redirect', array( '_secure' => true ) );
    }
 
    public function getSuccessUrl()
    {      
        return Mage::getUrl( 'payubiz/redirect/success', array( '_secure' => true ) );
    }
 
    public function getCancelUrl()
    {
        return Mage::getUrl( 'payubiz/redirect/cancel', array( '_secure' => true ) );
    }
  
    public function getfailureUrl()
    {
        return Mage::getUrl( 'payubiz/redirect/failure', array( '_secure' => true ) );
    }
  
    public function getRealOrderId()
    {
        return Mage::getSingleton( 'checkout/session' )->getLastRealOrderId();
    }
  
    public function getNumberFormat( $number )
    {
        return number_format( $number, 2, '.', '' );
    }

    public function getTotalAmount( $order )
    {
        if( $this->getConfigData( 'use_store_currency' ) )
            $price = $this->getNumberFormat( $order->getGrandTotal() );
        else
            $price = $this->getNumberFormat( $order->getBaseGrandTotal() );

        return $price;
    }
 
    public function getStoreName()
    {
        $store_info = Mage::app()->getStore();
        return $store_info->getName();
    }
  
    public function getStandardCheckoutFormFields()
    {
        // Variable initialization
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel( 'sales/order' )->loadByIncrementId( $orderIncrementId );
        $description = '';

       $transaction_mode = $this->getConfigData('trans_mode');
       $merchant_key = $this->getConfigData('merchant_key');   
       $salt = $this->getConfigData('salt');   
       $payment_gateway = $this->getConfigData('Pg');   
       $bankcode = $this->getConfigData('bankcode');  

       $currency_convertor = $this->getConfigData('currency_convertor');   

           $txnid = $orderIncrementId;

        // Create description
        foreach( $order->getAllItems() as $items )
        {
            $totalPrice = $this->getNumberFormat( $items->getQtyOrdered() * $items->getPrice() );

            $description .=
                $this->getNumberFormat( $items->getQtyOrdered() ) .
                ' x '. $items->getName() .
                ' @ '. $order->getOrderCurrencyCode() . $this->getNumberFormat( $items->getPrice() ) .
                ' = '. $order->getOrderCurrencyCode() . $totalPrice .'; ';
        }
        $description .= 'Shipping = '. $order->getOrderCurrencyCode() . $this->getNumberFormat( $order->getShippingAmount() ).';';
        $description .= 'Total = '. $order->getOrderCurrencyCode() . $this->getTotalAmount( $order ).';'; 

        /**********************************************************************************/ 

        //$order = Mage::getModel('sales/order')->load (MY_ORDER_ID); 
        //If they have no customer id, they're a guest. 
        if($order->getCustomerId() === NULL)
        { 
          $billingaddress =  $order->getBillingAddress()->getFirstname(); 
        } //else, they're a normal registered user. 
        else { 
          $customer = Mage::getModel('customer/customer')->load($order->getCustomerId()); 
         $billingaddress =  $customer->getDefaultBillingAddress()->getFirstname(); 
        } 

        /**********************************************************************************/ 

        //$billingaddress = $customer->getPrimaryBillingAddress()->getData();    
        $countryName = Mage::getModel('directory/country')->load($order->getBillingAddress()->getData('country_id'))->getName();   

        if($currency_convertor != 0)
        {        
        $getAmount = file_get_contents("http://www.google.com/finance/converter?a=".$this->getTotalAmount( $order )."&from=".$order->getData('base_currency_code')."&to=INR"); 

        $getAmount = explode("<span class=bld>",$getAmount);
        $getAmount = explode("</span>",$getAmount[1]);
        $convertedAmount = preg_replace("/[^0-9\.]/", null, $getAmount[0]);
        $calculatedAmount_INR = round($convertedAmount,2);       
       } else {
         $calculatedAmount_INR = $this->getTotalAmount( $order ); 
       }
        $billingaddress = $order->getBillingAddress()->getData();
        // Construct data for the form
        $data = array(
            // Merchant details
            'key' => $merchant_key,
            'txnid' => $txnid,
            'amount' => $calculatedAmount_INR,
            'productinfo' => 'Product Information',

             // Buyer details
            'firstname' => $order->getData('customer_firstname'),          
            'Lastname' =>  $order->getData('customer_lastname'),
            'City' => $billingaddress['city'],
            'State' => $billingaddress['region'],             
            'Country' => $countryName,
            'Zipcode' => $billingaddress['postcode'],          
            'email' => $order->getData('customer_email'),            
            'phone' => $billingaddress['telephone'],
            'surl' => $this->getSuccessUrl(),
            'furl' => $this->getfailureUrl(),
            'curl' => $this->getCancelUrl(),
        );
            $data['pg'] = $payment_gateway;
            $data['bankcode'] = $bankcode;

           $data['Hash']         =   strtolower(hash('sha512', $merchant_key.'|'.$txnid.'|'.$data['amount'].'|'.
 $data['productinfo'].'|'.$data['firstname'].'|'.$data['email'].'|||||||||||'.$salt));   

	/* if(PB_DEBUG) {
	//Mage::log('payubiz'.Zend_Debug::dump($data, null, false), null, 'payubiz.log');
	} */
        return( $data );
    }

    public function initialize( $paymentAction, $stateObject )
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState( $state );
        $stateObject->setStatus( 'pending_payment' );
        $stateObject->setIsNotified( false );
    }
    public function getResponseOperation($response)
    {     
           $order = Mage::getModel('sales/order');

		$debug_mode = 0;
		$orderIncrementId = $this->getCheckout()->getLastRealOrderId();
		$transaction_mode = $this->getConfigData('trans_mode');
        $txnid_magepayu = $orderIncrementId;       
        $merchant_key = $this->getConfigData('merchant_key');   
         $salt = $this->getConfigData('salt');  

        if(isset($response['status']))
        {
           //$txnid=$response['txnid'];
           $orderid=$txnid_magepayu;

           if(($response['status']=='success' || $response['status']=='in progress') && ($txnid_magepayu==$response['txnid']))
            {
                $status=$response['status'];
                $order->loadByIncrementId($orderid);
                $billing = $order->getBillingAddress();
				$amount = $order->getGrandTotal();
                $productinfo = $response['productinfo'];  
                $firstname   = $order->getBillingAddress()->getFirstname();
                $email       = $order->getCustomerEmail();
                $keyString='';
                $Udf1 = $response['udf1'];
                $Udf2 = $response['udf2'];
                $Udf3 = $response['udf3'];
                $Udf4 = $response['udf4'];
                $Udf5 = $response['udf5'];
                $Udf6 = $response['udf6'];
                $Udf7 = $response['udf7'];
                $Udf8 = $response['udf8'];
                $Udf9 = $response['udf9'];
                $Udf10 = $response['udf10'];
 
				$amount = number_format((float)$amount, 2,'.','');
                $keyString =  $merchant_key.'|'.$txnid_magepayu.'|'.$amount.'|'.$productinfo.'|'.$firstname.'|'.$email.'|'.$Udf1.'|'.$Udf2.'|'.$Udf3.'|'.$Udf4.'|'.$Udf5.'|'.$Udf6.'|'.$Udf7.'|'.$Udf8.'|'.$Udf9.'|'.$Udf10;    
                
                $keyArray = explode("|",$keyString);
                $reverseKeyArray = array_reverse($keyArray);
                $reverseKeyString=implode("|",$reverseKeyArray);
                $saltString     = $salt.'|'.$status.'|'.$reverseKeyString;
                $sentHashString = strtolower(hash('sha512', $saltString));
                 $responseHashString=$_REQUEST['hash'];
 
                if(($sentHashString==$responseHashString) && ($amount==$response['amount']))
                {
                        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
						
						// ---------------Auto Invoice Start---------------
						$invoice = $order->prepareInvoice();
						if($invoice->getTotalQty()){
							$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
							//$invoice->getOrder()->setCustomerNoteNotify(false);
							$invoice->register();
							Mage::getModel('core/resource_transaction')
									->addObject($invoice)
									->addObject($invoice->getOrder())
									->save();
							$invoice->sendEmail(false);
						}
						//---------------Auto Invoice Finish---------------
						
                        $order->save();
                        $order->sendNewOrderEmail();
                }
                else {
                    $order->setState(Mage_Sales_Model_Order::STATE_NEW, true);
                    $order->cancel()->save();
					Mage::throwException('Data has been tampered. Transaction Cancelled' );
                    }
                
                if ($debug_mode==1) {
                    $debugId=$response['udf1'];  
                    $data = array('response_body'=>implode(",",$response));
                    $model = Mage::getModel('payucheckout/api_debug')->load($debugId)->addData($data);
                    $model->setId($id)->save();
                  }
               }else if($response['status']=='failure'){
					 $order->loadByIncrementId($orderid);
					   $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
					  // Inventory updated 
					   $order_id=$response['txnid'];
					   $this->updateInventory($order_id);
					   $order->cancel()->save();
                    }else{
						$order->loadByIncrementId($orderid);
					   $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
					  // Inventory updated 
					   $order_id=$response['id'];
					   $this->updateInventory($order_id);
					   $order->cancel()->save();
					   Mage::throwException('Data has been tampered. Transaction Cancelled' );
					}
					

           if($response['status']=='failure')
           {
               $order->loadByIncrementId($orderid);
               $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
               // Inventory updated 
               $this->updateInventory($orderid);
               $order->cancel()->save();
               if ($debug_mode==1) {
                $debugId=$response['udf1'];
                        $data = array('response_body'=>implode(",",$response));
                    $model = Mage::getModel('payucheckout/api_debug')->load($debugId)->addData($data);
                    $model->setId($id)->save();
                  }
           }
           else  if($response['status']=='pending')
           {
               $order->loadByIncrementId($orderid);
               $order->setState(Mage_Sales_Model_Order::STATE_NEW, true);
               // Inventory updated  
               $this->updateInventory($orderid);
               $order->cancel()->save();
               if ($debug_mode==1) {
                $debugId=$response['udf1'];
                        $data = array('response_body'=>implode(",",$response));
                    $model = Mage::getModel('payucheckout/api_debug')->load($debugId)->addData($data);
                    $model->setId($id)->save();
                  }
           }
        }
        else {
           $order->loadByIncrementId($orderid);
           $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
          // Inventory updated 
           $order_id=$response['id'];
           $this->updateInventory($order_id);
           $order->cancel()->save();
        }       
    }

    public function updateInventory($order_id)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
        $items = $order->getAllItems();
        foreach ($items as $itemId => $item)
        {
           $ordered_quantity = $item->getQtyToInvoice();
           $sku=$item->getSku();
           $product = Mage::getModel('catalog/product')->load($item->getProductId());
           $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId())->getQty();
           $updated_inventory=$qtyStock + $ordered_quantity;                    
           $stockData = $product->getStockItem();
           $stockData->setData('qty',$updated_inventory);
           $stockData->save(); 
       } 
    }
  
    public function getPayUbizUrl()
    {
        switch( $this->getConfigData( 'trans_mode' ) )
        {
            case 'test':
                $url = 'https://test.payu.in/_payment';
                break;
            case 'live':
            default :
                $url = 'https://secure.payu.in/_payment';
                break;
        }
        return( $url );
    }   
}