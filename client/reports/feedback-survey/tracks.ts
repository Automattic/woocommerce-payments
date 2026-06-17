/**
 * External dependencies
 */
import { recordEvent } from 'tracks';

export type ReportFeedbackRating = 'thumbs-up' | 'thumbs-down';

const reportFeedbackReportType = 'balance';

const getSentiment = ( rating: ReportFeedbackRating ): 'up' | 'down' =>
	rating === 'thumbs-up' ? 'up' : 'down';

export const recordReportFeedbackView = () => {
	recordEvent( 'wcpay_reports_feedback_view', {
		report_type: reportFeedbackReportType,
	} );
};

export const recordReportFeedbackThumbsUp = () => {
	recordEvent( 'wcpay_reports_feedback_thumbs_up', {
		report_type: reportFeedbackReportType,
	} );
};

export const recordReportFeedbackThumbsDown = () => {
	recordEvent( 'wcpay_reports_feedback_thumbs_down', {
		report_type: reportFeedbackReportType,
	} );
};

export const recordReportFeedbackSubmit = (
	rating: ReportFeedbackRating,
	hasText: boolean
) => {
	recordEvent( 'wcpay_reports_feedback_submit', {
		report_type: reportFeedbackReportType,
		sentiment: getSentiment( rating ),
		has_text: hasText,
	} );
};

export const recordReportFeedbackSubmitError = (
	rating: ReportFeedbackRating,
	hasText: boolean
) => {
	recordEvent( 'wcpay_reports_feedback_submit_error', {
		report_type: reportFeedbackReportType,
		sentiment: getSentiment( rating ),
		has_text: hasText,
	} );
};

export const recordReportFeedbackCancel = () => {
	recordEvent( 'wcpay_reports_feedback_cancel', {
		report_type: reportFeedbackReportType,
	} );
};

export const recordReportFeedbackDismiss = () => {
	recordEvent( 'wcpay_reports_feedback_dismiss', {
		report_type: reportFeedbackReportType,
	} );
};
