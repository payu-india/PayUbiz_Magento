<?php

namespace PayUIndia\Payu\Controller\Standard;



class Response extends \PayUIndia\Payu\Controller\PayuAbstract {

    public function execute() {
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');
        try {
            $paymentMethod = $this->getPaymentMethod();
            $params = $this->getRequest()->getParams();

            if ($paymentMethod->validateResponse($params)) {
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
                $order = $this->getOrder();
                $payment = $order->getPayment();
                $paymentMethod->postProcessing($order, $payment, $params);
            } else {
				$returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/failure');
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));
        }

        $this->getResponse()->setRedirect($returnUrl);
    }

}
