<?php

namespace Drupal\commerce_paysto\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class Paysto
 *
 * @package Drupal\commerce_paysto\Plugin\Commerce\PaymentGateway
 */
class Paysto extends OffsitePaymentGatewayBase
{
    /**
     * Return default module settengs
     * @return array
     */
    public function defaultConfiguration()
    {
        
        $returned = [
                'x_login' => '',
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
     * Setup configuration (settings)
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);
        
        $form['x_login'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Merchant ID"),
            '#description' => $this->t("Visit merchant interface in Paysto site and copy data from 'Store code' field"),
            '#default_value' => $this->configuration['x_login'],
            '#required' => true,
        ];
        
        $form['secret'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Secret word"),
            '#description' => $this->t("Visit merchant interface in Paysto site set and copy data from 'Secret word' field"),
            '#default_value' => $this->configuration['secret'],
            '#required' => true,
        ];
        
        $form['description'] = [
            '#type' => 'textfield',
            '#title' => $this->t("Order description"),
            '#description' => $this->t("Order description in Paysto interface"),
            '#default_value' => $this->configuration['description'],
            '#required' => true,
        ];
        
        foreach ($this->getProductTypes() as $type) {
            $form['vat_product_' . $type] = [
                '#type' => 'select',
                '#title' => $this->t("Vat rate for product type " . $type),
                '#description' => $this->t("Set vat rate for product " . $type),
                '#options' => [
                    'Y' => $this->t('With VAT'),
                    'N' => $this->t('WIthout VAT'),
                ],
                '#default_value' => $this->configuration['vat_product_' . $type],
                '#required' => true,
            ];
        }
        
        $form['vat_shipping'] = [
            '#type' => 'select',
            '#title' => $this->t("Vat rate for shipping"),
            '#description' => $this->t("Set vat rate for shipping"),
            '#options' => [
                'Y' => $this->t('With VAT'),
                'N' => $this->t('WIthout VAT'),
            ],
            '#default_value' => $this->configuration['vat_shipping'],
            '#required' => true,
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
            '#type' => 'textarea',
            '#title' => $this->t("Acceptable server list"),
            '#description' => $this->t("Input new server IP in each new string"),
            '#default_value' => $this->configuration['server_list'],
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
            $this->configuration['x_login'] = $values['x_login'];
            $this->configuration['secret'] = $values['secret'];
            $this->configuration['description'] = $values['description'];
            foreach ($this->getProductTypes() as $type) {
                $this->configuration['vat_product_' . $type] = $values['vat_product_' . $type];
            }
            
            $this->configuration['vat_shipping'] = $values['vat_shipping'];
            $this->configuration['use_ip_only_from_server_list'] = $values['use_ip_only_from_server_list'];
            $this->configuration['server_list'] = $values['server_list'];
        }
    }
    
    /**
     * Notity payment callback
     * @param Request $request
     * @return null|\Symfony\Component\HttpFoundation\Response|void
     */
    public function onNotify(Request $request)
    {
        $x_login = $this->configuration['x_login'];
        $secret = $this->configuration['secret'];
        // try to get values from request
        $orderId = self::getRequest('x_invoice_num');
        
        if (!isset($orderId)) {
            \Drupal::messenger()->addMessage($this->t('Site can not get info from you transaction. Please return to store and perform the order'),
                'success');
            $response = new RedirectResponse('/', 302);
            $response->send();
            return;
        }
        
        $order = Order::load($orderId);
        
        $total_price = $order->getTotalPrice();
        $orderTotal = ($total_price->getNumber()) ?
            number_format($total_price->getNumber(), 2, '.', '') : 0.00;
        
        $x_response_code = self::getRequest('x_response_code');
        $x_trans_id = self::getRequest('x_trans_id');
        $x_MD5_Hash = self::getRequest('x_MD5_Hash');
        $calculated_x_MD5_Hash = self::get_x_MD5_Hash($x_login, $x_trans_id, $orderTotal, $secret);
        $paymentStorage = \Drupal::entityTypeManager()->getStorage('commerce_payment')->loadByProperties([ 'order_id' => [$orderId]]);
        $payment = end($paymentStorage);
        $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
        
        if ($payment->state->value != 'complete') {
            if ($this->checkInServerList()) {
                if ($x_response_code == 1 && $calculated_x_MD5_Hash == $x_MD5_Hash) {
                    $payment = $paymentStorage->create([
                        'state' => 'complete',
                        'amount' => $order->getTotalPrice(),
                        'payment_gateway' => $this->entityId,
                        'order_id' => $orderId,
                        'remote_id' => $x_trans_id,
                        'remote_state' => 'complete',
                        'state' => 'complete',
                    ]);
                    $payment->save();
                    // Change order statuses to remove from busket
                    $order->set('state', 'validation');
                    $order->save();
                } else {
                    $this->onCancel($order, $request);
                    return;
                }
            } else {
                $this->onCancel($order, $request);
                return;
            }
            
        } else {
            \Drupal::messenger()->addMessage($this->t('Order complete! Thank you for payment'), 'success');
            $this->onReturn($order, $request);
            return;
        }
    }
    
    /**
     * Return hash md5 HMAC
     * @param $x_login
     * @param $x_fp_sequence
     * @param $x_fp_timestamp
     * @param $x_amount
     * @param $x_currency_code
     * @param $secret
     * @return string
     */
    public static function get_x_fp_hash($x_login, $x_fp_sequence, $x_fp_timestamp, $x_amount, $x_currency_code, $secret) {
        $arr = [$x_login, $x_fp_sequence, $x_fp_timestamp, $x_amount, $x_currency_code];
        $str = implode('^', $arr);
        return hash_hmac('md5', $str, $secret);
    }
    
    /**
     * Return sign with MD5 algoritm
     * @param $x_login
     * @param $x_trans_id
     * @param $x_amount
     * @param $secret
     * @return string
     */
    public static function get_x_MD5_Hash($x_login, $x_trans_id, $x_amount, $secret)
    {
        return md5($secret . $x_login . $x_trans_id . $x_amount);
    }
    
    /**
     * Get post or get method
     * @param null $param
     */
    public static function getRequest($param = null)
    {
        try {
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
        } catch (\Exception $exception) {
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
     * Callback order success proceed
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request)
    {
        \Drupal::messenger()->addMessage($this->t('Order complete! Thank you for payment'), 'success');
        $orderId = self::getRequest('x_invoice_num');
        if ($user = \Drupal::currentUser()) {
            if ($userId = $user->id()) {
                $url = '/user/'.$userId.'/orders';
            } else {
                if (isset($orderId)) {
                    $url = '/checkout/' . $orderId . '/review';
                } else {
                    $url = '/';
                }
            }
        } else {
            $url = '/';
        }
        $response = new RedirectResponse($url, 302);
        $response->send();
        return;
    }


    /**
     * Callback order fail proceed
     * @param OrderInterface $order
     * @param Request $request
     */
    public function onCancel(OrderInterface $order, Request $request)
    {
        \Drupal::messenger()->addMessage($this->t('You have canceled checkout at @gateway but may resume the checkout process here when you are ready.',
            [
                '@gateway' => $this->getDisplayLabel(),
            ]), 'error');
        $orderId = self::getRequest('x_invoice_num');
        $url = '/checkout/' . $orderId . '/review';
        $response = new RedirectResponse($url, 302);
        $response->send();
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
            $name = $item->getTitle();
            $product = $item->getPurchasedEntity();
            $sku = $product->getSku();
            $type = $product->getProduct()->get('type')->getString();
            $price = number_format($item->getUnitPrice()->getNumber(), 2, '.', '');
            $qty = number_format($item->getQuantity(), 0, '.', '');
            if (!($vat = $config['vat_product_' . $type])) {
                $vat = 'no_vat';
            }
            $itemsArray[] = [
                'sku' => $sku,
                'name' => substr($name, 0, 100),
                'qty' => $qty,
                'price' => $price,
                'tax' => $vat,
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
                    'sku' => 'shipping',
                    'name' => substr($adjustment->getLabel(), 0, 100),
                    'qty' => 1,
                    'price' => number_format($adjustment->getAmount()->getNumber(), 2, '.', ''),
                    'tax' => $config['vat_shipping'],
                ];
            } else {
                $itemsArray[] = [
                    'sku' => $adjustment->getType(),
                    'name' => substr($adjustment->getLabel(), 0, 100),
                    'qty' => 1,
                    'price' => number_format($adjustment->getAmount()->getNumber(), 2, '.', ''),
                    'tax' => 'no_vat',
                ];
            }
        }
        return $itemsArray;
    }
    
    /**
     * Get formatted order items
     * @param $order
     * @param $configs
     */
    public static function getFormattedOrderItems($order, $configs)
    {
        $items = array_merge(self::getOrderItems($order, $configs), self::getOrderAdjustments($order, $configs));
        $returned = '';
        foreach ($items as $key => $item) {
            $lineArr = [];
            $pos = $key + 1;
            $lineArr[] = '#' . $pos . " ";
            $lineArr[] = substr($item['sku'], 0, 30);
            $lineArr[] = substr($item['name'], 0, 250);
            $lineArr[] = $item['qty'];
            $lineArr[] = $item['price'];
            $lineArr[] = $item['tax'];
            $returned .= implode('<|>', $lineArr) . "0<|>\n";
        }
        return $returned;
    }
    
    /**
     * Check if IP adress in server lists
     * @return bool
     */
    public function checkInServerList()
    {
        if ($this->configuration['use_ip_only_from_server_list']) {
            $clientIp = \Drupal::request()->getClientIp();
            $serverIpList = preg_split('/\r\n|[\r\n]/', $this->configuration['server_list']);
            if (in_array($clientIp, $serverIpList)) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }
}
