<?php

namespace PayUIndia\Payu\Controller\Standard;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;


class Response extends \PayUIndia\Payu\Controller\PayuAbstract implements CsrfAwareActionInterface, HttpGetActionInterface,HttpPostActionInterface
{

	/**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }
 
    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute() {
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');

        try {
            $paymentMethod = $this->getPaymentMethod();

			$allParam = $this->getRequest()->getParams();
			if(array_key_exists('full_response',$allParam)){
				$params = json_decode($allParam['full_response'],true);
			}else{
				$params = $allParam;
			}
			
			if ($paymentMethod->validateResponse($params)) {

                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
				
				$quoteId = $params["txnid"];
				if(strpos($quoteId,'-') > 0)
				{
					$q=explode('-',$quoteId);
					$quoteId=$q[0];
				}
				$quote = $this->quoteRepository->get($quoteId);
				
				if($paymentMethod->getConfigData('debuglog')==true)
					$this->_logger->debug("PayU Response QuoteID: ".$quoteId);
				
				
				$quote->getPayment()->setMethod($paymentMethod->getMethodCode());
				
				if($paymentMethod->getConfigData('debuglog')==true)
					$this->_logger->debug("PayU Creating Order ...");
				
				//Apply offer discount
				if(isset($params['offer']) && isset($params['offer_availed']) && isset($params['transaction_offer']))
				{
					$offer_json = $params['transaction_offer'];
					$offer_code ="";
					if(isset($offer_json['offer_data'])){
						if(isset($offer_json['offer_data'][0])){
							$offer_code = strtolower(str_replace(' ','',$offer_json['offer_data'][0]['offer_title']));
							$quote->setCouponCode($offer_code)->save();	
						}
					}
						
					if($paymentMethod->getConfigData('debuglog')==true)
						$this->_logger->debug("PayU ".$offer_code." Applied...");
					
				}
				// set customer email if email is found empty (Bug) 
				if ($quote->getCustomerEmail() === null && $quote->getBillingAddress()->getEmail() !== null )
				{
					$quote->setCustomerEmail($quote->getBillingAddress()->getEmail());
					$this->_logger->debug("PayU Guest Checkout - magento email bug fix applied ...");
				}
				
				$this->_logger->debug("PayU submitting quote to order ...");
				$quote->collectTotals()->save();
			

				
				$order = $this->getOrderById($quote->getReservedOrderId());
				$this->getCheckoutHelper()->updateOrderFromResponse($order,$params);
				
				if($paymentMethod->getConfigData('debuglog')==true)
					$this->_logger->debug("PayU Created Order ...");
				
				$this->checkoutSession->setLastSuccessQuoteId($quote->getId())
										->setLastQuoteId($quote->getId())
										->clearHelperData();
				if(empty($order) === false)
				{
					if($paymentMethod->getConfigData('debuglog')==true)
						$this->_logger->debug("PayU Updating Order ...");
					
					$payment = $order->getPayment();

					$method = $payment->getMethodInstance();
					
					if($paymentMethod->getConfigData('debuglog')==true)
						$this->_logger->debug("Order Payment Method: ".strtolower($method->getTitle()));
					
				
					
					$paymentMethod->postProcessing($order, $payment, $params);
					$quote->setIsActive(false)->save();
					$this->checkoutSession->replaceQuote($quote);
					$this->checkoutSession->setLastOrderId($order->getId())
											->setLastRealOrderId($order->getIncrementId())
											->setLastOrderStatus($order->getStatus());
					
					if($paymentMethod->getConfigData('debuglog')==true)
						$this->_logger->debug("PayU Updated Order ...Redirecting to Success...");
				}
				
				
				
            } else {
				
                $this->messageManager->addErrorMessage(__('Payment failed.'));
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/failure');
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
			echo $e->getMessage();
			
			die();
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
			
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));			
        }
		
        $this->getResponse()->setRedirect($returnUrl);
    }

}
