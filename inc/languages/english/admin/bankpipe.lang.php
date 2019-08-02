<?php

// Settings
$l['bankpipe_pluginlibrary_missing'] = "<a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing. Please install it before doing anything else with bankpipe.";

$l['setting_group_bankpipe'] = "BankPipe Settings";
$l['setting_group_bankpipe_desc'] = "Manage your BankPipe settings, such as the currency, who is allowed to see and manage monetized attachments, notifications, etc..";
$l['setting_bankpipe_currency'] = "Currency";
$l['setting_bankpipe_currency_desc'] = "Choose a system-wide currency.";
$l['setting_bankpipe_usergroups_view'] = "Usergroups allowed to see";
$l['setting_bankpipe_usergroups_view_desc'] = "Choose what usergroups can see and purchase items and subscriptions. Leave blank to allow everyone.";
$l['setting_bankpipe_third_party'] = "Third party monetization";
$l['setting_bankpipe_third_party_desc'] = "Enable this option to allow your users monetize their own attachments and receive money on their personal gateways' wallet.";
$l['setting_bankpipe_usergroups_manage'] = "Usergroups allowed to manage";
$l['setting_bankpipe_usergroups_manage_desc'] = "Choose what usergroups can create and manage items. Subscriptions are manageable only by admins through the ACP. Leave blank to allow everyone. Applies if third party monetization option is enabled. Users with ACP access can always manage items, even with third party monetization option disabled.";
$l['setting_bankpipe_forums'] = "Allowed forums";
$l['setting_bankpipe_forums_desc'] = "Choose what forums can hold paid items. Leave blank to allow every forum.";
$l['setting_bankpipe_notification_uid'] = "Notification sender";
$l['setting_bankpipe_notification_uid_desc'] = "Enter the uid of the user you want to use as sender for expiry notifications by PM. If a notification is sent by email, the board's internal notification email is used regardless of this option. Leave blank to use the MyBB Engine.";
$l['setting_bankpipe_notification_cc_uids'] = "Notification BCCs";
$l['setting_bankpipe_notification_cc_uids_desc'] = "Enter a coma-separated list of uids to whom send a copy of expiry notifications as BCC. This will apply only to those notifications which method of delivery is set to 'Private message'.";
$l['setting_bankpipe_admin_notification'] = "Admin notification";
$l['setting_bankpipe_admin_notification_desc'] = "Enter a coma-separated list of uids to whom send a notification when an user purchases something. If you want to send the notification to a single user, just enter the uid without any other characters. Leave blank to not send any notification.";
$l['setting_bankpipe_admin_notification_method'] = "Admin notification method";
$l['setting_bankpipe_admin_notification_method_desc'] = "Choose the notification method for admin notifications.";
$l['setting_bankpipe_admin_notification_sender'] = "Admin notification sender";
$l['setting_bankpipe_admin_notification_sender_desc'] = "Enter the uid of the user you want to use as sender for admin notifications by PM. Leave blank to use the MyBB Engine.";
$l['setting_bankpipe_required_fields'] = "Required fields";
$l['setting_bankpipe_required_fields_desc'] = "Enter a coma-separated list of field names required to be filled before making a purchase through cart and subscriptions pages. Reference these fields with names (eg.: for a field named foo, use for example &lt;input name='foo' type='checkbox' value='1' /&gt;) in bankpipe_cart* and bankpipe_subscriptions* templates.";
$l['setting_bankpipe_pending_payments_cleanup'] = "Pending payments cleanup";
$l['setting_bankpipe_pending_payments_cleanup_desc'] = "Enter the number of days from their creation time you want to wait for pending payments to finalize. If payments are still not paid after this deadline, they will be deleted and the buyers will be notified by email. Defaults to 7.";

// Module
$l['bankpipe'] = 'BankPipe';
$l['bankpipe_overview'] = 'Overview';
$l['bankpipe_overview_desc'] = 'Manage your active subscription.';
$l['bankpipe_overview_available_subscriptions'] = 'Available subscriptions';

$l['bankpipe_logs'] = 'History';
$l['bankpipe_logs_desc'] = 'See and manage the list of payments correctly executed throughout your forum.';

// Subscriptions
$l['bankpipe_subscriptions'] = 'Subscriptions';
$l['bankpipe_add_subscription'] = 'New subscription';
$l['bankpipe_edit_subscription'] = 'Edit subscription – {1}';
$l['bankpipe_edit_subscription_tab'] = 'Edit subscription';
$l['bankpipe_subscriptions_desc'] = $l['bankpipe_edit_subscription_desc'] = 'See and manage the list of payments correctly executed throughout your forum.';

$l['bankpipe_subscriptions_name'] = 'Name';
$l['bankpipe_subscriptions_name_desc'] = 'The name of this subscription. It will be displayed to the user when purchasing this subscription.';
$l['bankpipe_subscriptions_description'] = 'Gateway description';
$l['bankpipe_subscriptions_description_desc'] = 'This description will be displayed when purchasing this subscription on the off-site gateway of the user\'s choice. You can add up to 127 characters, HTML is not supported.';
$l['bankpipe_subscriptions_wallet'] = 'Custom wallets';
$l['bankpipe_subscriptions_wallet_desc'] = 'Specify a custom wallet to send the money to when users purchase this subscription. If set, this will override the default merchant set in BankPipe\'s gateways settings.';
$l['bankpipe_subscriptions_htmldescription'] = 'Forum description';
$l['bankpipe_subscriptions_htmldescription_desc'] = 'The extended description will be displayed in the forum. You can add HTML and an unlimited amount of characters.';
$l['bankpipe_subscriptions_price'] = $l['bankpipe_subscriptions_price'] = 'Price';
$l['bankpipe_subscriptions_price_desc'] = 'Enter a price for this subscription. Use dots to separate decimals. Do not use anything to separate thousands.';
$l['bankpipe_subscriptions_usergroup'] = 'Destination usergroup';
$l['bankpipe_subscriptions_usergroup_desc'] = 'Select the primary usergroup the user will be assigned to after he successfully purchases this subscription.';
$l['bankpipe_subscriptions_change_primary'] = 'Change primary usergroup';
$l['bankpipe_subscriptions_change_primary_desc'] = 'If enabled, the user\'s primary usergroup will be changed upon subscribing; otherwise the destination usergroup will be added to his additional groups. When disabled, it allows your users to pay for multiple subscriptions.';
$l['bankpipe_subscriptions_discount'] = 'Discount';
$l['bankpipe_subscriptions_discount_desc'] = 'Enter a percentage value discount for this subscription. It will be applied if an user already bought a lower-priced subscription. The final price will be calculated as: this sub - (discount * highest lower-priced sub). Set to 0 to disable.';
$l['bankpipe_subscriptions_expires'] = 'Expiry days';
$l['bankpipe_subscriptions_expires_desc'] = 'Enter an expiry amount of days for this subscription. When provided, users will be reverted to the usergroup they were in before they purchased this subscription. Set to 0 to let this subscription last forever.';
$l['bankpipe_subscriptions_expiry_usergroup'] = 'Expiry usergroup';
$l['bankpipe_subscriptions_expiry_usergroup_desc'] = 'If an expiry amount of days is specified, users will be reverted to the primary usergroup they had before the subscription. This option lets you override the destination usergroup and when this subscription expires, users\' primary usergroup will be changed to this.';
$l['bankpipe_subscriptions_use_default_usergroup'] = 'Inherit user\'s primary usergroup';
$l['bankpipe_subscriptions_no_subscription'] = 'There are no subscriptions at the moment. <a href="index.php?module=config-bankpipe&action=subscriptions">Add subscription</a>.';
$l['bankpipe_subscriptions_delete'] = 'Delete selected subscription(s)';

// Notifications
$l['bankpipe_notifications'] = 'Notifications';
$l['bankpipe_notifications_desc'] = 'Manage expiry notifications. They can be sent to users whom subscriptions are about to expire or have already expired.';
$l['bankpipe_notifications_header_notification'] = 'Notification';
$l['bankpipe_notifications_header_days_before'] = 'Days sent before expiry';
$l['bankpipe_notifications_no_notification'] = 'There are no notifications at the moment. <a href="index.php?module=config-bankpipe&action=notifications&manage">Add notification</a>.';
$l['bankpipe_notifications_delete'] = 'Delete selected notification(s)';
$l['bankpipe_add_notification'] = 'Add notification';
$l['bankpipe_edit_notification'] = 'Edit notification – {1}';
$l['bankpipe_manage_notification_title'] = 'Title';
$l['bankpipe_manage_notification_title_desc'] = 'The title of this notification. Variables available: {user} = username, {name} = subscription name, {price} = subscription price, {days} = days left.';
$l['bankpipe_manage_notification_description'] = 'Message';
$l['bankpipe_manage_notification_description_desc'] = 'The message of this notification. Variables available are identical to the ones available for the title.';
$l['bankpipe_manage_notification_method'] = 'Notification method';
$l['bankpipe_manage_notification_method_desc'] = 'Choose whether to send the notification through PMs or email.';
$l['bankpipe_manage_notification_daysbefore'] = 'Days before expiry';
$l['bankpipe_manage_notification_daysbefore_desc'] = 'Enter an amount of days within which the notification will be issued. For example, setting it to 2 will send the notification 2 days before a subscription expires. Set to 0 to send when a notification expires.';

// Logs
$l['bankpipe_logs'] = 'Logs';
$l['bankpipe_logs_desc'] = 'View a complete transaction attempts log with eventual success or error messages generated by BankPipe\'s internal routines.';
$l['bankpipe_logs_header_user'] = $l['bankpipe_history_header_user'] = $l['bankpipe_downloadlogs_header_user'] = 'User';
$l['bankpipe_logs_header_action'] = 'Action taken';
$l['bankpipe_logs_header_items'] = 'Associated items';
$l['bankpipe_logs_header_date'] = $l['bankpipe_history_header_date'] = $l['bankpipe_downloadlogs_header_date'] = 'Date';
$l['bankpipe_logs_no_logs'] = 'There are currently no payment logs to display.';
$l['bankpipe_logs_error'] = 'Error';
$l['bankpipe_logs_created'] = 'Payment created';
$l['bankpipe_logs_refunded'] = 'Payment refunded';
$l['bankpipe_logs_success'] = 'Payment successful';
$l['bankpipe_logs_cancel'] = 'Payment cancelled';
$l['bankpipe_logs_manual_subscription'] = 'Manual subscription';
$l['bankpipe_logs_pending'] = 'Pending payment';
$l['bankpipe_logs_items_deleted'] = 'The items associated to this log have been deleted';
$l['bankpipe_logs_delete'] = 'Delete selected log(s)';

// Download logs
$l['bankpipe_downloadlogs'] = 'Download logs';
$l['bankpipe_downloadlogs_desc'] = 'View a complete downloads log with informations about attachments, users and purchase options.';
$l['bankpipe_downloadlogs_header_handling_method'] = 'Related purchase';
$l['bankpipe_downloadlogs_header_item'] = 'Item downloaded';
$l['bankpipe_downloadlogs_no_logs'] = 'There are currently no download logs to display.';
$l['bankpipe_downloadlogs_attachment_name'] = '<a href="{1}">{2}</a>';
$l['bankpipe_downloadlogs_usergroup_access'] = 'Access granted through usergroup permissions.';
$l['bankpipe_downloadlogs_access_not_granted'] = '<span style="color: red">This user has downloaded this attachment without proper access permissions.</span>';
$l['bankpipe_downloadlogs_cannot_fetch_item'] = 'Access granted through a single-item purchase, but the item has changed and could not be fetched.';
$l['bankpipe_downloadlogs_single_item_purchase'] = 'Access granted through a <a href="{1}">single-item purchase</a>.';

// History
$l['bankpipe_history'] = 'Payment history';
$l['bankpipe_history_desc'] = 'View a complete transactions log history and manage them singularly, including editing, revoking and refunding.';
$l['bankpipe_history_header_items'] = 'Items purchased';
$l['bankpipe_history_header_merchant'] = 'Merchant';
$l['bankpipe_history_header_revenue'] = 'Net revenue (fee)';
$l['bankpipe_history_header_expires'] = 'Expires';
$l['bankpipe_history_expires_never'] = 'Never';
$l['bankpipe_history_expires_expired'] = 'Expired';
$l['bankpipe_history_inactive'] = ' – Inactive';
$l['bankpipe_history_refunded'] = ' – Refunded';
$l['bankpipe_history_pending'] = ' – Pending';
$l['bankpipe_history_header_options'] = 'Options';
$l['bankpipe_history_no_payments'] = 'There are currently no payments completed with the specified parameters selected.';
$l['bankpipe_history_edit'] = 'Edit';
$l['bankpipe_history_refund'] = 'Refund';
$l['bankpipe_history_revoke'] = 'Revoke';
$l['bankpipe_history_revenue'] = '<b>Revenue</b>: {1}';

// Manual add
$l['bankpipe_manual_add'] = 'Subscribe users';
$l['bankpipe_manual_add_desc'] = 'This tool lets you manually add an user to a subscription. Refund options will be available only if you specify a valid transaction ID.';
$l['bankpipe_manual_add_user'] = 'Users to subscribe';
$l['bankpipe_manual_add_user_desc'] = 'Select who will be subscribed to the specified subscription.';
$l['bankpipe_manual_add_usergroup'] = 'Usergroups to subscribe';
$l['bankpipe_manual_add_usergroup_desc'] = 'Alternatively or additionally, choose one or more usergroups to subscribe all users belonging to them. This function does not consider additional usergroups.';
$l['bankpipe_manual_add_subscription'] = 'Subscription plan';
$l['bankpipe_manual_add_subscription_desc'] = 'Select the subscription plan you want to add these users in.';
$l['bankpipe_manual_add_start_date'] = 'Start date';
$l['bankpipe_manual_add_start_date_desc'] = 'Select a starting date for this subscription. Future dates are not accepted.';
$l['bankpipe_manual_add_end_date'] = 'Expiry date';
$l['bankpipe_manual_add_end_date_desc'] = 'Select an expiry date for this subscription. Future dates are accepted. If left blank, the subscription\'s default expiry amount of days will be applied calculated starting from the start date.';
$l['bankpipe_manual_add_sale_id'] = 'Sale ID';
$l['bankpipe_manual_add_sale_id_desc'] = '(Optional) Add a valid sale ID. When provided, this subscription will be refundable in the appropriate section for most of the available gateways. This will not work when multiple users are selected.';

// Manage purchase
$l['bankpipe_manage_purchase'] = 'Manage purchase';
$l['bankpipe_manage_purchase_desc'] = 'Edit or refund a purchase.';
$l['bankpipe_edit_purchase_name'] = 'Items bought';
$l['bankpipe_edit_purchase_total'] = 'Total';
$l['bankpipe_edit_purchase_fee'] = 'Fee';
$l['bankpipe_edit_purchase_merchant'] = 'Merchant';
$l['bankpipe_edit_purchase_date'] = 'Date of purchase';
$l['bankpipe_edit_purchase_bought_by'] = 'Bought by';
$l['bankpipe_edit_purchase_sale_id'] = 'Sale ID';
$l['bankpipe_edit_purchase_payment_id'] = 'Payment ID';
$l['bankpipe_edit_purchase_status'] = 'Status';
$l['bankpipe_edit_purchase_success'] = 'This purchase has been approved and the money has been transferred correctly.';
$l['bankpipe_edit_purchase_pending'] = 'This purchase is currently pending approval by the gateway. Ensure you can accept this payment within your gateway\'s settings.';
$l['bankpipe_edit_purchase_manual'] = 'This purchase has been created manually through the subscribing tool found in the Admin Control Panel. The transaction is unknown to BankPipe.';
$l['bankpipe_edit_purchase_refund'] = 'This purchase has been refunded, either partially or completely.';
$l['bankpipe_edit_purchase_refund_date'] = 'Refund date';
$l['bankpipe_edit_purchase_oldgid'] = 'Expiry usergroup';
$l['bankpipe_edit_purchase_oldgid_desc'] = 'Select the usergroup this user will be added to when this subscription will expire, if so.';
$l['bankpipe_edit_purchase_expires'] = 'Expiry date';
$l['bankpipe_edit_purchase_expires_desc'] = 'Select an expiry date for this subscription. Future dates are accepted.';
$l['bankpipe_edit_purchase_active'] = 'Active';
$l['bankpipe_edit_purchase_active_desc'] = 'Choose whether this subscription is active or not. If disabled, the buyer will be reverted to the usergroup he was in before this subscription and will lose all the associated privileges.';
$l['bankpipe_edit_purchase_no_group'] = 'No usergroup set';
$l['bankpipe_revoke_purchase_title'] = 'Revoke purchase';
$l['bankpipe_revoke_purchase'] = 'Are you sure you want to revoke this purchase? The buyer will be reverted to the usergroup he was in before this subscription and will lose all the associated privileges. You can reactivate this purchase at any time by editing it.';
$l['bankpipe_refund_purchase_cost'] = 'Item\'s original cost';
$l['bankpipe_refund_purchase_amount'] = 'Refund amount';
$l['bankpipe_refund_purchase_amount_desc'] = 'Enter a refund amount. If left blank or exceeding the original amount, this purchase will be refunded completely.';

// Discounts
$l['bankpipe_discounts'] = 'Discount codes';
$l['bankpipe_discounts_desc'] = 'View and manage your discount codes. They can be set up per subscription or item.';
$l['bankpipe_discounts_header_code'] = 'Code';
$l['bankpipe_discounts_header_value'] = 'Value';
$l['bankpipe_discounts_header_permissions'] = 'Allowed users/subs';
$l['bankpipe_discounts_header_expires'] = 'Expiry date';
$l['bankpipe_discounts_expires_never'] = 'Never';
$l['bankpipe_discounts_no_code'] = 'There are currently no discount codes with the specified parameters selected.';
$l['bankpipe_discounts_delete'] = 'Delete selected discount code(s)';
$l['bankpipe_discounts_text_users'] = 'users';
$l['bankpipe_discounts_text_usergroups'] = 'usergroups';
$l['bankpipe_discounts_text_items'] = 'items';
$l['bankpipe_discounts_text_users_singular'] = 'user';
$l['bankpipe_discounts_text_usergroups_singular'] = 'usergroup';
$l['bankpipe_discounts_text_items_singular'] = 'item';
$l['bankpipe_discounts_no_restrictions'] = 'No restrictions';
$l['bankpipe_manage_discount'] = 'Manage discount code';
$l['bankpipe_manage_discount_editing'] = 'Manage discount code - {1}';
$l['bankpipe_manage_discount_name'] = 'Name';
$l['bankpipe_manage_discount_name_desc'] = 'Enter the name of this coupon. It will be shown to the user when it will be redeemed.';
$l['bankpipe_manage_discount_code'] = 'Code';
$l['bankpipe_manage_discount_code_desc'] = 'Enter the code your users will be able to enter to receive the corresponding reduction. Infinite alphanumeric characters can be entered. [<a href="" id="random">Generate random</a>]';
$l['bankpipe_manage_discount_value'] = 'Value';
$l['bankpipe_manage_discount_value_desc'] = 'Enter the amount that will be deducted from the total when this code is applied. You can also choose how it will be calculated (percentage or absolute value).';
$l['bankpipe_manage_discount_stackable'] = 'Stackable';
$l['bankpipe_manage_discount_stackable_desc'] = 'If a discount code is marked as stackable, your users can use it alongside other stackable codes to add more discount to their items. Otherwise, this code will be used only alone.';
$l['bankpipe_manage_discount_expires'] = 'Expiry date';
$l['bankpipe_manage_discount_expires_desc'] = 'Enter an expiration date for this discount code. If omitted, it will last forever or until it is deleted.';
$l['bankpipe_manage_discount_permissions_usergroups'] = 'Allowed usergroups';
$l['bankpipe_manage_discount_permissions_usergroups_desc'] = 'Choose the usergroups allowed to use this discount code. Leave blank to allow every group.';
$l['bankpipe_manage_discount_permissions_items'] = 'Allowed items';
$l['bankpipe_manage_discount_permissions_items_desc'] = 'Choose the subscriptions and/or attachments allowed to use this discount code. Leave blank to allow every item.';
$l['bankpipe_manage_discount_permissions_users'] = 'Allowed users';
$l['bankpipe_manage_discount_permissions_users_desc'] = 'Choose the users allowed to use this discount code. Leave blank to allow every user.';
$l['search_for_an_item'] = 'Search for an item';

// Messages
$l['bankpipe_success_subscription_added'] = 'Subscription created successfully.';
$l['bankpipe_success_subscription_edited'] = 'Subscription edited successfully.';
$l['bankpipe_success_subscription_deleted'] = 'Subscription deleted successfully.';
$l['bankpipe_success_notification_added'] = 'Notification created successfully.';
$l['bankpipe_success_notification_edited'] = 'Notification edited successfully.';
$l['bankpipe_success_notification_deleted'] = 'Notification deleted successfully.';
$l['bankpipe_success_discount_added'] = 'Discount created successfully.';
$l['bankpipe_success_discount_edited'] = 'Discount edited successfully.';
$l['bankpipe_success_discount_deleted'] = 'Discount deleted successfully.';
$l['bankpipe_success_users_added'] = 'The user(s) you have specified have been subscribed successfully to the selected subscription plan.';
$l['bankpipe_success_deleted_selected_logs'] = 'The log(s) you have selected have been deleted successfully.';
$l['bankpipe_success_purchase_edited'] = 'The purchase has been edited successfully.';
$l['bankpipe_success_purchase_refunded'] = 'The purchase has been refunded successfully. {1} have been sent to the user. This transaction costed {2} to the merchant in refund fees.';
$l['bankpipe_success_purchase_revoked'] = 'The purchase has been revoked successfully and the user has been reverted to the usergroup he was in before.';
$l['bankpipe_success_updated'] = "BankPipe has been updated correctly from version {1} to {2}. Good job!";

$l['bankpipe_error_price_not_valid'] = 'The price you entered does not look to be a valid price. Please enter a positive number, either integer or with decimals separated by a dot.';
$l['bankpipe_error_invalid_item'] = 'This subscription appears to be invalid.';
$l['bankpipe_error_invalid_purchase'] = 'This purchase appears to be invalid.';
$l['bankpipe_error_no_value_provided'] = 'Please enter a valid discount value.';
$l['bankpipe_error_invalid_notification'] = 'This notification appears to be invalid.';
$l['bankpipe_error_missing_subscriptions'] = 'Before attempting to add an user to a subscription plan, please create a subscription plan.';
$l['bankpipe_error_incorrect_dates'] = 'The dates you selected are not valid. Please enter a past date for the starting one and a future date for the ending one.';
$l['bankpipe_error_needtoupdate'] = "You seem to have currently installed an outdated version of BankPipe. Please <a href=\"index.php?module=config-settings&update=bankpipe\">click here</a> to run the upgrade script.";
$l['bankpipe_error_duplicate_code'] = "The code you entered is already in use.";
$l['bankpipe_error_cannot_exceed_hundreds'] = "You cannot enter a value more than or equal to 100 when using percentages.";

// Tasks
$l['task_bankpipe_ran'] = 'Subscriptions and their buyers have been cleared and/or notified successfully.';
$l['bankpipe_notification_pending_payment_cancelled_title'] = 'Your order has been cancelled';
$l['bankpipe_notification_pending_payment_cancelled'] = 'Dear {1},
your order {2} has been deleted automatically after {3} days of waiting the payment. Please make sure you hold sufficient funds to perform the transaction.

Best regards,
{4}';

// Permissions
$l['viewing_field_candownloadpaidattachments'] = $l['bankpipe_can_dl_paid_attachs'] = 'Can download paid attachments without purchasing?';

// Misc
$l['bankpipe_options'] = 'Options';
$l['bankpipe_delete'] = 'Delete';
$l['bankpipe_save'] = 'Save';
$l['bankpipe_filter'] = 'Filter';
$l['bankpipe_filter_username'] = 'Username';
$l['bankpipe_filter_item'] = 'Item';
$l['bankpipe_filter_startingdate'] = 'Start date';
$l['bankpipe_filter_endingdate'] = 'End date';
$l['bankpipe_new_subscription'] = '<a href="index.php?module=config-bankpipe&action=subscriptions" style="float: right">Add subscription</a>';
$l['bankpipe_new_notification'] = '<a href="index.php?module=config-bankpipe&action=notifications&manage" style="float: right">Add notification</a>';
$l['bankpipe_new_discount'] = '<a href="index.php?module=config-bankpipe&action=discounts&manage" style="float: right">Add discount code</a>';
