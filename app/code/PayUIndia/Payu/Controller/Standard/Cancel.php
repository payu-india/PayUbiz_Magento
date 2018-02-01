<?php

namespace PayUIndia\Payu\Controller\Standard;

class Cancel extends \PayUIndia\Payu\Controller\PayuAbstract {

    public function execute() {
        $this->getOrder()->cancel()->save();
        
        $this->messageManager->addErrorMessage(__('Your order has been can cancelled'));
        $this->getResponse()->setRedirect(
                $this->getCheckoutHelper()->getUrl('checkout')
        );
    }

}
