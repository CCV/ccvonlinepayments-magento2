define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'ccvonlinepayments_banktransfer',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_card_bcmc',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_card_maestro',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_card_mastercard',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_card_visa',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_card_amex',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_eps',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_giropay',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_ideal',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_applepay',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_googlepay',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_klarna',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_paypal',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_sofort',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            },{
                type: 'ccvonlinepayments_paybylink',
                component: 'CCVOnlinePayments_Magento/js/view/payment/method/default'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
