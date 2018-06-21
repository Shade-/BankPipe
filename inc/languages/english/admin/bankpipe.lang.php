<?php

// Settings
$l['bankpipe_pluginlibrary_missing'] = "<a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing. Please install it before doing anything else with bankpipe.";

$l['setting_group_bankpipe'] = "BankPipe Settings";
$l['setting_group_bankpipe_desc'] = "Manage your BankPipe settings, such as your client tokens, the default payee and other options.";
$l['setting_bankpipe_subscription_payee'] = "Default payee";
$l['setting_bankpipe_subscription_payee_desc'] = "Enter the email address of the default payee where money will be sent to. If he does not have a PayPal account yet, he will be asked to create one when he receives his first payment. This is used to create subscriptions from the ACP.";
$l['setting_bankpipe_client_id'] = "Client ID";
$l['setting_bankpipe_client_id_desc'] = "Enter your PayPal client ID. If you are using sandbox mode, ensure you add the sandbox client ID.";
$l['setting_bankpipe_client_secret'] = "Client secret";
$l['setting_bankpipe_client_secret_desc'] = "Enter your PayPal client secret. If you are using sandbox mode, ensure you add the sandbox client secret.";
$l['setting_bankpipe_sandbox'] = "Sandbox mode";
$l['setting_bankpipe_sandbox_desc'] = "Enable this option to use PayPal's sandbox API endpoints. Inside the sandbox, your users do not exchange real money.";
$l['setting_bankpipe_currency'] = "Currency";
$l['setting_bankpipe_currency_desc'] = "Choose a system-wide currency.";
$l['setting_bankpipe_usergroups_view'] = "Usergroups allowed to see";
$l['setting_bankpipe_usergroups_view_desc'] = "Choose what usergroups can see and purchase items and subscriptions. Leave blank to allow everyone.";
$l['setting_bankpipe_third_party'] = "Third party monetization";
$l['setting_bankpipe_third_party_desc'] = "Enable this option to allow your users monetize their own attachments and receive money on their personal PayPal account.";
$l['setting_bankpipe_usergroups_manage'] = "Usergroups allowed to manage";
$l['setting_bankpipe_usergroups_manage_desc'] = "Choose what usergroups can create and manage items. Subscriptions are manageable only by admins through the ACP. Leave blank to allow everyone. Applies if third party monetization option is enabled. Users with ACP access can always manage items, even with third party monetization option disabled.";
$l['setting_bankpipe_forums'] = "Allowed forums";
$l['setting_bankpipe_forums_desc'] = "Choose what forums can hold paid items. Leave blank to allow every forum.";
$l['setting_bankpipe_notification_uid'] = "Notification sender";
$l['setting_bankpipe_notification_uid_desc'] = "Enter the uid of the user you want to use as sender for expire notifications by PM. If a notification is sent by email, the board's internal notification email is used regardless of this option. Leave blank to use the MyBB Engine.";

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
$l['bankpipe_manage_subscription_desc'] = $l['bankpipe_edit_subscription_desc'] = 'See and manage the list of payments correctly executed throughout your forum.';

$l['bankpipe_manage_subscription_name'] = $l['bankpipe_subscriptions_name'] = 'Name';
$l['bankpipe_manage_subscription_name_desc'] = 'The name of this subscription. It will be displayed to the user when purchasing this subscription.';
$l['bankpipe_manage_subscription_description'] = 'PayPal description';
$l['bankpipe_manage_subscription_description_desc'] = 'This description will be displayed to the user when purchasing this subscription on PayPal. You can add up to 127 characters, HTML is not supported.';
$l['bankpipe_manage_subscription_htmldescription'] = 'Forum description';
$l['bankpipe_manage_subscription_htmldescription_desc'] = 'The extended description will be displayed in the forum. You can add HTML and an unlimited amount of characters.';
$l['bankpipe_manage_subscription_price'] = $l['bankpipe_subscriptions_price'] = 'Price';
$l['bankpipe_manage_subscription_price_desc'] = 'Enter a price for this subscription. Use dots to separate decimals. Do not use anything to separate thousands.';
$l['bankpipe_manage_subscription_usergroup'] = 'Destination usergroup';
$l['bankpipe_manage_subscription_usergroup_desc'] = 'Select the primary usergroup the user will be assigned to after he successfully purchases this subscription.';
$l['bankpipe_manage_subscription_change_primary'] = 'Change primary usergroup';
$l['bankpipe_manage_subscription_change_primary_desc'] = 'If enabled, the user\'s primary usergroup will be changed upon subscribing; otherwise the destination usergroup will be added to his additional groups. When disabled, it allows your users to pay for multiple subscriptions.';
$l['bankpipe_manage_subscription_discount'] = 'Discount';
$l['bankpipe_manage_subscription_discount_desc'] = 'Enter a percentage value discount for this subscription. It will be applied if an user already bought a lower-priced subscription. The final price will be calculated as: this sub - (discount * highest lower-priced sub). Set to 0 to disable.';
$l['bankpipe_manage_subscription_expires'] = 'Expiry days';
$l['bankpipe_manage_subscription_expires_desc'] = 'Enter an expiry amount of days for this subscription. When provided, users will be reverted to the usergroup they were in before they purchased this subscription. Set to 0 to let this subscription last forever.';
$l['bankpipe_manage_subscription_expiry_usergroup'] = 'Expiry usergroup';
$l['bankpipe_manage_subscription_expiry_usergroup_desc'] = 'If an expiry amount of days is specified, users will be reverted to the primary usergroup they had before the subscription. This option lets you override the destination usergroup and when this subscription expires, users\' primary usergroup will be changed to this.';
$l['bankpipe_manage_subscription_use_default_usergroup'] = 'Inherit user\'s primary usergroup';
$l['bankpipe_save'] = 'Save';
$l['bankpipe_subscriptions_no_subscription'] = 'There are no subscriptions at the moment. <a href="index.php?module=config-bankpipe&amp;action=manage_subscription">Add subscription</a>.';

// Notifications
$l['bankpipe_notifications'] = 'Notifications';
$l['bankpipe_notifications_desc'] = 'Manage expiry notifications. They can be sent to users whom subscriptions are about to expire or have already expired.';
$l['bankpipe_notifications_header_notification'] = 'Notification';
$l['bankpipe_notifications_header_days_before'] = 'Days sent before expiry';
$l['bankpipe_notifications_no_notification'] = 'There are no notifications at the moment. <a href="index.php?module=config-bankpipe&amp;action=manage_notification">Add notification</a>.';
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
$l['bankpipe_logs_header_user'] = $l['bankpipe_history_header_user'] = 'User';
$l['bankpipe_logs_header_action'] = 'Action taken';
$l['bankpipe_logs_header_item'] = 'Purchase handled';
$l['bankpipe_logs_header_date'] = $l['bankpipe_history_header_date'] = 'Date';
$l['bankpipe_logs_header_delete'] = 'Delete';
$l['bankpipe_logs_no_logs'] = 'There are currently no payment logs to display.';
$l['bankpipe_logs_error'] = 'Error';
$l['bankpipe_logs_created'] = 'Payment created';
$l['bankpipe_logs_refunded'] = 'Payment refunded';
$l['bankpipe_logs_executed'] = 'Payment executed';
$l['bankpipe_logs_pending'] = 'Pending payment';
$l['bankpipe_logs_delete'] = 'Delete selected log(s)';

// History
$l['bankpipe_history'] = 'Payment history';
$l['bankpipe_history_desc'] = 'View a complete transactions log history and manage them singularly, including editing, revoking and refunding.';
$l['bankpipe_history_header_item'] = 'Item purchased';
$l['bankpipe_history_header_expires'] = 'Expires';
$l['bankpipe_history_expires_never'] = 'Never';
$l['bankpipe_history_expires_expired'] = 'Expired';
$l['bankpipe_history_inactive'] = ' – Inactive';
$l['bankpipe_history_refunded'] = ' – Refunded';
$l['bankpipe_history_header_options'] = 'Options';
$l['bankpipe_history_no_payments'] = 'There is currently no transaction history to display.';
$l['bankpipe_history_edit'] = 'Edit';
$l['bankpipe_history_refund'] = 'Refund';
$l['bankpipe_history_revoke'] = 'Revoke';

// Manual add
$l['bankpipe_manual_add'] = 'Subscribe users';
$l['bankpipe_manual_add_desc'] = 'This tool lets you manually add an user to a subscription. Refund options will be available only if you specify a valid PayPal transaction ID.';
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
$l['bankpipe_manual_add_sale_id_desc'] = '(Optional) Add a valid PayPal sale ID. When provided, this subscription will be refundable in the appropriate section. This will not work when multiple users are selected.';

// Manage purchase
$l['bankpipe_manage_purchase'] = 'Manage purchase';
$l['bankpipe_manage_purchase_desc'] = 'Edit or refund a purchase.';
$l['bankpipe_edit_purchase_name'] = 'Item bought';
$l['bankpipe_edit_purchase_bought_by'] = 'Bought by';
$l['bankpipe_edit_purchase_sale_id'] = 'Sale identifier';
$l['bankpipe_edit_purchase_refunded'] = 'Purchase refunded';
$l['bankpipe_edit_purchase_refunded_on'] = 'This purchase has been refunded on: {1}.';
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

// Messages
$l['bankpipe_success_subscription_added'] = 'Subscription created successfully.';
$l['bankpipe_success_subscription_edited'] = 'Subscription edited successfully.';
$l['bankpipe_success_notification_added'] = 'Notification created successfully.';
$l['bankpipe_success_notification_edited'] = 'Notification edited successfully.';
$l['bankpipe_success_users_added'] = 'The user(s) you have specified have been subscribed successfully to the selected subscription plan.';
$l['bankpipe_success_deleted_selected_logs'] = 'The log(s) you have selected have been deleted successfully.';
$l['bankpipe_success_purchase_edited'] = 'The purchase has been edited successfully.';
$l['bankpipe_success_purchase_refunded'] = 'The purchase has been refunded successfully. {1} have been sent to the user. This transaction costed {2} to the payee in PayPal refund fees.';
$l['bankpipe_success_purchase_revoked'] = 'The purchase has been revoked successfully and the user has been reverted to the usergroup he was in before.';

$l['bankpipe_error_missing_default_payee'] = 'The default payee is missing. Please specify one before attempting to create a subscription.';
$l['bankpipe_error_price_not_valid'] = 'The price you entered does not look to be a valid price. Please enter a positive number, either integer or with decimals separated by a dot.';
$l['bankpipe_error_invalid_item'] = 'This subscription appears to be invalid.';
$l['bankpipe_error_invalid_purchase'] = 'This purchase appears to be invalid.';
$l['bankpipe_error_invalid_notification'] = 'This notification appears to be invalid.';
$l['bankpipe_error_missing_subscriptions'] = 'Before attempting to add an user to a subscription plan, please create a subscription plan.';
$l['bankpipe_error_incorrect_dates'] = 'The dates you selected are not valid. Please enter a past date for the starting one and a future date for the ending one.';

// Tasks
$l['task_bankpipe_ran'] = 'BankPipe cleanup task has ran successfully.';

// Permissions
$l['viewing_field_candownloadpaidattachments'] = $l['bankpipe_can_dl_paid_attachs'] = 'Can download paid attachments without purchasing?';

// Misc
$l['bankpipe_options'] = 'Options';
$l['bankpipe_new_subscription'] = '<a href="index.php?module=config-bankpipe&amp;action=manage_subscription" style="float: right">Add subscription</a>';
$l['bankpipe_new_notification'] = '<a href="index.php?module=config-bankpipe&amp;action=manage_notification" style="float: right">Add notification</a>';