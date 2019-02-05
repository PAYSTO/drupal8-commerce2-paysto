# Commerce Paysto

Integration [Paysto](https://info.paysto.ru/) with Drupal Commerce 2.

**This module should be only installed via Composer. Archives on drupal.org for informative purposes.**

## Installation

1. Request module with composer `composer require drupal/commerce_paysto`.
2. Enable module as usual.
3. Navigate to Store > Configuration > Payments > Payment Gateways.
4. Add new payment gateway as you need, just select plugin Paysto.
5. Select or set Merchant ID, Secret word, Hash Method and Vat Rate for products and shipping. They are different for test and live REST API's.
6. Save it.
7. For test mode you can use information represented bellow

## Credit cards for testing

| Type       | Credit No.          | Expires | CVC/CVV  | 3-D Secure |
|------------|---------------------|---------|----------|------------|
| VISA       | 4100 0000 0000 0010 | 2021/12 | 123      |            |

## Other info

- [Paysto tariffs](https://info.paysto.ru/tarif/)
- [Paysto commoont protocol API](https://paysto.ru/Partners/ru/docs/protocol)*
- [Paysto PHP SDK](https://github.com/alexsaab/paysto-sdk-php) (RU)

\* _That is only common api protocol. If you want get more info about other method of realisation Paysto API you welcome to [Paysto API](https://info.paysto.ru/api/)_