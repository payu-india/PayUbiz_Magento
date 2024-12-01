<?php

namespace PayUIndia\Payu\Controller\Standard;

class Response extends \PayUIndia\Payu\Controller\PayuAbstract {

    public function execute() {
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');

        try {
            $paymentMethod = $this->getPaymentMethod();
            $params = $this->getRequest()->getParams();
            $orderId = $paymentMethod->getDecryptOrderId($params["uniqId"]);
            $result = false;
            if ($orderId == $params["productinfo"] && $paymentMethod->validateResponse($params)) {
                $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
                $order = $this->getOrder($orderId);
                $payment = $order->getPayment();
                if($params["amount"] == round($order->getBaseGrandTotal(), 2)){;
                    $paymentMethod->postProcessing($order, $payment, $params);
                    $result =  true;
                }
            }
            if($result == false){
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
