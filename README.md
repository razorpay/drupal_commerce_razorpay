## Razorpay Payment Extension for Drupal Commerce

This extension utilizes Razorpay API and provides seamless integration with Drupal Commerce, allowing payments for merchants via Credit Cards, Debit Cards, Net Banking, Wallets, etc.

### Installation

1. Download the module from Drupal [Marketplace](https://www.drupal.org/project/drupal_commerce_razorpay) or [Razorpay Repository](https://github.com/razorpay/drupal_commerce_razorpay/releases).
2. To Upload module Go to Drupal admin dashboard click Extend -> Add new module -> choose file -> continue.
3. To install module click extend search for razorpay module, select checkbox and click install button.

### Dependencies

This module requires:

* drupal 10.*
* php 8.1 or higher
* composer require drupal/commerce:^2.33 // Drupal commerce is required as payment module runs on top of it.
* composer require razorpay/razorpay:2.* // Please check the latest [release](https://github.com/razorpay/razorpay-php) and  Command to be run in the main folder of Drupal site.
* Sign up to create a Razorpay account log into your Razorpay account and generate API keys.

### Configuration
 
1. Click commerce -> configuration -> payment gateways -> add payment gateway.
2. Visit razorpay merchant dashboard and generate [key id and key secret](https://dashboard.razorpay.com/app/website-app-settings/api-keys).
3. Add key id and key secret and other required details for creating payment gateway.
4. Click save.
    
### Support

Visit [https://razorpay.com](https://razorpay.com) for support requests or email contact@razorpay.com.

### License

See the [LICENSE](https://github.com/razorpay/payment_button_drupal_plugin/tree/master/LICENSE.txt) file for the complete LICENSE text.
