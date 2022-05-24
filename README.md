# Adyen Payment plugin for Shopware 5
Use Adyen's plugin for Shopware 5 to offer frictionless payments online, in-app, and in-store.

## Contributing
We strongly encourage you to join us in contributing to this repository so everyone can benefit from:
* New features and functionality
* Resolved bug fixes and issues
* Any general improvements

Read our [**contribution guidelines**](https://github.com/Adyen/.github/blob/master/CONTRIBUTING.md) to find out how.

## Requirements
* PHP >=7.4
* Shopware >=5.7.3

Note: The Adyen payment plugin is not compatible with the cookie manager plugin (<= 5.6.2), it is however compatible with the Shopware default cookie consent manager (>5.6.2).

## Installation
Please see our Wiki for the [integration guide](https://github.com/Adyen/adyen-shopware5/wiki) of the plugin to see how to install it.


## Documentation
Please find the relevant documentation for
 - [Get started with Adyen](https://docs.adyen.com/user-management/get-started-with-adyen)
 - [Shopware 5 plugin integration guide](https://github.com/Adyen/adyen-shopware5/wiki)
 - [Adyen PHP API Library](https://docs.adyen.com/development-resources/libraries#php)

## See [HELP](https://github.com/Adyen/adyen-shopware5/wiki#help) in our Wiki.

# For developers

## Integration
The plugin integrates card component (Secured Fields) using Adyen Checkout for all card payments.

## API Library
This module is using the Adyen's API Library for PHP for all (API) connections to Adyen.
<a href="https://github.com/Adyen/adyen-php-api-library" target="_blank">This library can be found here</a>

## License
MIT license. For more information, see the [LICENSE file](LICENSE).