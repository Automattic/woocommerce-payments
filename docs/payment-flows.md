Explanation is available in paJDYF-6wC-p2 

This description is temporary, and will be repalced before/if the PR gets merged.

You can use https://mermaid-js.github.io/mermaid-live-editor/edit/ to view it.

```mermaid
graph TB
	classDef JS fill:#f2ef85
	classDef PHP fill:#a5e1f2
	classDef asyncJS fill:#f2ef85,stroke:#000,stroke-width:3px
	classDef asyncPHP fill:#a5e1f2,stroke:#000,stroke-width:3px
	classDef Stripe fill:#6058f5,color:#ffffff

	%% Start of checkout
    init{Checkout page loads}
	classic{{Classic checkout}}
    init --> classic
    init --> blocks

	%% Do we need to investigate the checkout block?
	blocks{{Checkout block}}
	blocks --> NaN(Investigation<br />needed?)

	%% UPE Preparation
    form_ready(<strong>Payment Fields Displayed</strong><br>Checkout form is ready at this point.)

	%% Getting payment fields ready
	%% create_intent_first and mount_upe_fields are defined in the UPE intent creation section below
	classic -->|UPE| create_intent_first
	mount_upe_fields --> form_ready
	classic -->|Non-UPE| form_ready

	%% Classic submission to create_and_confirm_intent
	form_ready -->|non-UPE Submit| block_form[Block submission of the form]:::JS
	subgraph Generate payment method
		block_form --> payment_method[<code>handlePaymentMethodCreation</code> retrieves<br>the payment method ID from Stripe.js<br>and stores it in a hidden field.]:::JS
		payment_method --> resubmit[Re-submit the form]:::JS
	end
	resubmit --> create_and_confirm_intent
	
  subgraph "Confirmation stage (where payments happen)"
    create_and_confirm_intent[<code>$gateway->process_payment_for_order</code><br>performs a <a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payment-gateway-wcpay.php#L1010'><b><code>create_and_confirm_intent</code></b></a> call.]:::PHP
    upe_confirm_intent["Call <a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/client/checkout/classic/upe.js#L520'><code>stripe.confirmPayment()</code></a><br>to finalize the payment."]:::JS
  end

	%% Classic successful `create_and_confirm_intent` processing to redirect
	create_and_confirm_intent -->|<code>status: succeeded</code>| intent_is_successful(Payment is successful)
	intent_is_successful --> attach_info_to_order
	mark_payment_completed --> php_redirect[<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payment-gateway-wcpay.php#L1206'>Redirect to the Order Received page</a>]:::PHP
	
	%% Classic `requires_action` to both marking the order as completed, and a redirect.
	create_and_confirm_intent -->|<code>status: requires_action</code>| return_to_checkout["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payment-gateway-wcpay.php#L1149'>Return a redirect</a> to the same checkout<br>page with <code>$order_id</code> and <code>$client_secret</code><br> for authenticating the payment"]:::PHP
	return_to_checkout --> confirm_payment["Use the client secret to confirm the intent<br>through <a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/client/checkout/classic/index.js#L393'><code>stripe.confirmCardPayment()</code></a>"]:::JS
	confirm_payment --> update_order_status_request[AJAX <a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/client/checkout/api/index.js#L284'><code>update_order_status</code></a> call]:::JS
	update_order_status_request --->|Intent & Order IDs| attach_info_to_order_2
	mark_payment_completed_2 -->|Return URL| js_redirect[<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/client/checkout/classic/index.js#L420'>Redirect to the Order Received page</a>]:::JS

	%% Classic webhook on the side.
	confirm_payment --> stripe_webhook
	intent_is_successful --> stripe_webhook(Stripe sends a webhook<br><code>payment_intent.succeeded</code>):::Stripe
	stripe_webhook --> webhook_handler["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payments-webhook-processing-service.php#L398'><code>WC_Payments_Webhook_Processing_Service::<br>process_webhook_payment_intent_succeeded()</code></a>"]:::PHP
	webhook_handler --> webhook_meta_update

	%% UPE intent creation
	create_intent_first[<a href='https://github.com/Automattic/woocommerce-payments/blob/develop/client/checkout/api/index.js#L379-L401'>Create intent in advance through a<br><code>create_payment_intent</code> AJAX call</a>]:::JS
	create_intent_first --> mount_upe_fields[<a href='https://github.com/Automattic/woocommerce-payments/blob/develop/client/checkout/classic/upe.js#L207-L211'>Mount the UPE fields with the intent ID</a>]:::JS
	
	%% UPE Submission

	form_ready -->|UPE Submit| upe_ajax["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/client/checkout/classic/upe.js#L499-L507'><strong>Process the checkout form via WC AJAX</strong></a><br>Normally this should not be needed, but<br>we do it for <a href='https://github.com/Automattic/woocommerce-payments/pull/2430'>UPE compatibility with multi-currency</a>"]:::JS
	upe_ajax -->|<code>wc_payment_intent_id</code>| upe_checkout["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/payment-methods/class-upe-payment-gateway.php#L421'><code>$gateway->process_payment()</code></a><br><a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/payment-methods/class-upe-payment-gateway.php#L460-L470'>updates the intent with order data</a>"]:::PHP
	upe_checkout --> upe_attach_info["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/payment-methods/class-upe-payment-gateway.php#L489'><code>attach_intent_info_to_order()</code></a> stores intent<br>ID, but the payment method is not available yet"]:::PHP
	upe_attach_info --> upe_update_order["Because of the <code>requires_payment_method</code> intent status,<br><a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/payment-methods/class-upe-payment-gateway.php#L490'><code>update_order_status_from_intent()</code></a> adds a payment started note."]:::PHP
	upe_update_order --> upe_proceed_in_js[PHP sends a <a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/payment-methods/class-upe-payment-gateway.php#L506'><code>payment_needed</code></a> flag back to JS]:::PHP
	upe_proceed_in_js -->|payment_needed, return_url| upe_handle_confirmation["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/client/checkout/classic/upe.js#L504-L517'>Data for <code>handlePaymentConfirmation()</code><br>gets prepared, and it is called to confirm the intention</a>"]:::JS
	upe_handle_confirmation --> upe_confirm_intent
	upe_confirm_intent --> upe_check_intent_status[<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/client/checkout/api/index.js#L472-L474'>Check the status of the intent</a>]:::asyncJS
	upe_confirm_intent --> stripe_webhook
	upe_check_intent_status --> upe_redirect_to_thank_you_page[<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/client/checkout/api/index.js#L475-L481'>Redirect to the Order Received page</a>]:::JS
	upe_redirect_to_thank_you_page -->|wcpay_process_redirect_order_nonce| upe_handle_redirect["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/payment-methods/class-upe-payment-gateway.php#L539'><code>maybe_process_upe_redirect()</code></a><br />verifies the request"]:::PHP
	upe_handle_redirect --> upe_process_upe_redirect_order{<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/payment-methods/class-upe-payment-gateway.php#L600'>Order is pending payment?</a><br>It could have been completed<br>through a webhook}:::PHP
	upe_process_upe_redirect_order -->|No| upe_display_page[Display the Order Received page as usual]:::PHP
	upe_process_upe_redirect_order -->|Yes| upe_attach_info_to_order
	upe_mark_payment_completed --> upe_display_page

  subgraph "Order status updates and attachment of data"
    attach_info_to_order_2["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payment-gateway-wcpay.php#L2404'><code>attach_intent_info_to_order()</code></a><br>stores the intent and charge IDs"]:::PHP
    attach_info_to_order_2 ---> mark_payment_completed_2["Mark order as complete through<br /><a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payment-gateway-wcpay.php#L2414'><code>$order_service->mark_payment_completed</code></a>"]:::PHP

    webhook_meta_update[<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payments-webhook-processing-service.php#L414-L428'>Manual metadata update</a>]:::asyncPHP
		webhook_meta_update --> webhook_mark_payment_completed["Mark order as complete through<br /><a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payments-webhook-processing-service.php#L430'><code>$order_service->mark_payment_completed()</code></a>"]:::asyncPHP

    attach_info_to_order["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payment-gateway-wcpay.php#L1174'><code>attach_intent_info_to_order()</code></a><br>stores the intent and charge IDs"]:::PHP
		attach_info_to_order ---> mark_payment_completed["Mark order as complete through<br /><a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/class-wc-payment-gateway-wcpay.php#L1176'><code>$order_service->mark_payment_completed()</code></a>"]:::PHP

    upe_attach_info_to_order["<a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/payment-methods/class-upe-payment-gateway.php#L657'><code>attach_intent_info_to_order()</code></a><br>stores the intent and charge IDs"]:::PHP
		upe_attach_info_to_order ---> upe_mark_payment_completed["Mark order as complete through<br /><a href='https://github.com/Automattic/woocommerce-payments/blob/472fd4fbc29a4cd33e6f8870bd8f6612a8a113f4/includes/payment-methods/class-upe-payment-gateway.php#L659'><code>$order_service->mark_payment_completed()</code></a>"]:::PHP
  end

	%% At the end
	done(<b>Done:</b><br >Customer sees the order received page.)
	js_redirect --> done
	php_redirect --> done
	upe_display_page --> done
```
