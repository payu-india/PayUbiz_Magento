define(
    [
        'Magento_Checkout/js/view/payment/default',
        'PayUIndia_Payu/js/action/set-payment-method',
    ],
    function(Component,setPaymentMethod){
    'use strict';

    return Component.extend({
        defaults:{
            'template':'PayUIndia_Payu/payment/payu'
        },
        redirectAfterPlaceOrder: false,
        
        afterPlaceOrder: function () {
            setPaymentMethod();    
        }

    });
});
