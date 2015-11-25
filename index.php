<?php
/*
Plugin Name: WooCommerce Payment gateways
Plugin URI: https://github.com/gammapartners
Description: WooCommerce Payment gateways (PayU)
Version: 2.0
Author: Rene Manqueros
Author URI: https://github.com/reneManqueros/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

class PayU_Middleware {
    static $api_key = '6u39nqhq8ftd0hlvnjfs66eh8c';
    static $api_login = '11959c415b33d0c';
    static $merchant_id = '500238';
    static $account_id = '500547';
    static $test_mode = true;

    static function do_payment($order_id, $description, $total, $email, $name, $dni, $card_number, $cvv, $expiration_Date, $offline_store, $is_offline){
        require_once __DIR__ .'/payu/PayU.php';
        try {
            $gp_options = get_option('woocommerce_GP_PayU_offline_Gateway_settings');

            PayU::$apiKey = $gp_options['api_key'];
            PayU::$apiLogin = $gp_options['api_login'];
            PayU::$merchantId = $gp_options['merchant_id'];
            PayU::$language = SupportedLanguages::ES;
            PayU::$isTest = $gp_options['environment'] == 'yes';

            if (PayU::$isTest == true) {
                Environment::setPaymentsCustomUrl("https://stg.api.payulatam.com/payments-api/4.0/service.cgi");
                Environment::setReportsCustomUrl("https://stg.api.payulatam.com/reports-api/4.0/service.cgi");
                $name = 'APPROVED';
            } else {
                Environment::setPaymentsCustomUrl("https://api.payulatam.com/payments-api/4.0/service.cgi");
                Environment::setReportsCustomUrl("https://api.payulatam.com/reports-api/4.0/service.cgi");
            }

            if ($is_offline == false) {
                if (preg_match('/^3[47][0-9]{13}$/', $card_number)) {
                    $paymentMethod = PaymentMethods::AMEX;
                } elseif (preg_match('/^5[1-5][0-9]{14}$/', $card_number)) {
                    $paymentMethod = PaymentMethods::MASTERCARD;
                } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $card_number)) {
                    $paymentMethod = PaymentMethods::VISA;
                }
            } else {
                PayU::$isTest = false;
                if ($offline_store == 'oxxo') {
                    $paymentMethod = PaymentMethods::OXXO;
                } elseif ($offline_store == 'seveneleven') {
                    $paymentMethod = PaymentMethods::SEVEN_ELEVEN;
                }
            }

            $reference_code = date("Ymd His - ") . $order_id;

            $base_parameters = array(
                PayUParameters::ACCOUNT_ID => PayU_Middleware::$account_id,
                PayUParameters::REFERENCE_CODE => $reference_code,
                PayUParameters::DESCRIPTION => $description,
                PayUParameters::VALUE => $total,
                PayUParameters::CURRENCY => "MXN",
                PayUParameters::BUYER_EMAIL => $email,
                PayUParameters::PAYER_DNI => $dni,
                PayUParameters::PAYMENT_METHOD => $paymentMethod,
                PayUParameters::COUNTRY => PayUCountries::MX,
                PayUParameters::EXPIRATION_DATE => "2016-09-27T00:00:00",
                PayUParameters::IP_ADDRESS => $_SERVER['REMOTE_ADDR'],
                PayUParameters::PAYER_NAME => $name
            );

            $online_parameters = array(
                PayUParameters::CREDIT_CARD_NUMBER => $card_number,
                PayUParameters::CREDIT_CARD_EXPIRATION_DATE => $expiration_Date,
                PayUParameters::CREDIT_CARD_SECURITY_CODE => $cvv,
                PayUParameters::PAYER_EMAIL, $email,
                PayUParameters::INSTALLMENTS_NUMBER => "1",
                PayUParameters::COUNTRY => PayUCountries::MX,
                PayUParameters::DEVICE_SESSION_ID => "vghs6tvkcle931686k1900o6e1",
                PayUParameters::PAYER_COOKIE => "11pt1t38347bs6jc9ruv2ecpv7o2",
                PayUParameters::USER_AGENT => $_SERVER['HTTP_USER_AGENT']
            );

            if ($is_offline == false) {
                $parameters = array_merge($base_parameters, $online_parameters);
            } else {
                $parameters = $base_parameters;
            }

            $response = PayUPayments::doAuthorizationAndCapture($parameters);

            if ($response) {
                $res = array(
                    'provider' => 'payu',
                    'order_id' => $order_id,
                    'reference_code' => $reference_code,
                    'state' => $response->transactionResponse->state,
                    'transaction_id' => $response->transactionResponse->transactionId,
                    'code' => $response->code,
                    'pending_reason' => $response->transactionResponse->pendingReason,
                    'payment_url' => $response->transactionResponse->extraParameters->URL_PAYMENT_RECEIPT_HTML,
                    'response_json' => json_encode($response)
                );

                do_action( 'gp_order_completed', json_encode($res));

                return $res;
            }
        }
        catch (PayUException  $e){
            do_action( 'gp_error_occurred', json_encode($e));
            throw new Exception( __( 'PayU. ' . $e, 'GP_PayU_offline_Gateway' ) );
        }

        catch(Exception $e){
            do_action( 'gp_error_occurred', json_encode($e));
            throw new Exception( __( 'Generic. ' . $e, 'GP_PayU_offline_Gateway' ) );
        }
    }
}

add_action( 'plugins_loaded', 'GP_payu_online_gateway_init', 0 );
function GP_payu_online_gateway_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    include_once( 'payu-online.php' );
    add_filter( 'woocommerce_payment_gateways', 'GP_payu_online_gateway' );
    function GP_payu_online_gateway( $methods ) {
        $methods[] = 'GP_PayU_online_Gateway';
        return $methods;
    }
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'GP_payu_online_gateway_links' );
function GP_payu_online_gateway_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'GP_PayU_online_Gateway' ) . '</a>',
    );

    return array_merge( $plugin_links, $links );


}

add_action( 'plugins_loaded', 'GP_payu_offline_gateway_init', 0 );
function GP_payu_offline_gateway_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    include_once( 'payu-offline.php' );
    add_filter( 'woocommerce_payment_gateways', 'GP_payu_offline_gateway' );
    function GP_payu_offline_gateway( $methods ) {
        $methods[] = 'GP_PayU_offline_Gateway';
        return $methods;
    }
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'GP_payu_offline_gateway_links' );
function GP_payu_offline_gateway_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'GP_PayU_offline_Gateway' ) . '</a>',
    );

    return array_merge( $plugin_links, $links );
}





add_action( 'plugins_loaded', 'GP_authorize_gateway_init', 0 );
function GP_authorize_gateway_init() {
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    // If we made it this far, then include our Gateway Class
    include_once( 'authorizenet.php' );

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'GP_authorize_gateway' );
    function GP_authorize_gateway( $methods ) {
        $methods[] = 'GP_authorize_gateway';
        return $methods;
    }
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'GP_authorize_gateway_links' );
function GP_authorize_gateway_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'GP_authorize_gateway' ) . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge( $plugin_links, $links );
}
