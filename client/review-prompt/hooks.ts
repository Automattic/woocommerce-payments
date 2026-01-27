/**
 * External dependencies
 */
import { useUserPreferences } from '@woocommerce/data';

interface UserPreferences extends ReturnType< typeof useUserPreferences > {
	wc_payments_review_prompt_dismissed?: number;
	wc_payments_review_prompt_maybe_later?: number;
}

const cooldownDays = 10;
const cooldownMs = cooldownDays * 24 * 60 * 60 * 1000;

export const useReviewPromptState = () => {
	const {
		updateUserPreferences,
		...userPrefs
	} = useUserPreferences() as UserPreferences;

	const isAccountEligible =
		wcpaySettings?.accountStatus?.campaigns?.wporgReview2025;

	const hasUserDismissedPrompt = !! userPrefs?.wc_payments_review_prompt_dismissed;

	const maybeLaterTimestamp =
		userPrefs?.wc_payments_review_prompt_maybe_later;
	const isCooldownActive = maybeLaterTimestamp
		? Date.now() < maybeLaterTimestamp + cooldownMs
		: false;

	const dismissPrompt = () => {
		updateUserPreferences( {
			wc_payments_review_prompt_dismissed: Date.now(),
		} );
	};

	const setMaybeLater = () => {
		updateUserPreferences( {
			wc_payments_review_prompt_maybe_later: Date.now(),
		} );
	};

	return {
		isAccountEligible,
		hasUserDismissedPrompt,
		isCooldownActive,
		dismissPrompt,
		setMaybeLater,
	};
};
