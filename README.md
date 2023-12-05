# 1. About this plugin

Official CCV Payment Services plugin for Magento 2.

# 2. Technical information

## 2.1. Plugin dependencies

- Magento 2.3.5-p2 or higher
- PHP 7.2 or higher

# 3. Installing the plugin into your web shop

from CMD line:
1. composer require ccv/magento2
2. php bin/magento module:enable --clear-static-content CCVOnlinePayments_Magento
3. php bin/magento setup:upgrade
4. php bin/magento cache:clean
5. php bin/magento indexer:reindex

in Magento Admin:

1. Click on Stores
2. Click on Configuration
3. Click on Sales
4. Click on Payment Methodes
5. Open Other Payment methods
6. Open  CCV Online Payments
7. Open General
8. Get your LIVE API key from your MyCCV account*
9. Enter the API key
10. Open and activate the desired payment methods
11. Click on Save Config

*The live API key is released after successful boarding by CCV Online Payments.

Manuals are available on the [CCV web site](https://www.ccv.eu/nl/service/support/handleidingen).

# 4. Support

Please create a GitHub issue for feature requests or bug reports. If you have a general question or installation difficulties, you can contact us directly through [this form](https://www.ccv.eu/nl/betaaloplossingen/betaaloplossingen-online/online-payments-voor-developers). 

# 5. License

[![MIT license](https://img.shields.io/github/license/CCV/ccvonlinepayments-magento2)](https://github.com/CCV/ccvonlinepayments-magento2/blob/master/LICENSE.txt)
