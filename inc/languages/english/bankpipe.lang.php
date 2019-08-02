<?php

$l['bankpipe'] = "BankPipe";
$l['bankpipe_error_missing_tokens'] = "BankPipe's configuration is not complete yet. Please configure BankPipe tokens before using it.";
$l['bankpipe_not_allowed_to_buy'] = "You are not allowed to buy this item";

// Nav
$l['bankpipe_nav'] = "Payments and subscriptions";
$l['bankpipe_nav_subscriptions'] = "Subscriptions";
$l['bankpipe_nav_purchases'] = "Purchases";
$l['bankpipe_nav_manage'] = "My items";
$l['bankpipe_nav_cart'] = "Cart";

// Edit attachment
$l['bankpipe_update'] = "Update";
$l['bankpipe_item_price'] = "Item price";

// Management
$l['bankpipe_manage_title'] = "Items management";
$l['bankpipe_manage_wallet'] = "Wallets";
$l['bankpipe_manage_wallet_desc'] = "Add your wallet address(es) or ID(s) where you will receive funds when users purchase your items. If one or more fields are empty and you have some items set up, the board's wallets will be used instead.";
$l['bankpipe_manage_settings_save'] = "Save settings";
$l['bankpipe_manage_cat_attachment'] = $l['bankpipe_profile_cat_attachment'] = $l['bankpipe_purchases_cat_attachment'] = "Attachment";
$l['bankpipe_manage_cat_size'] = "Size";
$l['bankpipe_manage_cat_post'] = "Post";
$l['bankpipe_manage_cat_cost'] = $l['bankpipe_purchases_cat_cost'] = $l['bankpipe_subscriptions_cat_cost'] = $l['bankpipe_profile_cat_cost'] = $l['bankpipe_purchases_payment_cost'] = "Price";
$l['bankpipe_manage_no_items'] = "You currently do not have any paid items set. To create a paid item, upload an attachment in a post and add a price if possible. The attachment will be then available as a paid item and will be listed here. <b>In order to set up paid items you must have at least one wallet.</b>";

// Profile
$l['bankpipe_profile_no_purchases'] = "This user hasn't purchased any item at the moment.";
$l['bankpipe_profile_purchased_title'] = "Purchased items";
$l['bankpipe_profile_cat_purchased'] = $l['bankpipe_purchases_cat_purchased'] = "Purchased";

// Purchases
$l['bankpipe_purchases_title'] = "Purchases";
$l['bankpipe_purchases_no_purchases'] = "You haven't purchased any item at the moment.";
$l['bankpipe_purchases_expired'] = "Expired";
$l['bankpipe_purchases_refunded'] = "Refunded";
$l['bankpipe_purchases_pending'] = "Pending";
$l['bankpipe_purchases_inactive'] = "Inactive";
$l['bankpipe_purchases_sale_info'] = "Sale info";
$l['bankpipe_purchases_payment_item'] = "Item";
$l['bankpipe_purchases_payment_total'] = "Total";
$l['bankpipe_purchases_payment_date'] = "Date";
$l['bankpipe_purchases_payment_download'] = "Download";
$l['bankpipe_purchases_payment_merchant'] = "Merchant";
$l['bankpipe_purchases_payment_codes'] = "Discount codes";
$l['bankpipe_purchases_payment_savings'] = "Total savings";
$l['bankpipe_purchases_payment_transaction'] = "Transaction";
$l['bankpipe_purchases_payment_transaction_pending'] = "Transaction pending approval";
$l['bankpipe_purchases_payment_pending_note'] = "<b>Your purchase is currently pending confirmation</b>, and so does your access to the resources you bought. You will be notified once it will be approved or rejected.";
$l['bankpipe_purchases_cat_payment_id'] = "Payment";
$l['bankpipe_purchases_previous_subscription_discount'] = "Previous subscription";

// Cart
$l['bankpipe_cart_title'] = "Cart";
$l['bankpipe_cart_header_item'] = "Item";
$l['bankpipe_cart_header_cost'] = "Cost";
$l['bankpipe_cart_header_discount'] = "Discount";
$l['bankpipe_cart_no_items'] = "You currently have no items in your shopping cart.";
$l['bankpipe_cart_price'] = "Price: ";
$l['bankpipe_cart_item_removed'] = "Item removed successfully";
$l['bankpipe_cart_item_removed_desc'] = "The selected item has been removed successfully from your cart.";
$l['bankpipe_cart_item_added'] = "Item added successfully";
$l['bankpipe_cart_item_added_desc'] = "The selected item has been added successfully to your cart.";
$l['bankpipe_cart_merchant_different'] = "This item's merchant is different from the one set for other items in your cart. BankPipe can't handle multiple merchants at once. Please either finalize or empty the current cart to add this item to your cart.";
$l['bankpipe_cart_item_unknown'] = "This item does not exist.";
$l['bankpipe_discounts_promo_code'] = "Enter a promo code here";
$l['bankpipe_discounts_apply'] = "Apply";
$l['bankpipe_discounts_remove_code'] = "Remove code";
$l['bankpipe_payment_methods'] = "Payment methods";
$l['bankpipe_payment_method_continue'] = "Continue";
$l['bankpipe_payment_method_PayPal'] = "By clicking on <b>Continue</b> we will open a PayPal window, where you will be asked to finalize the payment. PayPal payments are instant.";
$l['bankpipe_payment_method_Coinbase'] = "By clicking on <b>Continue</b> we will open a Coinbase window, where you will be asked to finalize the payment to a wallet of your choice. Cryptocurrency payments need a certain number of confirmations before being approved: you will be notified accordingly when your payment is successful. You can close the window as soon as Coinbase detects a payment. <b>Network fees are your responsibility. Do not attempt to pay less than requested, or your order will be rejected</b>.";

// Discounts
$l['bankpipe_discount_applied'] = "The discount you have entered has been validated and applied successfully to your cart or subscriptions.";
$l['bankpipe_discounts_removed'] = "The selected discount has been removed successfully from your cart or subscriptions.";

// Subscriptions
$l['bankpipe_subscriptions_title'] = "Subscriptions";
$l['bankpipe_subscriptions_cat_subscription'] = "Subscription";
$l['bankpipe_subscriptions_cat_purchase'] = "Purchase";
$l['bankpipe_subscriptions_not_available'] = "There are currently no subscriptions available.";
$l['bankpipe_subscriptions_current_plan'] = "Current active plan";

// Notifications
$l['bankpipe_notification_merchant_purchase_title'] = "{1} has purchased some items for {2}{3}";
$l['bankpipe_notification_merchant_purchase'] = "Dear merchant,
[url={2}]{1}[/url] has purchased the following list of items:

[list]{3}[/list]

This transaction was [b]{4}{7}[/b], of which {5}{7} were retained in processing fees. A net revenue of [b]{6}{7}[/b] has been credited to {8}.

Best regards,
{9}";
$l['bankpipe_notification_buyer_purchase_title'] = "Order confirmation #{1}";
$l['bankpipe_notification_buyer_purchase'] = "Dear {1},
your order {2} has been confirmed. You have bought:

[list]{3}[/list]

You paid [b]{4}{5}[/b] to {6}. You can see a detailed breakdown of your purchase [url={7}]here[/url].

Best regards,
{8}";
$l['bankpipe_notification_merchant_underpaid_purchase_title'] = "{1} has placed an order with insufficient funds";
$l['bankpipe_notification_merchant_underpaid_purchase'] = "Dear merchant,
[url={2}]{1}[/url] has placed an order (#{5}) but he has sent insufficient funds ({3} {4}) to the wallet: {6}.

The transaction must be manually resolved by your side. Please get in touch with said user.

Best regards,
{7}";
$l['bankpipe_notification_buyer_underpaid_purchase_title'] = "Order #{1} has been marked as underpaid";
$l['bankpipe_notification_buyer_underpaid_purchase'] = "Dear {1},
your order {2} has been confirmed, but the system detected you have sent an insufficient amount of money to cover the price of the items. You can view a breakdown of your purchase [url={3}]here[/url].

The merchant has been notified with a similar automated message, and he has been asked to get in touch with you as soon as possible. Please allow some hours, depending on your timezone, to let the merchant reply. If you do not receive any communication within 24 hours, please contact an administrator specifying your Order ID (#{2}).

Best regards,
{4}";
$l['bankpipe_notification_merchant_crypto_purchase'] = "Dear merchant,
[url={2}]{1}[/url] has purchased the following list of items:

[list]{3}[/list]

This transaction was [b]{4}{5}[/b], which has been credited as [b]{6}{7}[/b] to your cryptocurrency exchange account.

Best regards,
{8}";
$l['bankpipe_notification_buyer_crypto_purchase'] = "Dear {1},
your order {2} has been confirmed. You have bought:

[list]{3}[/list]

You paid [b]{4}{5}[/b] by spending {6}{7}. You can see a detailed breakdown of your purchase [url={8}]here[/url].

Best regards,
{9}";
$l['bankpipe_notification_pending_payment_cancelled_webhooks_title'] = 'Your order has been cancelled';
$l['bankpipe_notification_pending_payment_cancelled_webhooks'] = 'Dear {1},
your order {2} has been deleted automatically as we did not detect any payment within the provided timeframe. Please try again.

Best regards,
{3}';

// Messages
$l['bankpipe_error_gateway_not_enabled'] = "This gateway has not been configured yet and it is therefore not available.";
$l['bankpipe_error_code_not_found'] = "This promo code does not appear to be valid.";
$l['bankpipe_error_code_expired'] = "This promo code has expired and is no longer valid.";
$l['bankpipe_error_code_not_allowed'] = "You are not allowed to apply this promo code.";
$l['bankpipe_error_code_not_allowed_stackable'] = "This promo code cannot be stacked to existing applied promo codes. Please remove the others and retry.";
$l['bankpipe_error_other_codes_not_allowed_stackable'] = "This promo code cannot be applied because a non-stackable code is already applied. Please remove it and retry.";
$l['bankpipe_error_code_already_applied'] = "This promo code has already been applied.";
$l['bankpipe_error_missing_required_field'] = "Some required fields are missing. Please ensure you have filled all the required fields before trying to purchase an item.";
$l['bankpipe_error_functionality_not_allowed'] = "You are not allowed to use this functionality. Please contact an administrator to ask for permissions.";
$l['bankpipe_error_module_not_exists'] = "This module does not exist.";
$l['bankpipe_error_webhook_signature_check_failed'] = 'Signature check failed. The webhook listener has encountered some problems communicating with the gateway.';
$l['bankpipe_error_webhook_no_matching_items'] = 'No matching orders were found with id: {1}';
$l['bankpipe_error_order_not_found'] = 'The order you are trying to send has expired or has been lost. Please go back and try again.';
$l['bankpipe_error_items_not_found'] = 'The items you are trying to purchase cannot be found. Please go back and try again.';
$l['bankpipe_error_email_not_valid'] = 'Merchant\'s email is not valid. This item cannot be purchased at the moment.';
$l['bankpipe_error_wallet_not_valid'] = 'Merchant\'s wallet is not valid. This item cannot be purchased at the moment.';
$l['bankpipe_error_cannot_cancel_order'] = 'You do not have enough permissions to do this or this order cannot be cancelled this way.';

$l['bankpipe_success_purchased_item'] = "You have successfully purchased {1}. You will be redirected to the purchase recap in some seconds.";
$l['bankpipe_success_settings_edited'] = "Settings edited";
$l['bankpipe_success_settings_edited_desc'] = "Your items management settings have been edited successfully.";

$l['bankpipe_payment_cancelled'] = 'You have cancelled the payment. If you want to buy the items you have selected, please continue with the payment.';
$l['bankpipe_payment_successful'] = 'Your payment has been processed successfully. Reference: {reference}. You will be redirected to the order recap in a moment.';
$l['bankpipe_payment_pending'] = 'Your payment is currently pending approval. You will be granted access as soon as the payment is resolved successfully.';

$l['bankpipe_response_malformed'] = 'The server returned a malformed response. A raw representation of the response is available in your browser\\\'s console. Please report it to an administrator.';

$l['bankpipe_discount_previous_item_desc'] = "This discount has been applied because you have already purchased another subscription in the past.";
$l['bankpipe_discount_code_desc'] = "This discount code has been validated and applied to your total.";

// Misc
$l['bankpipe_discounts_applied'] = "Discounts applied: {1}";
$l['bankpipe_discount_previous_item'] = "Previous subscriptions discount";
$l['bankpipe_add_to_cart'] = "Add to cart";
$l['bankpipe_remove_from_cart'] = "Remove from cart";
$l['bankpipe_cart_remove_item'] = "Remove";
$l['bankpipe_overlay_preparing_payment'] = "Preparing payment...";
$l['bankpipe_payment_waiting_confirmation'] = 'Waiting payment confirmation from the gateway...';
$l['bankpipe_payment_marked_as_unresolved'] = 'This payment has been marked as "unresolved" due to the following status code reason: {1}. You have to manually resolve this transaction.';

// Tasks
$l['task_bankpipe_ran'] = 'Subscriptions and their buyers have been cleared and/or notified successfully.';
$l['bankpipe_notification_pending_payment_cancelled_title'] = 'Your order has been cancelled';
$l['bankpipe_notification_pending_payment_cancelled'] = 'Dear {1},
your order {2} has been deleted automatically after {3} days of waiting the payment. Please make sure you hold sufficient funds to perform the transaction.

Best regards,
{4}';
