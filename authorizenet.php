<?php
class GP_authorize_gateway extends WC_Payment_Gateway {

    function __construct() {
        $this->id = "GP_authorize_gateway";
        $this->method_title = __( "Authorize.net AIM", 'GP_authorize_gateway' );
        $this->method_description = __( "Authorize.net AIM Payment Gateway Plug-in for WooCommerce", 'GP_authorize_gateway' );
        $this->title = __( "Authorize.net AIM", 'GP_authorize_gateway' );
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
                'title'		=> __( 'Enable / Disable', 'GP_authorize_gateway' ),
                'label'		=> __( 'Enable this payment gateway', 'GP_authorize_gateway' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'title' => array(
                'title'		=> __( 'Title', 'GP_authorize_gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'GP_authorize_gateway' ),
                'default'	=> __( 'Credit card', 'GP_authorize_gateway' ),
            ),
            'description' => array(
                'title'		=> __( 'Description', 'GP_authorize_gateway' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'GP_authorize_gateway' ),
                'default'	=> __( 'Pay securely using your credit card.', 'GP_authorize_gateway' ),
                'css'		=> 'max-width:350px;'
            ),
            'mark_order' => array(
                'title'		=> __( 'Complete checkout', 'GP_authorize_gateway' ),
                'label'		=> __( 'Mark order / clear cart on successful checkout', 'GP_PayU_online_Gateway' ),
                'desc_tip'	=> __( 'Marks the order as completed and empties the cart when the checkout is successful', 'GP_PayU_online_Gateway' ),
                'type'		=> 'checkbox',
                'default'	=> 'yes',
            ),
            'api_login' => array(
                'title'		=> __( 'Authorize.net API Login', 'GP_authorize_gateway' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'This is the API Login provided by Authorize.net when you signed up for an account.', 'GP_authorize_gateway' ),
            ),
            'trans_key' => array(
                'title'		=> __( 'Authorize.net Transaction Key', 'GP_authorize_gateway' ),
                'type'		=> 'password',
                'desc_tip'	=> __( 'This is the Transaction Key provided by Authorize.net when you signed up for an account.', 'GP_authorize_gateway' ),
            ),
            'environment' => array(
                'title'		=> __( 'Authorize.net Test Mode', 'GP_authorize_gateway' ),
                'label'		=> __( 'Enable Test Mode', 'GP_authorize_gateway' ),
                'type'		=> 'checkbox',
                'description' => __( 'Place the payment gateway in test mode.', 'GP_authorize_gateway' ),
                'default'	=> 'no',
            )
        );
    }

    public function process_payment( $order_id ) {
        global $woocommerce;
        $customer_order = new WC_Order( $order_id );
        $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

        $environment_url = ( "FALSE" == $environment )
            ? 'https://secure.authorize.net/gateway/transact.dll'
            : 'https://test.authorize.net/gateway/transact.dll';


        $payload = array(
            // Authorize.net Credentials and API Info
            "x_tran_key"           	=> $this->trans_key,
            "x_login"              	=> $this->api_login,
            "x_version"            	=> "3.1",
            "x_amount"             	=> $customer_order->order_total,
            "x_card_num"           	=> str_replace( array(' ', '-' ), '', $_POST['GP_authorize_gateway-card-number'] ),
            "x_card_code"          	=> ( isset( $_POST['GP_authorize_gateway-card-cvc'] ) ) ? $_POST['GP_authorize_gateway-card-cvc'] : '',
            "x_exp_date"           	=> str_replace( array( '/', ' '), '', $_POST['GP_authorize_gateway-card-expiry'] ),
            "x_type"               	=> 'AUTH_CAPTURE',
            "x_invoice_num"        	=> str_replace( "#", "", $customer_order->get_order_number() ),
            "x_test_request"       	=> $environment,
            "x_delim_char"         	=> '|',
            "x_encap_char"         	=> '',
            "x_delim_data"         	=> "TRUE",
            "x_relay_response"     	=> "FALSE",
            "x_method"             	=> "CC",
            "x_first_name"         	=> $customer_order->billing_first_name,
            "x_last_name"          	=> $customer_order->billing_last_name,
            "x_address"            	=> $customer_order->billing_address_1,
            "x_city"              	=> $customer_order->billing_city,
            "x_state"              	=> $customer_order->billing_state,
            "x_zip"                	=> $customer_order->billing_postcode,
            "x_country"            	=> $customer_order->billing_country,
            "x_phone"              	=> $customer_order->billing_phone,
            "x_email"              	=> $customer_order->billing_email,
            "x_ship_to_first_name" 	=> $customer_order->shipping_first_name,
            "x_ship_to_last_name"  	=> $customer_order->shipping_last_name,
            "x_ship_to_company"    	=> $customer_order->shipping_company,
            "x_ship_to_address"    	=> $customer_order->shipping_address_1,
            "x_ship_to_city"       	=> $customer_order->shipping_city,
            "x_ship_to_country"    	=> $customer_order->shipping_country,
            "x_ship_to_state"      	=> $customer_order->shipping_state,
            "x_ship_to_zip"        	=> $customer_order->shipping_postcode,
            "x_cust_id"            	=> $customer_order->user_id,
            "x_customer_ip"        	=> $_SERVER['REMOTE_ADDR'],
        );

        $response = wp_remote_post( $environment_url, array(
            'method'    => 'POST',
            'body'      => http_build_query( $payload ),
            'timeout'   => 90,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) )
            do_action( 'gp_order_online_completed_failed', $response);

        if ( empty( $response['body'] ) )
            do_action( 'gp_order_online_completed_failed', $response);

        $response_body = wp_remote_retrieve_body( $response );

        // Parse the response into something we can read
        foreach ( preg_split( "/\r?\n/", $response_body ) as $line ) {
            $resp = explode( "|", $line );
        }

        // Get the values we need
        $r['response_code']             = $resp[0];
        $r['response_sub_code']         = $resp[1];
        $r['response_reason_code']      = $resp[2];
        $r['response_reason_text']      = $resp[3];

        if ( ( $r['response_code'] == 1 ) || ( $r['response_code'] == 4 ) ) {
            $customer_order->add_order_note( __( 'Authorize.net payment completed.', 'GP_authorize_gateway' ) );

            if($this->mark_order == 'yes'){
                $woocommerce->cart->empty_cart();
                $customer_order->payment_complete();
                $customer_order->update_status('completed');
            }

            do_action( 'gp_order_online_completed_successfully', $response);

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $customer_order ),
            );
        } else {
            do_action( 'gp_error_occurred', $r['response_reason_text']);
        }
    }
}