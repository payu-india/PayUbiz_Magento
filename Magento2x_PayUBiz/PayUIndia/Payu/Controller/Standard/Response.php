<?php

namespace PayUIndia\Payu\Controller\Standard;

class Response extends \PayUIndia\Payu\Controller\PayuAbstract {

    public function execute() {
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');

        try {
            $paymentMethod = $this->getPaymentMethod();
            $params = $this->getRequest()->getParams();
            $orderId = $paymentMethod->getDecryptOrderId($params["uniqId"]);
            $order = $this->getOrder($orderId);

            if (!empty($orderId) && $paymentMethod->validateResponse($params, $order)) {
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
                $payment = $order->getPayment();
                $paymentMethod->postProcessing($order, $payment, $params);
            } else {
                $this->messageManager->addErrorMessage(__('Payment failed. Please try again or choose a different payment method'));
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
