/**
 * External dependencies
 */
import { useCallback, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { useUserPreferences } from '@woocommerce/data';

/**
 * Internal dependencies
 */
import { NAMESPACE } from 'wcpay/data/constants';
import type { ReportFeedbackRating } from './tracks';

interface SubmitFeedbackParams {
	comments: string;
	rating: ReportFeedbackRating;
}

interface UserPreferences extends ReturnType< typeof useUserPreferences > {
	wc_payments_reports_feedback_dismissed?: number;
}

export const useSubmitReportFeedback = () => {
	const [ isSubmitting, setIsSubmitting ] = useState( false );

	const submitFeedback = useCallback(
		async ( { rating, comments }: SubmitFeedbackParams ) => {
			setIsSubmitting( true );
			try {
				await apiFetch( {
					path: `${ NAMESPACE }/survey/reports-feedback`,
					method: 'POST',
					data: { rating, comments },
				} );
			} finally {
				setIsSubmitting( false );
			}
		},
		[]
	);

	return { isSubmitting, submitFeedback };
};

export const useReportFeedbackState = () => {
	const {
		updateUserPreferences,
		wc_payments_reports_feedback_dismissed: dismissedAt,
	} = useUserPreferences() as UserPreferences;

	const dismiss = () =>
		updateUserPreferences( {
			wc_payments_reports_feedback_dismissed: Math.floor(
				Date.now() / 1000
			),
		} );

	return {
		dismiss,
		isDismissed: Boolean( dismissedAt ),
	};
};
