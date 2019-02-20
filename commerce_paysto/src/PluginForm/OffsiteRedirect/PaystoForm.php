<?php

namespace Drupal\commerce_paysto\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_paysto\Plugin\Commerce\PaymentGateway\Paysto as PM;


/**
 * Order registration and redirection to payment URL.
 */
class PaystoForm extends BasePaymentOffsiteForm
{


    /** @var string payment url for redirect on paysto.com */
    public $payment_url = 'https://paysto.com/ru/pay/AuthorizeNet';

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $data = [];
        $form = parent::buildConfigurationForm($form, $form_state);

        // Get now for sign
        $now = time();
        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $this->entity;
        /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
        $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
        $configs = $payment_gateway_plugin->getConfiguration();

        $order = $payment->getOrder();
        $total_price = $order->getTotalPrice();

        $data = [
            'x_description' => $configs['description'] . $payment->getOrderId(),
            'x_login' => $configs['x_login'],
            'x_amount' => $total_price ? $total_price->getNumber() : '0.00',
            'x_currency_code' => $total_price->getCurrencyCode(),
            'x_fp_sequence' => $payment->getOrderId(),
            'x_fp_timestamp' => $now,
            'x_fp_hash' => PM::get_x_fp_hash($configs['x_login'],  $payment->getOrderId(), $now,
                $total_price ? $total_price->getNumber() : '0.00', $total_price->getCurrencyCode(), $configs['secret']),
            'x_invoice_num' => $payment->getOrderId(),
            'x_relay_response' => "TRUE",
            'x_relay_url' => $this->getNotifyUrl(),
        ];

        $customerEmail = $order->getEmail();
        // if isset email
        if ($customerEmail) {
            $data['x_email'] = $customerEmail;
        }

        // Get item data
        $items = array_merge(PM::getOrderItems($order, $configs), PM::getOrderAdjustments($order, $configs));
        //add products
        $pos = 1;
        $x_line_item = '';
        foreach ($items as $key => $item) {
            $lineArr[] = '№' . $pos . "  ";
            $lineArr[] = $item['SKU'];
            $lineArr[] = $item['NAME'];
            $lineArr[] = $item['QTY'];
            $lineArr[] = $item['PRICE'];
            $lineArr[] = $item['TAX'];
            $x_line_item .= implode('<|>', $lineArr) . "0<|>\n";
            $pos++;
        }

        $data['x_line_item'] = $x_line_item;

        return $this->buildRedirectForm($form, $form_state, $this->payment_url, $data, 'post');
    }

    /**
     * {@inheritdoc}
     */
    public function getNotifyUrl()
    {
        $url = \Drupal::request()->getSchemeAndHttpHost().'/payment/notify/paysto';
        return $url;
    }

}
