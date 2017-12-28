<?php

namespace PayUIndia\Payu\Model;

use Magento\Sales\Api\Data\TransactionInterface;

class Payu extends \Magento\Payment\Model\Method\AbstractMethod {

    const PAYMENT_PAYU_CODE = 'payu';
    const ACC_BIZ = 'payubiz';
    const ACC_MONEY = 'payumoney';

    protected $_code = self::PAYMENT_PAYU_CODE;

    /**
     *
     * @var \Magento\Framework\UrlInterface 
     */
    protected $_urlBuilder;
    protected $_supportedCurrencyCodes = array(
        'AFN', 'ALL', 'DZD', 'ARS', 'AUD', 'AZN', 'BSD', 'BDT', 'BBD',
        'BZD', 'BMD', 'BOB', 'BWP', 'BRL', 'GBP', 'BND', 'BGN', 'CAD',
        'CLP', 'CNY', 'COP', 'CRC', 'HRK', 'CZK', 'DKK', 'DOP', 'XCD',
        'EGP', 'EUR', 'FJD', 'GTQ', 'HKD', 'HNL', 'HUF', 'INR', 'IDR',
        'ILS', 'JMD', 'JPY', 'KZT', 'KES', 'LAK', 'MMK', 'LBP', 'LRD',
        'MOP', 'MYR', 'MVR', 'MRO', 'MUR', 'MXN', 'MAD', 'NPR', 'TWD',
        'NZD', 'NIO', 'NOK', 'PKR', 'PGK', 'PEN', 'PHP', 'PLN', 'QAR',
        'RON', 'RUB', 'WST', 'SAR', 'SCR', 'SGF', 'SBD', 'ZAR', 'KRW',
        'LKR', 'SEK', 'CHF', 'SYP', 'THB', 'TOP', 'TTD', 'TRY', 'UAH',
        'AED', 'USD', 'VUV', 'VND', 'XOF', 'YER'
    );
    
    private $checkoutSession;
    /**
     * 
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
      public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \PayUIndia\Payu\Helper\Payu $helper,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Checkout\Model\Session $checkoutSession      
              
    ) {
        $this->helper = $helper;
        $this->orderSender = $orderSender;
        $this->httpClientFactory = $httpClientFactory;
        $this->checkoutSession = $checkoutSession;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );

    }

    public function canUseForCurrency($currencyCode) {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    public function getRedirectUrl() {
        return $this->helper->getUrl($this->getConfigData('redirect_url'));
    }

    public function getReturnUrl() {
        return $this->helper->getUrl($this->getConfigData('return_url'));
    }

    public function getCancelUrl() {
        return $this->helper->getUrl($this->getConfigData('cancel_url'));
    }

    /**
     * Return url according to environment
     * @return string
     */
    public function getCgiUrl() {
        $env = $this->getConfigData('environment');
        if ($env === 'production') {
            return $this->getConfigData('production_url');
        }
        return $this->getConfigData('sandbox_url');
    }
    public function buildCheckoutRequest() {
        $order = $this->checkoutSession->getLastRealOrder();
        $billing_address = $order->getBillingAddress();

        $params = array();
        $params["key"] = $this->getConfigData("merchant_key");
        if ($this->getConfigData('account_type') == self::ACC_MONEY) {
            $params["service_provider"] = $this->getConfigData("service_provider");
        }
        $params["txnid"] 		= $this->checkoutSession->getLastRealOrderId();
        $params["amount"] 		= round($order->getBaseGrandTotal(), 2);
        $params["productinfo"] 	= $this->checkoutSession->getLastRealOrderId();
        $params["firstname"] 	= $billing_address->getFirstName();
        $params["lastname"] 	= $billing_address->getLastname();
        $params["city"]         = $billing_address->getCity();
        $params["state"]        = $billing_address->getRegion();
        $params["zip"]          = $billing_address->getPostcode();
        $params["country"]      = $billing_address->getCountryId();
        $params["email"] 		= $order->getCustomerEmail();
        $params["phone"] 		= $billing_address->getTelephone();
        $params["curl"] 		= $this->getCancelUrl();
        $params["furl"] 		= $this->getReturnUrl();
        $params["surl"] 		= $this->getReturnUrl();

        $params["hash"] = $this->generatePayuHash($params['txnid'],$params['amount'], $params['productinfo'], $params['firstname'],$params['email']);
		
        return $params;
    }

    public function generatePayuHash($txnid, $amount, $productInfo, $name, $email) {
        $SALT = $this->getConfigData('salt');

        $posted = array(
            'key' => $this->getConfigData("merchant_key"),
            'txnid' => $txnid,
            'amount' => $amount,
            'productinfo' => $productInfo,
            'firstname' => $name,
            'email' => $email,
        );

        $hashSequence = 'key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10';

        $hashVarsSeq = explode('|', $hashSequence);
        $hash_string = '';
        foreach ($hashVarsSeq as $hash_var) {
            $hash_string .= isset($posted[$hash_var]) ? $posted[$hash_var] : '';
            $hash_string .= '|';
        }
        $hash_string .= $SALT;
        return strtolower(hash('sha512', $hash_string));
    }

    //validate response
    public function validateResponse($returnParams) {
		$order = $this->checkoutSession->getLastRealOrder();
		$SALT = $this->getConfigData('salt');
		
		$key = $returnParams['key'];
		$txnid = $this->checkoutSession->getLastRealOrderId();
		$amount = number_format($order->getBaseGrandTotal(), 2);
		$productInfo = 	$returnParams['productinfo'];
		$firstname    		= 	$returnParams['firstname'];
		$email        		=	$returnParams['email'];
		$keyString 	  		=  	$key.'|'.$txnid.'|'.$amount.'|'.$productInfo.'|'.$firstname.'|'.$email.'||||||||||';
		$keyArray 	  		= 	explode("|",$keyString);
		$reverseKeyArray 	= 	array_reverse($keyArray);
		$reverseKeyString	=	implode("|",$reverseKeyArray);
		
		if ((isset($returnParams['status']) && $returnParams['status'] == 'success')  && ($txnid == $returnParams['txnid'])) {
			$saltString     = $SALT.'|'.$returnParams['status'].'|'.$reverseKeyString;
			$sentHashString = strtolower(hash('sha512', $saltString));
			
			if(($sentHashString==$responseHashString) && ($amount == $returnParams['amount'])){
				return true;
			}
			else{
				return false;
			}
		}
		else{
			return false;
		}
		
        /* if ($returnParams['status'] == 'pending' || $returnParams['status'] == 'failure') {
            return false;
        }
        if ($returnParams['key'] != $this->getConfigData("merchant_key")) {
            return false;
        } */
		
		return true;
    }

    public function postProcessing(\Magento\Sales\Model\Order $order,
            \Magento\Framework\DataObject $payment, $response) {
        
        $payment->setTransactionId($response['txnid']);
        $payment->setTransactionAdditionalInfo('payu_mihpayid',
                $response['mihpayid']);
        $payment->setAdditionalInformation('payu_order_status', 'approved');
        $payment->addTransaction(TransactionInterface::TYPE_ORDER);
        $payment->setIsTransactionClosed(0);
        $payment->place();
        $order->setStatus('processing');
        $order->save();
    }
}