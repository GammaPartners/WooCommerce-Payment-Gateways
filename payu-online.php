<?php

class GP_PayU_online_Gateway extends WC_Payment_Gateway {

    function __construct() {
        $this->id = "GP_PayU_online_Gateway";
        $this->method_title = __( "Online PayU Gateway", 'GP_PayU_online_Gateway' );
        $this->method_description = __( "PayU Gateway for WooCommerce", 'GP_PayU_online_Gateway' );
        $this->title = __( "Online PayU Gateway", 'GP_PayU_online_Gateway' );
        $this->icon = null;
        $this->has_fields = true;
        $this->supports = array( 'default_credit_card_form' );
        $this->init_form_fields();
        $this->init_settings();
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }
        if ( is_admin() ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'		=> __( 'Enable / Disable', 'GP_PayU_online_Gateway' ),
                'label'		=> __( 'Enable this payment gateway', 'GP_PayU_online_Gateway' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'title' => array(
                'title'		=> __( 'Title', 'GP_PayU_online_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'GP_PayU_online_Gateway' ),
                'default'	=> __( 'Credit card', 'GP_PayU_online_Gateway' ),
            ),
            'description' => array(
                'title'		=> __( 'Description', 'GP_PayU_online_Gateway' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'GP_PayU_online_Gateway' ),
                'default'	=> __( 'Pay securely using your credit card.', 'GP_PayU_online_Gateway' ),
                'css'		=> 'max-width:350px;'
            ),
            'payment_description' => array(
                'title'		=> __( 'Payment Description', 'GP_PayU_online_Gateway' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Payment description the customer will see on his receipt. (order ID will be appended at the end) ', 'GP_PayU_online_Gateway' ),
                'default'	=> __( 'My Store - Order #', 'GP_PayU_online_Gateway' ),
                'css'		=> 'max-width:350px;'
            ),
            'thankyou_page_url' => array(
                'title'		=> __( 'Thank you page URL', 'GP_PayU_online_Gateway' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Thank you page the customer will be redirected to, order_id will be passed on the querystring', 'GP_PayU_online_Gateway' ),
                'default'	=> __( '/thankyou', 'GP_PayU_online_Gateway' ),
                'css'		=> 'max-width:350px;'
            ),
            'mark_order' => array(
                'title'		=> __( 'Complete checkout', 'GP_PayU_online_Gateway' ),
                'label'		=> __( 'Mark order / clear cart on successful checkout', 'GP_PayU_online_Gateway' ),
                'desc_tip'	=> __( 'Marks the order as completed and empties the cart when the checkout is successful', 'GP_PayU_online_Gateway' ),
                'type'		=> 'checkbox',
                'default'	=> 'yes',
            ),
            'api_key' => array(
                'title'		=> __( 'API Key', 'GP_PayU_online_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'API Key.', 'GP_PayU_online_Gateway' ),
            ),
            'api_login' => array(
                'title'		=> __( 'API login', 'GP_PayU_online_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'API login.', 'GP_PayU_online_Gateway' ),
            ),
            'merchant_id' => array(
                'title'		=> __( 'Merchant ID', 'GP_PayU_online_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Merchant ID.', 'GP_PayU_online_Gateway' ),
            ),
            'account_id' => array(
                'title'		=> __( 'Account ID', 'GP_PayU_online_Gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'AccountID.', 'GP_PayU_online_Gateway' ),
            ),
            'environment' => array(
                'title'		=> __( 'Test mode?', 'GP_PayU_online_Gateway' ),
                'label'		=> __( 'Enable Test Mode', 'GP_PayU_online_Gateway' ),
                'type'		=> 'checkbox',
                'description' => __( 'Place the payment gateway in test mode.', 'GP_PayU_online_Gateway' ),
                'default'	=> 'no',
            )
        );
    }

    public function process_payment( $order_id ) {
        try {
            global $woocommerce;
            $customer_order = new WC_Order($order_id);

            PayU_Middleware::$api_key = $this->api_key;
            PayU_Middleware::$api_login = $this->api_login;
            PayU_Middleware::$merchant_id = $this->merchant_id;
            PayU_Middleware::$account_id = $this->account_id;
            PayU_Middleware::$test_mode = $this->environment == 'yes';

            $cardNumber = str_replace(array(' ', ''), '', $_POST['GP_PayU_online_Gateway-card-number']);

            $expirationArray = explode('/', $_POST['GP_PayU_online_Gateway-card-expiry']);
            $expirationDate = '20' . $expirationArray[1] . '/' . $expirationArray[0];
            $expirationDate = str_replace(' ', '', $expirationDate);
            $payerName = $customer_order->billing_first_name . ' ' . $customer_order->billing_last_name;
            $cvv = $_POST['GP_PayU_online_Gateway-card-cvc'];

            $res = PayU_Middleware::do_payment($order_id, $this->payment_description . $order_id, $customer_order->order_total, $customer_order->billing_email, $payerName, '111', $cardNumber, $cvv, $expirationDate, '', false);

            if(isset($res['code']) == true && isset($res['state']) == true && $res['code'] == 'SUCCESS' && $res['state'] == "APPROVED") {
                do_action( 'gp_order_online_completed_successfully', $res);

                if($this->mark_order == 'yes'){
                    $woocommerce->cart->empty_cart();
                    $customer_order->payment_complete();
                    $customer_order->update_status('completed');
                }

                return array(
                    'result' => 'success',
                    'redirect' =>  $this->thankyou_page_url.'?order_id=' . $order_id
                );
            }
            else {
                do_action( 'gp_order_online_completed_failed', $res);
            }
        }
        catch (PayUException  $e){
            do_action( 'gp_error_occurred', $e);
        }

        catch(Exception $e){
            do_action( 'gp_error_occurred', $e);
        }
    }
}
