<?php

namespace Drupal\commerce_paysto\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides the Paysto payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paysto",
 *   label = @Translation("Paysto"),
 *   display_label = @Translation("Paysto"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paysto\PluginForm\OffsiteRedirect\PaystoForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "maestro", "mastercard", "visa", "mir",
 *   },
 * )
 */
class Paysto extends OffsitePaymentGatewayBase
{

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {

        $returned = [
                'merchant_id' => '',
                'secret' => '',
                'vat_shipping' => '',
                'use_ip_only_from_server_list' => true,
                'server_list' => '95.213.209.218
95.213.209.219
95.213.209.220
95.213.209.221
95.213.209.222'
            ] + parent::defaultConfiguration();

        foreach ($this->getProductTypes() as $type) {
            $returned['vat_product_' . $type] = '';
        }

        return $returned;
    }

    /**
     * Setup configuration
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['merchant_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Merchant ID"),
            '#description' => $this->t("Visit merchant interface in Paysto site and copy data from 'Store code' field"),
            '#default_value' => $this->configuration['merchant_id'],
            '#required' => TRUE,
        ];

        $form['secret'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Secret word"),
            '#description' => $this->t("Visit merchant interface in Paysto site set and copy data from 'Secret word' field"),
            '#default_value' => $this->configuration['secret'],
            '#required' => TRUE,
        ];

        $form['description'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Order description"),
            '#description' => $this->t("Order description in Paysto interface"),
            '#default_value' => $this->configuration['description'],
            '#required' => TRUE,
        ];

        foreach ($this->getProductTypes() as $type) {
            $form['vat_product_' . $type] = [
                '#type' => 'select',
                '#title' => $this->t("Vat rate for product type " . $type),
                '#description' => $this->t("Set vat rate for product " . $type),
                '#options' => array(
                    'Y' => $this->t('With VAT'),
                    'N' => $this->t('WIthout VAT'),
                ),
                '#default_value' => $this->configuration['vat_product_' . $type],
                '#required' => TRUE,
            ];
        }

        $form['vat_shipping'] = [
            '#type' => 'select',
            '#title' => $this->t("Vat rate for shipping"),
            '#description' => $this->t("Set vat rate for shipping"),
            '#options' => array(
                'Y' => $this->t('With VAT'),
                'N' => $this->t('WIthout VAT'),
            ),
            '#default_value' => $this->configuration['vat_shipping'],
            '#required' => TRUE,
        ];

        $form['use_ip_only_from_server_list'] = [
            '#type' => 'checkbox',
            '#title' => $this->t("Use server IP"),
            '#description' => $this->t("Use server IP for callback only from list"),
            '#value' => true,
            '#false_values' => [false],
            '#default_value' => $this->configuration['use_ip_only_from_server_list'],
            '#required' => true,
        ];


        $form['server_list'] = [
            '#type' => 'select',
            '#title' => $this->t("Acceptable server list"),
            '#description' => $this->t("Input new server IP in each new string"),
            '#default_value' => $this->configuration['vat_shipping'],
        ];

        return $form;
    }

    /**
     * Validation of form
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValue($form['#parents']);
    }

    /**
     * Form submit
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        // Parent method will reset configuration array and further condition will
        // fail.
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['merchant_id'] = $values['merchant_id'];
            $this->configuration['secret'] = $values['secret'];
            $this->configuration['hash_method'] = $values['hash_method'];
            $this->configuration['description'] = $values['description'];
            foreach ($this->getProductTypes() as $type) {
                $this->configuration['vat_product_' . $type] = $values['vat_product_' . $type];
            }

            $this->configuration['vat_shipping'] = $values['vat_shipping'];
        }
    }

    /**
     * Notity payment callback
     * @param Request $request
     * @return null|\Symfony\Component\HttpFoundation\Response|void
     */
    public function onNotify(Request $request)
    {

        // try to get values from request
        $LMI_MERCHANT_ID = self::getRequest('LMI_MERCHANT_ID');
        $LMI_PAYMENT_NO = self::getRequest('LMI_PAYMENT_NO');
        $LMI_SYS_PAYMENT_ID = self::getRequest('LMI_SYS_PAYMENT_ID');
        $LMI_SYS_PAYMENT_DATE = self::getRequest('LMI_SYS_PAYMENT_DATE');
        $LMI_PAYMENT_AMOUNT = self::getRequest('LMI_PAYMENT_AMOUNT');
        $LMI_CURRENCY = self::getRequest('LMI_CURRENCY');
        $LMI_PAID_AMOUNT = self::getRequest('LMI_PAID_AMOUNT');
        $LMI_PAID_CURRENCY = self::getRequest('LMI_PAID_CURRENCY');
        $LMI_PAYMENT_SYSTEM = self::getRequest('LMI_PAYMENT_SYSTEM');
        $LMI_SIM_MODE = self::getRequest('LMI_SIM_MODE');
        $SECRET = $this->configuration['secret'];
        $hash_method = $this->configuration['hash_method'];

        // get hash and sign from request
        $LMI_HASH = self::getRequest('LMI_HASH');
        $LMI_SIGN = self::getRequest('SIGN');

        // gert order
        if (!$LMI_PAYMENT_NO)
            die('Order load fail!');
        $order = Order::load($LMI_PAYMENT_NO);
        $order_total = self::getOrderTotalAmount($order->getTotalPrice());
        $order_currency = self::getOrderCurrencyCode($order->getTotalPrice());


        // Check callback for pre-request
        if (self::getRequest("LMI_PREREQUEST")) {
            if (($LMI_MERCHANT_ID == $this->configuration['merchant_id']) &&
                ($LMI_PAYMENT_AMOUNT == $order_total) && ($LMI_PAID_CURRENCY == $order_currency)) {
                echo 'YES';
                exit;
            } else {
                echo 'FAIL';
                exit;
            }
        }

        // get hash and sign
        $hash = self::getHash($LMI_MERCHANT_ID, $LMI_PAYMENT_NO, $LMI_SYS_PAYMENT_ID, $LMI_SYS_PAYMENT_DATE,
            $LMI_PAYMENT_AMOUNT, $LMI_CURRENCY, $LMI_PAID_AMOUNT, $LMI_PAID_CURRENCY, $LMI_PAYMENT_SYSTEM,
            $LMI_SIM_MODE, $SECRET, $hash_method);

        $sign = self::getSign($LMI_MERCHANT_ID, $LMI_PAYMENT_NO, $LMI_PAYMENT_AMOUNT, $LMI_CURRENCY,
            $SECRET, $hash_method);

        // check for right hash and sign
        if ($hash === $LMI_HASH && $sign === $LMI_SIGN) {
            $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
            $payment = $payment_storage->create([
                'state' => 'complete',
                'amount' => $order->getTotalPrice(),
                'payment_gateway' => $this->entityId,
                'order_id' => $LMI_PAYMENT_NO,
                'remote_id' => $LMI_SYS_PAYMENT_ID,
                'remote_state' => 'complete'
            ]);
            $payment->save();

        } else {
            MessengerInterface::addMessage($this->t('Invalid Transaction. Please try again'), 'error');
            return $this->onCancel($order, $request);
        }

    }


    /**
     * Callback function
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request)
    {
        // Get order_id in callback
        $order_id = $request->query->get('LMI_PAYMENT_NO');
        $order = Order::load($order_id);
        $order_total = number_format($order->getTotalPrice()->getNumber(), 2, '.', '');

        print($order_total);

        // Check callback for prerequest
        if ($request->query->get("LMI_PREREQUEST")) {
            if (($request->query->get("LMI_MERCHANT_ID") == $this->configuration['merchant_id']) && ($request->query->get("LMI_PAYMENT_AMOUNT") == $order_total)) {
                echo 'YES';
                exit;
            } else {
                echo 'FAIL';
                exit;
            }
        }


    }


    /**
     * Get hash
     * @param $LMI_MERCHANT_ID
     * @param $LMI_PAYMENT_NO
     * @param $LMI_SYS_PAYMENT_ID
     * @param $LMI_SYS_PAYMENT_DATE
     * @param $LMI_PAYMENT_AMOUNT
     * @param $LMI_CURRENCY
     * @param $LMI_PAID_AMOUNT
     * @param $LMI_PAID_CURRENCY
     * @param $LMI_PAYMENT_SYSTEM
     * @param $LMI_SIM_MODE
     * @param $SECRET
     * @param string $hash_method
     * @return string
     */
    public static function getHash($LMI_MERCHANT_ID, $LMI_PAYMENT_NO, $LMI_SYS_PAYMENT_ID, $LMI_SYS_PAYMENT_DATE, $LMI_PAYMENT_AMOUNT, $LMI_CURRENCY, $LMI_PAID_AMOUNT, $LMI_PAID_CURRENCY, $LMI_PAYMENT_SYSTEM, $LMI_SIM_MODE, $SECRET, $hash_method = 'md5')
    {
        $string = $LMI_MERCHANT_ID . ";" . $LMI_PAYMENT_NO . ";" . $LMI_SYS_PAYMENT_ID . ";" . $LMI_SYS_PAYMENT_DATE . ";" . $LMI_PAYMENT_AMOUNT . ";" . $LMI_CURRENCY . ";" . $LMI_PAID_AMOUNT . ";" . $LMI_PAID_CURRENCY . ";" . $LMI_PAYMENT_SYSTEM . ";" . $LMI_SIM_MODE . ";" . $SECRET;
        $hash = base64_encode(hash($hash_method, $string, true));
        return $hash;
    }


    /**
     * Get sign
     * @param $merchant_id
     * @param $order_id
     * @param $amount
     * @param $lmi_currency
     * @param $secret_key
     * @param string $sign_method
     * @return string
     */
    public static function getSign($merchant_id, $order_id, $amount, $lmi_currency, $secret_key, $hash_method = 'md5')
    {
        $plain_sign = $merchant_id . $order_id . $amount . $lmi_currency . $secret_key;
        $sign = base64_encode(hash($hash_method, $plain_sign, true));
        return $sign;
    }

    /**
     * Get post or get method
     * @param null $param
     */
    public static function getRequest($param = null)
    {
        $post = \Drupal::request()->request->get($param);
        $get = \Drupal::request()->query->get($param);
        if ($post) {
            return $post;
        }
        if ($get) {
            return $get;
        } else {
            return null;
        }
    }


    /**
     * Get order amount
     * @param \Drupal\commerce_price\Price $price
     * @return string
     */
    public static function getOrderTotalAmount(\Drupal\commerce_price\Price $price)
    {
        return number_format($price->getNumber(), 2, '.', '');
    }


    /**
     * Get order currency
     * @param \Drupal\commerce_price\Price $price
     * @return string
     */
    public static function getOrderCurrencyCode(\Drupal\commerce_price\Price $price)
    {
        return $price->getCurrencyCode();
    }


    /**
     * {@inheritdoc}
     */
    public function onCancel(OrderInterface $order, Request $request)
    {
        MessengerInterface::addMessage($this->t('You have canceled checkout at @gateway but may resume the checkout process here when you are ready.', [
            '@gateway' => $this->getDisplayLabel(),
        ]));
    }


    /**
     * Get all product types
     * @return array
     */
    public function getProductTypes()
    {
        $product_types = \Drupal\commerce_product\Entity\ProductType::loadMultiple();
        return array_keys($product_types);
    }


    /**
     * Get order product items
     * @param $order
     * @param $config array
     * @return array
     */
    public static function getOrderItems($order, $config)
    {
        $itemsArray = [];

        foreach ($order->getItems() as $key => $item) {
            $type = $item->getPurchasedEntity()->getProduct()->get('type')->getString();
            $name = $item->getTitle();
            $price = number_format($item->getUnitPrice()->getNumber(), 2, '.', '');
            $qty = number_format($item->getQuantity(), 0, '.', '');
            if (!($vat = $config['vat_product_' . $type])) {
                $vat = 'no_vat';
            }
            $itemsArray[] = [
                'NAME' => $name,
                'QTY' => $qty,
                'PRICE' => $price,
                'TAX' => $vat,
            ];
        }
        return $itemsArray;
    }


    /**
     * Get order Adjastment (Shipping, fee and etc.)
     * @param $order
     * @param $config array
     * @return array
     */
    public static function getOrderAdjustments($order, $config)
    {
        $itemsArray = [];
        foreach ($order->getAdjustments() as $adjustment) {
            if ($adjustment->getType() == 'shipping') {
                $itemsArray[] = [
                    'NAME' => $adjustment->getLabel(),
                    'QTY' => 1,
                    'PRICE' => number_format($adjustment->getAmount()->getNumber(), 2, '.', ''),
                    'TAX' => $config['vat_shipping'],
                ];
            } else {
                $itemsArray[] = [
                    'NAME' => $adjustment->getLabel(),
                    'QTY' => 1,
                    'PRICE' => number_format($adjustment->getAmount()->getNumber(), 2, '.', ''),
                    'TAX' => 'no_vat',
                ];
            }
        }
        return $itemsArray;
    }


}
