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
     * Return form for checkout
     * @param array $form
     * @param FormStateInterface $form_state
     * @return mixed
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {

        $form = parent::buildConfigurationForm($form, $form_state);

        // Get now for sign
        $now = time();
        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $this->entity;
        /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
        $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
        $configs = $payment_gateway_plugin->getConfiguration();

        $order = $payment->getOrder();
        $paymentMachineName = $order->get('payment_gateway')->first()->entity->getOriginalId();
        $total_price = $order->getTotalPrice();
        $total_price_number = ($total_price->getNumber()) ?
                number_format($total_price->getNumber(), 2, '.', '') : 0.00;

        $data = [
            'x_description' => $configs['description'] . $payment->getOrderId(),
            'x_login' => $configs['x_login'],
            'x_amount' => $total_price_number,
            'x_currency_code' => $total_price->getCurrencyCode(),
            'x_fp_sequence' => $payment->getOrderId(),
            'x_fp_timestamp' => $now,
            'x_fp_hash' => PM::get_x_fp_hash($configs['x_login'],  $payment->getOrderId(), $now,$total_price_number,
                $total_price->getCurrencyCode(), $configs['secret']),
            'x_invoice_num' => $payment->getOrderId(),
            'x_relay_response' => "TRUE",
            'x_relay_url' => $this->getNotifyUrl($paymentMachineName),
        ];

        $customerEmail = $order->getEmail();

        // if isset email
        if ($customerEmail) {
            $data['x_email'] = $customerEmail;
        }

        $x_line_item = PM::getFormattedOrderItems($order, $configs);

        $data['x_line_item'] = $x_line_item;

        return $this->buildRedirectForm($form, $form_state, $this->payment_url, $data, 'post');
    }

    /**
     * Return notify url
     * {@inheritdoc}
     */
    public function getNotifyUrl($paymentName)
    {
        $url = \Drupal::request()->getSchemeAndHttpHost().'/payment/notify/'.$paymentName;
        return $url;
    }

}
