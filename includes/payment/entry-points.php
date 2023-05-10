<?php
use WCPay\Payment_Process\Step;

Initial_State // Allow flags to be toggled and data to be set up. => Prepare
Prepared_State // Everything is in place, can be verified => Verify
Verified_State // Either checks whether everything's good, or it has been good already. => Process
Pending_Authentication_State // Performs all completion steps => Complete
Processed_State // Performs all completion steps => Complete
Completed_State|Captured_State // Allows capturing the payment


$steps = [
	Step\Metadata_Step::collect_data(), // Requires order.
	Step\Customer_Details_Step::collect_data(), // Requires order.
	Step\Verify_Fraud_Token_Step::collect_data(), // Requires a payment token to be provided.
];

$verification = [
	Step\Bump_Transaction_Limiter_Step::action(), // All process payment.
	Step\Check_Session_Against_Processing_Order_Step::action(), // Requires order.
	Step\Check_Attached_Intent_Success_Step::action(), // Requires order.
	Step\Complete_Without_Payment_Step::action(),
	Step\Verify_Minimum_Amount_Step::action(),
];

$all = [
	Step\Customer_Details_Step::action(), // Requires order.
];

$strategies = [
	// Step\Load_Intent_After_Authentication_Step::action(), // Post-checkout redirect flow only
	// Step\WooPay_Prepare_Intent_Step::action(),
	Step\Create_UPE_Intent_Step::action(),
	Step\Redirect_UPE_Payment_Step::action(),
	Step\Update_UPE_Intent_Step::action(),
	// Step\Standard_Payment_Step::action(),
	// Step\Setup_Payment_Step::action(),
];

$complete = [
	Step\Bump_Transaction_Limiter_Step::complete(),
	Step\Check_Session_Against_Processing_Order_Step::complete(),
	Step\Update_Saved_Payment_Method_Step::complete(), // When using a saved PM.
	Step\Save_Payment_Method_Step::complete(), // Saves PM
	Step\Store_Metadata_Step::complete(),
	Step\Update_Order_Step::complete(),
	Step\Add_Token_To_Order_Step::complete(), // Saved PM
	Step\Cleanup_Step::complete(),
];
