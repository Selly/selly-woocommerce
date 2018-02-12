<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Name: Selly WooCommerce Payment Gateway
 * Plugin URI: https://selly.gg
 * Description:  A payment gateway for Selly Pay
 * Author: Selly
 * Author URI: https://selly.gg
 * Version: 1.0.0
 */

add_action('plugins_loaded', 'selly_gateway_load', 0);
function selly_gateway_load()
{

    if (!class_exists('WC_Payment_Gateway')) {
        // oops!
        return;
    }

    /**
     * Add the gateway to WooCommerce.
     */
    add_filter('woocommerce_payment_gateways', 'add_gateway');

    function add_gateway($classes)
    {
        if (!in_array('WC_Gateway_Selly', $classes)) {
            $classes[] = 'WC_Gateway_Selly';
        }

        return $classes;
    }

    class WC_Gateway_Selly extends WC_Payment_Gateway
    {
        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct()
        {
            global $woocommerce;

            $this->id = 'selly';
            $this->icon = apply_filters('woocommerce_selly_icon', plugins_url() . '/selly-woocommerce/assets/selly.png');
            $this->method_title = __('Selly', 'woocommerce');
            $this->has_fields = true;
            $this->webhook_url = add_query_arg('wc-api', 'selly_webhook_handler', home_url('/'));

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->email = $this->get_option('email');
            $this->api_key = $this->get_option('api_key');
            $this->order_id_prefix = $this->get_option('order_id_prefix');
            $this->confirmations = $this->get_option('confirmations');
            $this->paypal = $this->get_option('paypal') == 'yes' ? true : false;
            $this->bitcoin = $this->get_option('bitcoin') == 'yes' ? true : false;
            $this->litecoin = $this->get_option('litecoin') == 'yes' ? true : false;
            $this->ethereum = $this->get_option('ethereum') == 'yes' ? true : false;
            $this->dash = $this->get_option('dash') == 'yes' ? true : false;
            $this->bitcoin_cash = $this->get_option('bitcoin_cash') == 'yes' ? true : false;
            $this->ripple = $this->get_option('ripple') == 'yes' ? true : false;

            // Logger
            $this->log = new WC_Logger();

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

            // Webhook Handler
            add_action('woocommerce_api_selly_webhook_handler', [$this, 'webhook_handler']);
        }


        public function payment_fields()
        {
            ?>
            <div class="form-row selly-payment-gateway-form">
                <label for="payment_gateway" class="selly-payment-gateway-label">
                    Payment Method <abbr class="required" title="required">*</abbr>
                </label>
                <select name="payment_gateway" class="selly-payment-gateway-select">
                    <?php if ($this->paypal){ ?><option value="PayPal">PayPal</option><?php } ?>
                    <?php if ($this->bitcoin){ ?><option value="Bitcoin">Bitcoin</option><?php } ?>
                    <?php if ($this->litecoin){ ?><option value="Litecoin">Litecoin</option><?php } ?>
                    <?php if ($this->ethereum){ ?><option value="Ethereum">Ethereum</option><?php } ?>
                    <?php if ($this->dash){ ?><option value="Dash">Dash</option><?php } ?>
                    <?php if ($this->bitcoin_cash){ ?><option value="Bitcoin Cash">Bitcoin Cash</option><?php } ?>
                    <?php if ($this->ripple){ ?><option value="Ripple">Ripple</option><?php } ?>
                </select>
            </div>
            <?php
        }

        /**
         * Check if this gateway is available
         *
         * @access public
         * @return bool
         */
        function is_valid_for_use()
        {
            return true;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @since 1.0.0
         */
        public function admin_options()
        {
            ?>
            <h3><?php _e('Selly', 'woocommerce'); ?></h3>

            <table class="form-table">
                <?php
                $this->generate_settings_html();
                ?>
            </table>
            <?php
        }


        /**
         * Initialise settings
         *
         * @access public
         * @return void
         */
        function init_form_fields()
        {

            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable Selly', 'woocommerce'),
                    'default' => 'yes'
                ],
                'title' => [
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Selly Pay', 'woocommerce'),
                    'desc_tip' => true,
                ],
                'description' => [
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Pay with PayPal, Bitcoin, Ethereum, Litecoin and many more gateways via Selly', 'woocommerce')
                ],
                'email' => [
                    'title' => __('Email', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Please enter your Selly email address.', 'woocommerce'),
                    'default' => '',
                ],
                'api_key' => [
                    'title' => __('API Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Please enter your Selly API Key.', 'woocommerce'),
                    'default' => '',
                ],
                'order_id_prefix' => [
                    'title' => __('Order ID Prefix', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('The prefix before the order number. For example, a prefix of "Order #" and a ID of "10" will result in "Order #10"', 'woocommerce'),
                    'default' => 'Order #',
                ],
                'confirmations' => [
                    'title' => __('Number of confirmations for crypto currencies', 'woocommerce'),
                    'type' => 'number',
                    'description' => __('The default of 1 is advised for both speed and security', 'woocommerce'),
                    'default' => '1'
                ],
                'paypal' => [
                    'title' => __('Accept PayPal', 'woocommerce'),
                    'label' => __('Enable/Disable PayPal', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'bitcoin' => [
                    'title' => __('Accept Bitcoin', 'woocommerce'),
                    'label' => __('Enable/Disable Bitcoin', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'litecoin' => [
                    'title' => __('Accept Litecoin', 'woocommerce'),
                    'label' => __('Enable/Disable Litecoin', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'ethereum' => [
                    'title' => __('Accept Ethereum', 'woocommerce'),
                    'label' => __('Enable/Disable Ethereum', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'dash' => [
                    'title' => __('Accept Dash', 'woocommerce'),
                    'label' => __('Enable/Disable Dash', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'bitcoin_cash' => [
                    'title' => __('Accept Bitcoin Cash', 'woocommerce'),
                    'label' => __('Enable/Disable Bitcoin Cash', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'ripple' => [
                    'title' => __('Accept Ripple', 'woocommerce'),
                    'label' => __('Enable/Disable Ripple', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ]
            ];

        }

        function generate_selly_payment($order)
        {
            $params = [
                'title' => $this->order_id_prefix . $order->get_id(),
                'currency' => $order->get_currency(),
                'return_url' => $this->get_return_url($order),
                'webhook_url' => add_query_arg('wc_id', $order->get_id(), $this->webhook_url),
                'email' => $order->get_billing_email(),
                'value' => $order->get_total(),
                'gateway' => $_POST['payment_gateway'],
                'confirmations' => $this->confirmations
            ];

            $curl = curl_init('https://selly.gg/api/pay');
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($curl, CURLOPT_USERAGENT, 'Selly WooCommerce (PHP ' . PHP_VERSION . ')');
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($this->email . ':' . $this->api_key)]);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);

            if (curl_errno($curl)) {
                return wc_add_notice(__('Payment error:', 'woothemes') . 'Request error: ' . curl_error($curl), 'error');
            }

            curl_close($curl);
            $response = json_decode($response, true);

            if ($response['error']) {
                return wc_add_notice(__('Payment error:', 'woothemes') . 'Selly API error: ' . join($response['error']), 'error');
            } else {
                return $response['url'];
            }
        }


        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $payment = $this->generate_selly_payment($order);

            if ($payment) {
                return [
                    'result' => 'success',
                    'redirect' => $payment
                ];
            } else {
                return;
            }

        }

        /**
         * Handle webhooks
         *
         * @access public
         * @return void
         */
        function webhook_handler()
        {
            global $woocommerce;

            $data = json_decode(file_get_contents('php://input'), true);
            $selly_order = $this->valid_selly_order($data['id']);

            if ($selly_order) {
                $order = wc_get_order($_REQUEST['wc_id']);
                $this->log->add('selly', 'Order #' . $_REQUEST['wc_id'] . ' (' . $selly_order['id'] . '). Status: ' . $selly_order['status']);

                if ((int)$selly_order['status'] == 100) {
                    $order->payment_complete();
                } elseif ((int)$selly_order['status'] == 53) {
                    $order->update_status('on-hold', sprintf(__('Awaiting crypto currency confirmations', 'woocommerce')));
                } elseif ((int)$selly_order['status'] == 53) {
                    $order->update_status('refunded', sprintf(__('Selly payment refunded', 'woocommerce')));
                }
            }
        }

        /**
         * Validates content in webhook
         *
         * @access public
         * @return boolean
         */
        function valid_selly_order($order_id)
        {
            $curl = curl_init('https://selly.gg/api/orders/' . $order_id);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Selly WooCommerce (PHP ' . PHP_VERSION . ')');
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($this->email . ':' . $this->api_key)]);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl);

            curl_close($curl);
            $body = json_decode($response, true);

            if ($body['error']) {
                mail(get_option('admin_email'), sprintf(__('Unable to verify order via Selly Pay API', 'woocommerce'), $order_id));
                return null;
            } else {
                return $body;
            }
        }
    }
}
