define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/url-builder',
        'mage/url'
    ],
    function (Component,urlBuilder,url) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'CCVOnlinePayments_Magento/payment/default',
                selectedIssuer: null
            },
            redirectAfterPlaceOrder: false,
            afterPlaceOrder: function() {
                window.location.replace(url.build('ccvonlinepayments/checkout/redirect'));
            },
            getIssuers: function() {
                return window.checkoutConfig.issuers[this.item.method];
            },
            getSelectedIssuer: function() {
                return this.selectedIssuer;
            },
            getIcon: function() {
                return window.checkoutConfig.icons[this.item.method];
            },
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        "selectedIssuer":   this.selectedIssuer,
                        "issuerKey":        window.checkoutConfig.issuerKeys[this.item.method]
                    }
                };
            },
        });
    }
);
