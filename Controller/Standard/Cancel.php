<?php

namespace PayUIndia\Payu\Controller\Standard;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;


class Cancel extends \PayUIndia\Payu\Controller\PayuAbstract implements CsrfAwareActionInterface, HttpGetActionInterface, HttpPostActionInterface
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
		$paymentMethod = $this->getPaymentMethod();
		$goto=$this->_cancelPayment('Payment canceld/failed...Order canceled...');
        $this->messageManager->addErrorMessage(__('Your order has been canceled'));
        $this->getResponse()->setRedirect(
                $this->getCheckoutHelper()->getUrl('checkout').'/'.$goto
        );
    }

}
