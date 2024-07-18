<?php

namespace PayUIndia\Payu\Cron;

class OrderUpdate
{
    protected $logger;
    protected $orderModel;
    protected $payuHelper;
    protected $paymentMethodModel;
    protected $OrderCollectionFactory;
    protected $timezone;
    protected $orderSender;

    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $OrderCollectionFactory,
        \Magento\Sales\Model\Order                                 $orderModel,
        \PayUIndia\Payu\Helper\Payu                                $payuHelper,
        \PayUIndia\Payu\Model\Payu                                 $paymentMethodModel,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface       $timezone,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender        $orderSender

    )
    {

        $this->orderModel = $orderModel;
        $this->paymentMethodModel = $paymentMethodModel;
        $this->payuHelper = $payuHelper;
        $this->OrderCollectionFactory = $OrderCollectionFactory;
        $this->timezone = $timezone;
        $this->orderSender = $orderSender;
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/ordercron.log');
        $this->logger = new \Zend_Log();
        $this->logger->addWriter($writer);
    }

    public function execute()
    {
        $this->logger->info('its update');
        $end= new \DateTime();
        $end= $end->format('Y-m-d H:i:s');
        $dateTime=new \DateTime();
        $dateTime->sub(new \DateInterval('PT10M'));
        $start=$dateTime->format(\Magento\Framework\StdLib\DateTime::DATETIME_PHP_FORMAT);
        $orderList = $this->OrderCollectionFactory->create()->addFieldToFilter('created_at', array('lteq' => $start))->addFieldToFilter('status','pending_payment');
       
        $this->logger->info($orderList->getSelect()->__toString());

        foreach ($orderList as $orderData){
            $order=$this->orderModel->load($orderData->getId());
            $txnid=$orderData->getTxnid();
            $paymentVerData=$this->paymentVerify($order,$txnid);
            $paymentResponse=json_decode($paymentVerData,true);

            $this->logger->info($paymentVerData);
            $this->logger->info($paymentResponse['transaction_details'][$txnid]['status']);

            if($order->getState()=='pending_payment' && $paymentResponse['transaction_details'][$txnid]['status']=='success'){
                $this->payuHelper->updateOrderFromResponse($order,$paymentResponse['transaction_details'][$txnid]);
                $payment = $order->getPayment();
                $this->postProcessing($order, $payment, $paymentResponse['transaction_details'][$txnid]);
            }elseif ($order->getState()=='pending_payment'){
                $order->setStatus('canceled');
                $order->setState('canceled');
                $order->save();

            }else{
                $this->logger->info('order up-to-date');
            }
        }
    }
    public function paymentVerify($order,$txnid)
    {
        $fields = array(
            'key' => $this->payuHelper->getConfigData("merchant_key"),
            'command' => 'verify_payment',
            'var1' => $txnid,
            'hash' => ''
        );

        $hash = hash("sha512", $fields['key'] . '|' . $fields['command'] . '|' . $fields['var1'] . '|' . $this->payuHelper->getConfigData('salt'));
        $fields['hash'] = $hash;

        $fields_string = http_build_query($fields);
        $url = 'https://info.payu.in/merchant/postservice.php?form=2';
        if ($this->payuHelper->getConfigData('environment') == 'sandbox')
            $url = "https://test.payu.in/merchant/postservice.php?form=2";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSLVERSION, 6);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
        $response = curl_exec($curl);
        $curlerr = curl_error($curl);

        $message = '';
        $res = '';
        if ($curlerr != '') {
            $message = $curlerr;
            return false;
        } else {
            $res = json_decode($response, true);
        }
        return $response;
    }

    public function postProcessing(\Magento\Sales\Model\Order $order,\Magento\Framework\DataObject $payment, $response) 
	{
		$writer = new \Zend_Log_Writer_Stream(BP . '/var/log/ordercron.log'); 
		$logger = new \Zend_Log(); 
		$logger->addWriter($writer); 
		$logger->info('Custom message');
		$logger->info('response '.json_encode($response));
		
		try {				
			$orderemail = $this->payuHelper->getConfigData("orderemail");
			$geninvoice = $this->payuHelper->getConfigData("generateinvoice");
			$emailinvoice = $this->payuHelper->getConfigData("invoiceemail");	
			if($this->paymentMethodModel->verifyPayment($order,$response['txnid']))
			{	
				$payment->setTransactionId($response['txnid'])       
				->setPreparedMessage('SUCCESS')
				->setShouldCloseParentTransaction(true)
				->setIsTransactionClosed(0)
				->setAdditionalInformation('payu_mihpayid', $response['mihpayid'])
				->setAdditionalInformation('payu_order_status', 'approved');    
				
				If (isset($response['additional_charges'])) {
					$payment->setAdditionalInformation('Additional Charges', $response['additional_charges']);		
					$payment->registerCaptureNotification(($response['amt']+$response['additional_charges']),true);
					$order->save();
				}
				else {
					$payment->registerCaptureNotification($response['amt'],true)->save();
				}
				
				if($this->payuHelper->getConfigData('debuglog')==true)
					$this->logger->debug($response);					
				
				// Fix for Bug- Order Item 'base_original_price' and 'original_price' not updated during order save
				foreach ($order->getAllItems() as $item) 
				{ 
					$logger->info('itemdata '.json_encode($item->getData()));
					$item->setBasePrice($item->getBasePrice())->setOriginalPrice($item->getOriginalPrice())->setBaseOriginalPrice($item->getBaseOriginalPrice())->save();

				}
				
				$order->setTotalPaid($response['amt'])->save();
				$order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING,true)->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)->save();				
				$order->setCanSendNewEmailFlag(true)->save();
				$order->setIsCustomerNotified(true)->save();
				
				if($orderemail)
					$this->orderSender->send($order);
				
				if($geninvoice && $order->canInvoice()) {
					$invoice = $order->prepareInvoice();
					$invoice->register();
					$logger->info('invoice data '.json_encode($invoice->getData()));

					$invoice->save();

					if($emailinvoice) {
						$objManager = \Magento\Framework\App\ObjectManager::getInstance();
						$sender = $objManager->get('\Magento\Sales\Model\Order\Email\Sender\InvoiceSender'); 
						$sender->send($invoice);
					}
				}	
			}
			else {
				//modified to cancel order in case of failed or canceled payment
				if($this->payuHelper->getConfigData('debuglog')==true)
					$this->logger->debug($response);									  
				
				$order->setState(Order::STATE_CANCELED,true)->setStatus(Order::STATE_CANCELED);	
				$order->save();
			}
		}
		catch(Exception $e){
			if($this->payuHelper->getConfigData('debuglog')==true)
				$this->logger->debug($e->getMessage());
		}
    }
}
