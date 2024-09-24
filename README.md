<img src="https://github.com/user-attachments/assets/060de999-2f1f-491f-94d8-dd22121526d8" alt="Magento" width="200"/>

# About this plugin
Magento 2 is a powerful, open-source e-commerce platform designed for businesses of all sizes. Known for its flexibility, scalability, and robust feature set, Magento 2 offers extensive customization options and a wide range of third-party extensions. It's ideal for businesses with complex product catalogs and high transaction volumes, and it requires technical expertise for setup and maintenance.

CCV has developed a dedicated plugin that seamlessly integrates Magento 2 with CCV's Online Payments solution.  This plugin connects your Magento 2 store to CCV's payment gateway.

# Payment methods
CCV makes the following payment methods available in this plugin: 
- iDeal
- Bancontact
- Apple Pay
- Google Pay
- Maestro
- Mastercard
- Visa
- Amex
- PayPal
- Klarna
- Bank Transfer	

# Requirements
To support this plugin, the following requirements must be met:
-	Magento 2.3.5-p2 or higher
-	PHP 7.2 or higher
-	MyCCV account & API key*
-	Composer**

*The live API key is released after successful boarding by CCV Online Payments. To register, contact us [here](https://www.ccv.eu/en/solutions/payment-services/ccv-online-payments/partners/online-payments-form/).

**Composer is a PHP dependency manager. 
1.	Check if Composer is already installed: Some hosting providers may already have Composer installed, especially if they offer Magento-optimized hosting. You can check by running “composer --version” in your command line.
2.	Install Composer: If Composer is not installed, you need to follow Composer’s installation guide, which is typically done via the command line. The official installation instructions are available on Composer's website, and they vary depending on the server’s operating system (Linux, Windows, macOS).

# Download
from CMD line in the Magento 2 root folder:
```
composer require ccv/magento2
```

# Install
from CMD line in the Magento 2 root folder:
```
php bin/magento module:enable --clear-static-content CCVOnlinePayments_Magento
php bin/magento setup:upgrade
php bin/magento cache:clean
php bin/magento indexer:reindex
```

# Upgrade
from CMD line in the Magento 2 root folder:
```
composer update ccv/magento2
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
php bin/magento setup:di:compile
```

# Configure
In Magento Admin:
1.	Click on Stores
2.	Click on Configuration
3.	Click on Sales
4.	Click on Payment Methodes
5.	Open Other Payment methods
6.	Open CCV Online Payments
7.	Open General
8.	Get your LIVE API key from your MyCCV account
9.	Enter the API key
10.	Open and activate the desired payment methods
11.	Click on Save Config

# Release notes
At CCV, we are committed to transparency and keeping our users informed about the continuous improvements to our Online Payments solution. To ensure easy access to the latest updates, we maintain a dedicated [release notes website](https://onlinepayments.ccvlab.eu/). This resource provides detailed information about each new version, including feature additions, enhancements, and fixes. By visiting the website, you can stay up to date with the latest developments and make the most of the new tools and capabilities we introduce.

# Contact Us
Please create a GitHub issue for feature requests or bug reports. If you have a general question or installation difficulties, you can contact us directly ecommerce@ccv.eu.

# License

[![MIT license](https://img.shields.io/github/license/CCV/ccvonlinepayments-magento2)](https://github.com/CCV/ccvonlinepayments-magento2/blob/master/LICENSE.txt)
