<?php

namespace PayUIndia\Payu\Controller\Standard;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;


class Redirect extends \PayUIndia\Payu\Controller\PayuAbstract implements CsrfAwareActionInterface, HttpGetActionInterface,  HttpPostActionInterface
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
        if (!$this->getRequest()->isAjax()) {
            $this->_cancelPayment();
            $this->checkoutSession->restoreQuote();
            $this->getResponse()->setRedirect(
                    $this->getCheckoutHelper()->getUrl('checkout')
            );
        }
		
        $html = $this->getPaymentMethod()->buildCheckoutRequest();
		
		if(isset($html['error']) && $html['error']!="") {
			$this->_logger->error("PayU Error-".json_encode($html)); 	
			$this->messageManager->addError("<strong>Error:</strong> ".$html['error']);
		}
	
		return $this->resultJsonFactory->create()->setData(['html' => $html['data']]);
    }

}
