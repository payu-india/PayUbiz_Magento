<?php

namespace PayUIndia\Payu\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Exception\LocalizedException;


class Payu extends AbstractHelper
{
    private $session;
    private $customerSession;
    private $quote;
    private $quoteManagement;
    private $orderSender;
    private $_storeManager;
    private $customerFactory;
    private $customerRepository;
    private $orderService;
    private $cartManagement;
    protected $order;
    protected $scopeConfig;
    protected $shipconfig;
    protected $payuEventLog;
    protected $payuEventCollection;
    protected $payuWebhook;
    protected $payuWebhookCollection;
    protected $customerCollectionFactory;



    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Model\Service\OrderService $orderService,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Shipping\Model\Config $shipconfig

    ) {
        $this->session = $session;
        $this->quote = $quote;
        $this->order =  $order;
        $this->quoteManagement = $quoteManagement;
        $this->cartManagement = $cartManagement;
        $this->customerSession = $customerSession;
        $this->_storeManager = $storeManager;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->orderService = $orderService;
        $this->shipconfig = $shipconfig;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function cancelCurrentOrder($comment)
    {
        $order = $this->session->getLastRealOrder();
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
            return true;
        }
        return false;
    }

    public function restoreQuote()
    {
        return $this->session->restoreQuote();
    }

    public function getUrl($route, $params = [])
    {
        return $this->_getUrl($route, $params);
    }

    public function createTempOrder($txnId)
    {
        $quote = $this->session->getQuote();

        if($this->scopeConfig->getValue('payment/payu/paymentaction')=='expresscheckout'){
            $store = $this->_storeManager->getStore();
            $websiteId = $this->_storeManager->getStore()->getWebsiteId();
            $customer=$this->customerFactory->create();
            $customer->setWebsiteId($websiteId);

            if($this->customerSession->isLoggedIn()) {
                $email = $this->customerSession->getCustomer()->getEmail();
                $fname = $this->customerSession->getCustomer()->getFirstname() ?? '';
                $lname = $this->customerSession->getCustomer()->getLastname() ?? '';
            }else{
                $email = 'guestuser'.uniqid().time().'@gmail.com';
                $fname = 'fname';
                $lname = 'lname';
            }
            $customer->loadByEmail($email);
            if(!$customer->getEntityId()){
                $quote->setCustomerEmail($email);
                $quote->setCustomerIsGuest(true);
            }else{
                $customer= $this->customerRepository->getById($customer->getEntityId());
                $quote->assignCustomer($customer);
            }
            $address=$quote->getBillingAddress()->getData();

            $address  = [
                'firstname'    => 'guest',
                'lastname'     => 'user',
                'street' => 'street',
                'city' => 'city',
                'country_id' => 'US',
                'region' => 'xxx',
                'region_id' => '1',
                'postcode' => 'XXXXXX',
                'telephone' => '0123456789',
                'fax' => '',
                'save_in_address_book' => 0
            ];

            //Set Address to quote
            $quote->getBillingAddress()->addData($address);
            $quote->getShippingAddress()->addData($address);

            $activeCarriers = $this->getShippingMethods();

            $shippingAddress=$quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setShippingMethod($activeCarriers[0]['value'][0]['value']);

            $quote->setInventoryProcessed(false);
            $quote->setPaymentMethod('payu');
            $quote->save();
            $quote->getPayment()->importData(['method' => 'payu']);
            $quote->collectTotals()->save();
        }else{
            if($this->customerSession->isLoggedIn()) {
                $email = $this->customerSession->getCustomer()->getEmail();
                $fname = $this->customerSession->getCustomer()->getFirstname() ?? '';
                $lname = $this->customerSession->getCustomer()->getLastname() ?? '';
            }else{
                $email = 'guestuser'.uniqid().time().'@gmail.com';
                $fname = 'fname';
                $lname = 'lname';
                $quote->setCustomerIsGuest(true);
                
            }
            $quote->setCustomerEmail($email);
            $quote->setInventoryProcessed(false);
            $quote->setPaymentMethod('payu');
            $quote->save();
            $quote->getPayment()->importData(['method' => 'payu']);
            $quote->collectTotals()->save();
        }

        // Create Order From Quote
        $orderid = $this->cartManagement->placeOrder($quote->getId());
        $m2order = $this->order->load($orderid);
        $m2order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $m2order->setStatus('pending_payment');
        $m2order->setIsNotified(false);
        $m2order->setTxnid($txnId)->save();
        $increment_id = $m2order->getRealOrderId();
        $m2order->setCanSendNewEmailFlag(false)->save();
        if($m2order->getEntityId()){
            $result['order_id']= $m2order->getRealOrderId();
        }else{
            $result=['error'=>1,'msg'=>'erro message'];
        }
        return $result;
    }

    public function getShippingMethods() {
        $activeCarriers = $this->shipconfig->getActiveCarriers();

        foreach($activeCarriers as $carrierCode => $carrierModel) {
            $options = array();

            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $carrierCode . '_' . $methodCode;
                    $options[] = array('value' => $code, 'label' => $method);
                }
                $carrierTitle = $this->scopeConfig
                    ->getValue('carriers/'.$carrierCode.'/title');
            }

            $methods[] = array('value' => $options, 'label' => $carrierTitle);
        }

        return $methods;
    }

    public function getConfigData($value)
    {
        return $this->scopeConfig->getValue(
            'payment/payu/' . $value,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function updateOrderFromResponse($order,$params)
    {
        if(isset($params['offer']) && isset($params['offer_availed']) && isset($params['transaction_offer']))
        {
            $offerArr = $params['transaction_offer'];
            if (!is_array($offerArr)) {
                $offerArr = json_decode($offerArr, true);
            }
            if(isset($offerArr['offer_data'])){
                $des=$offerArr['offer_data'];
                $description=$des[0];
                $discountDescription="Title - ".$description['offer_title']." | Offer Key - ".$description['offer_key']." |  Type - ".$description['offer_type'];
                $customDiscount=$offerArr['discount_data']['total_discount'];
                if($customDiscount > 0){
                    $this->setDiscount($order,$customDiscount,$discountDescription);
                }
            }

        }else{
            if(isset($params['disc']) && $params['disc']>0)
            {
                $discountDescription='Payu Offer';
                $customDiscount=$params['disc'];
                $this->setDiscount($order,$customDiscount,$discountDescription);
            }
        }

    }

    public function setDiscount($order,$customDiscount,$discountDescription)
    {
        $total=$order->getBaseSubtotal();
        $order->setDiscountAmount($customDiscount);
        $order->setBaseDiscountAmount($customDiscount);
        $order->setBaseGrandTotal($order->getBaseGrandTotal()-$customDiscount);
        $order->setGrandTotal($order->getGrandTotal()-$customDiscount);
        $order->setDiscountDescription($discountDescription);
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $shippingAddress->setDiscountAmount($customDiscount);
            $shippingAddress->setDiscountDescription($discountDescription);
            $shippingAddress->setBaseDiscountAmount($customDiscount);
        }
        $orderBillingAddress = $order->getBillingAddress();
        $orderBillingAddress->setDiscountAmount($customDiscount);
        $orderBillingAddress->setDiscountDescription($discountDescription);
        $orderBillingAddress->setBaseDiscountAmount($customDiscount);

        $order->setSubtotal((float) $order->getSubTotal());
        $order->setBaseSubtotal((float) $order->getBaseSubtotal());
        $order->setGrandTotal((float)  $order->getGrandTotal());
        $order->setBaseGrandTotal((float) $order->getBaseGrandTotal());
        $order ->save();
      

        $order->setBaseTotalInvoiced($order->getGrandTotal());
        $order->setTotalInvoiced($order->getGrandTotal());
        $payment=$order->getpayment();
        $payment->setBaseAmountPaid($order->getGrandTotal());
        $payment->setAmountPaid($order->getGrandTotal());
        $payment->setBaseAmountOrdered($order->getGrandTotal());
        $payment->setAmountOrdered($order->getGrandTotal());
        $payment->save();
        foreach($order->getAllItems() as $item){
            $rat=$item->getPriceInclTax()/$total;
            $ratdisc=abs($customDiscount)*$rat;
            $discountAmt=($item->getDiscountAmount()+$ratdisc) * $item->getQtyOrdered();
            $base=($item->getBaseDiscountAmount()+$ratdisc) * $item->getQtyOrdered();
            $item->setBaseDiscountAmount($base);
            $item->setDiscountAmount($discountAmt);
            $item->save();
        }
        $order->save();
    }

}
