/**
 * External dependencies
 */
import { useUserPreferences } from '@woocommerce/data';

/**
 * Extend the user preferences returned from useUserPreferences to include the WooPayments merchant feedback prompt dismissed state.
 * See WC_Payments::add_user_data_fields() in includes/class-wc-payments.php for the PHP implementation.
 */
interface UserPreferences extends ReturnType< typeof useUserPreferences > {
	/** The unix timestamp of the dismissal of the merchant feedback prompt. */
	wc_payments_wporg_review_2025_prompt_dismissed?: number;
}

/**
 * A hook for managing the merchant feedback prompt visibility state.
 * It returns the current visibility state and a function to update the state.
 */
export const useMerchantFeedbackPromptState = () => {
	const {
		updateUserPreferences,
		...userPrefs
	} = useUserPreferences() as UserPreferences;

	const isAccountEligible =
		wcpaySettings?.accountStatus?.campaigns?.wporgReview2025;

	const hasUserDismissedPrompt =
		userPrefs?.wc_payments_wporg_review_2025_prompt_dismissed;

	const dismissPrompt = () => {
		// Stored as a unix timestamp in case we want to let this expire in the future.
		const unixTimestamp = Date.now();
		updateUserPreferences( {
			wc_payments_wporg_review_2025_prompt_dismissed: unixTimestamp,
		} );
	};

	return {
		/** Whether the account is eligible to be presented with the merchant feedback prompt. */
		isAccountEligible,
		/** Whether the user has dismissed the merchant feedback prompt. */
		hasUserDismissedPrompt,
		/** A function to dismiss the merchant feedback prompt. Stores the current timestamp of dismissal in user preferences. */
		dismissPrompt,
	};
};
