/*browser:true*/
/*global define*/
define([
  "Magento_Checkout/js/view/payment/default",
  "jquery",
  "mage/url",
], function (Component, setPaymentMethod) {
  "use strict";

  return Component.extend({
    defaults: {
      template: "PayUIndia_Payu/payment/payu",
    },

    preparePayment: function () {
      jQuery(function ($) {
        $.ajax({
          url: window.checkoutConfig.payment.payu.redirectUrl,
          type: "get",
          dataType: "json",
          cache: false,
          processData: false, // Don't process the files
          contentType: false, // Set content type to false as jQuery will tell the server its a query string request
          success: function (data) {
            $("#payuloader", parent.document).html(data["html"]);
          },
          error: function (xhr, ajaxOptions, thrownError) {
            alert(
              thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText
            );
          },
        });
      });
    },

    redirectAfterPlaceOrder: false,

    afterPlaceOrder: function () {
      //do nothing
    },
  });
});
