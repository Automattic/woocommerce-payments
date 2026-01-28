/**
 * External dependencies
 */
import { useUserPreferences } from '@woocommerce/data';

interface UserPreferences extends ReturnType< typeof useUserPreferences > {
	wc_payments_review_prompt_dismissed?: number;
	wc_payments_review_prompt_maybe_later?: number;
}

export const useReviewPromptState = () => {
	const { updateUserPreferences } = useUserPreferences() as UserPreferences;

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
		dismissPrompt,
		setMaybeLater,
	};
};
