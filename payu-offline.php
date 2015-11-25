<?php

class GP_PayU_offline_Gateway extends WC_Payment_Gateway {

    // Setup our Gateway's id, description and other values
    function __construct() {
        $this->id = "GP_PayU_offline_Gateway";

        $this->method_title = __( "Offline PayU Gateway", 'GP_PayU_offline_Gateway' );
        $this->method_description = __( "Offline PayU Gateway for WooCommerce", 'GP_PayU_offline_Gateway' );
        $this->title = __( "Offline PayU Gateway", 'GP_PayU_offline_Gateway' );
        $this->icon = null;
        $this->has_fields = false;
        $this->init_form_fields();
        $this->init_settings();
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        if ( is_admin() ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }
    } // End __construct()


    public function payment_fields(){
        echo '<label>' . $this->select_store_title . '</label><br />';
        echo '<label class="offlinepaymentmethod">Oxxo<input name="GP_PayU_offline_Gateway-offlinemethod" value="oxxo" checked="checked" type="radio" /></label><br />';
        echo '<label class="offlinepaymentmethod">7-Eleven<input name="GP_PayU_offline_Gateway-offlinemethod" value="seveneleven" type="radio" /></label><br />';
    }

    // Build the administration fields for this specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'		=> __( 'Enable / Disable', 'GP_PayU_offline_Gateway' ),
                'label'		=> __( 'Enable this payment gateway', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'title' => array(
                'title'		=> __( 'Title', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'GP_PayU_offline_Gateway' ),
                'default'	=> __( 'Offline payments', 'GP_PayU_offline_Gateway' ),
            ),
            'select_store_title' => array(
                'title'		=> __( 'Select a store title', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Title the customer will see to select where he wants to pay.', 'GP_PayU_offline_Gateway' ),
                'default'	=> __( 'Select a store:', 'GP_PayU_offline_Gateway' ),
            ),
            'description' => array(
                'title'		=> __( 'Description', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'GP_PayU_offline_Gateway' ),
                'default'	=> __( 'Pay securely at a convenience store.', 'GP_PayU_offline_Gateway' ),
                'css'		=> 'max-width:350px;'
            ),
            'payment_description' => array(
                'title'		=> __( 'Payment Description', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Payment description the customer will see on his receipt. (order ID will be appended at the end) ', 'GP_PayU_offline_Gateway' ),
                'default'	=> __( 'My Store - Order #', 'GP_PayU_offline_Gateway' ),
                'css'		=> 'max-width:350px;'
            ),
            'thankyou_page_url' => array(
                'title'		=> __( 'Thank you page URL', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Thank you page the customer will be redirected to, order_id and payment_receipt will be passed on the querystring', 'GP_PayU_offline_Gateway' ),
                'default'	=> __( '/thankyou', 'GP_PayU_offline_Gateway' ),
                'css'		=> 'max-width:350px;'
            ),
            'mark_order' => array(
                'title'		=> __( 'Complete checkout', 'GP_PayU_offline_Gateway' ),
                'label'		=> __( 'Mark order / clear cart on successful checkout', 'GP_PayU_offline_Gateway' ),
                'desc_tip'	=> __( 'Marks the order as pending and empties the cart when the checkout is successful', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'checkbox',
                'default'	=> 'yes',
            ),
            'api_key' => array(
                'title'		=> __( 'API Key', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'API Key.', 'GP_PayU_offline_Gateway' ),
            ),
            'api_login' => array(
                'title'		=> __( 'API login', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'API login.', 'GP_PayU_offline_Gateway' ),
            ),
            'merchant_id' => array(
                'title'		=> __( 'Merchant ID', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Merchant ID.', 'GP_PayU_offline_Gateway' ),
            ),
            'account_id' => array(
                'title'		=> __( 'Account ID', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'AccountID.', 'GP_PayU_offline_Gateway' ),
            ),
            'environment' => array(
                'title'		=> __( 'Test mode?', 'GP_PayU_offline_Gateway' ),
                'label'		=> __( 'Enable Test Mode', 'GP_PayU_offline_Gateway' ),
                'type'		=> 'checkbox',
                'description' => __( 'Place the payment gateway in test mode.', 'GP_PayU_offline_Gateway' ),
                'default'	=> 'no',
            )
        );
    }


    // Submit payment and handle response
    public function process_payment( $order_id ) {
        try {
            global $woocommerce;
            $customer_order = new WC_Order($order_id);

            PayU_Middleware::$api_key = $this->api_key;
            PayU_Middleware::$api_login = $this->api_login;
            PayU_Middleware::$merchant_id = $this->merchant_id;
            PayU_Middleware::$account_id = $this->account_id;
            PayU_Middleware::$test_mode = $this->environment == 'yes';

            $payerName = $customer_order->billing_first_name . ' ' . $customer_order->billing_last_name;

            $method = $_POST["GP_PayU_offline_Gateway-offlinemethod"];

            $res = PayU_Middleware::do_payment($order_id, $this->payment_description . $order_id, $customer_order->order_total, $customer_order->billing_email, $payerName, '123', '', '', '', $method, true);

            if ($res['code'] == 'SUCCESS' && $res['state'] == "PENDING") {
                if($this->mark_order == 'yes') {
                    $woocommerce->cart->empty_cart();
                    $customer_order->update_status('pending');
                }
                return array(
                    'result' => 'success',
                    'redirect' => $this->thankyou_page_url.'?order_id=' . $order_id . '&receipt_url=' . $res['payment_url']
                );
            }
        }
        catch(PayUException $e){
            do_action( 'gp_error_occurred', $e);
        }
        catch(Exception $e){
            do_action( 'gp_error_occurred', $e);
        }
    }

}
