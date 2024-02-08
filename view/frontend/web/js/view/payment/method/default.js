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
                return window.checkoutConfig.payment[this.item.method]?.issuers;
            },
            getSelectedIssuer: function() {
                return this.selectedIssuer;
            },
            getIcon: function() {
                return window.checkoutConfig.payment[this.item.method]?.icon;
            },
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        "selectedIssuer":   this.selectedIssuer,
                        "issuerKey":        window.checkoutConfig.payment[this.item.method]?.issuerKey
                    }
                };
            },
        });
    }
);
