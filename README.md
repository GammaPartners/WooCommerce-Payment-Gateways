## GP payment gateways

Allows customers to pay using several payment gateways:
* Authorize.net - Credit card only.
* PayU (Latin America) - Credit card + offline payments on 7-Eleven, Oxxo's.

## Usage:

This plug-in creates new settings pages under Woocommerce->Settings->Checkout.
Fill out the account information on them for each provider that will be activated.

## Hooks:
Two hooks are exposed, for a basic workflow in which the order needs to be marked as complete or users will send a 
receipt for offline payments, these hooks are not needed, they are meant for more complex and custom workflows.

* gp_order_online_completed_successfully - This hook is triggered when the payment is completed. Sends an array with 
    the gateway's response information and the order ID to the callback.
* gp_error_occurred - This hook is triggered when an error occurs during the payment process. Sends the exception 
    to the callback.



