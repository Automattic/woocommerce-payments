/**
 * External dependencies
 */
import React, { useEffect, useRef, useState } from 'react';
import { Button, Notice, TextareaControl } from '@wordpress/components';
import { closeSmall } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import './style.scss';
import { ThumbsControl } from './thumbs';
import { useReportFeedbackState, useSubmitReportFeedback } from './hooks';
import {
	cancelLabel,
	closeAriaLabel,
	feedbackQuestion,
	privacyDisclaimer,
	sendLabel,
	submitErrorMessage,
	thumbsDownLabel,
	thumbsUpLabel,
} from './strings';
import {
	recordReportFeedbackCancel,
	recordReportFeedbackDismiss,
	recordReportFeedbackSubmit,
	recordReportFeedbackSubmitError,
	recordReportFeedbackThumbsDown,
	recordReportFeedbackThumbsUp,
	recordReportFeedbackView,
	type ReportFeedbackRating,
} from './tracks';

const isReportsAreaEnabled = () =>
	typeof wcpaySettings !== 'undefined' &&
	Boolean( wcpaySettings?.featureFlags?.reportsArea );

const ReportFeedbackSurveyContent = () => {
	const { dismiss, isDismissed } = useReportFeedbackState();
	const { isSubmitting, submitFeedback } = useSubmitReportFeedback();
	const [ rating, setRating ] = useState< ReportFeedbackRating | null >(
		null
	);
	const [ comments, setComments ] = useState( '' );
	const [ isHidden, setIsHidden ] = useState( false );
	const [ hasSubmitError, setHasSubmitError ] = useState( false );
	const hasRecordedView = useRef( false );

	const isVisible = ! isHidden && ! isDismissed;
	const isExpanded = rating !== null;

	useEffect( () => {
		if ( ! isVisible || hasRecordedView.current ) {
			return;
		}

		recordReportFeedbackView();
		hasRecordedView.current = true;
	}, [ isVisible ] );

	if ( ! isVisible ) {
		return null;
	}

	const handleRatingSelect = ( selectedRating: ReportFeedbackRating ) => {
		setRating( selectedRating );
		setHasSubmitError( false );

		if ( selectedRating === 'thumbs-up' ) {
			recordReportFeedbackThumbsUp();
		} else {
			recordReportFeedbackThumbsDown();
		}
	};

	const handleCancel = () => {
		recordReportFeedbackCancel();
		setRating( null );
		setComments( '' );
		setHasSubmitError( false );
	};

	const handleDismiss = () => {
		recordReportFeedbackDismiss();
		dismiss();
		setIsHidden( true );
	};

	const handleSubmit = async () => {
		if ( ! rating ) {
			return;
		}

		setHasSubmitError( false );
		const trimmedComments = comments.trim();
		const hasText = trimmedComments.length > 0;
		recordReportFeedbackSubmit( rating, hasText );

		try {
			await submitFeedback( { rating, comments: trimmedComments } );
			await dismiss();
			setIsHidden( true );
		} catch {
			recordReportFeedbackSubmitError( rating, hasText );
			setHasSubmitError( true );
		}
	};

	return (
		<div className="wcpay-reports-feedback-survey">
			<div className="wcpay-reports-feedback-survey__header">
				<p className="wcpay-reports-feedback-survey__question">
					{ feedbackQuestion }
				</p>
				<ThumbsControl
					disabled={ isSubmitting }
					onSelect={ handleRatingSelect }
					selectedRating={ rating }
				/>
				<Button
					className="wcpay-reports-feedback-survey__close"
					disabled={ isSubmitting }
					icon={ closeSmall }
					label={ closeAriaLabel }
					onClick={ handleDismiss }
				/>
			</div>

			{ isExpanded && (
				<>
					<div
						className="wcpay-reports-feedback-survey__divider"
						aria-hidden="true"
					/>
					<div className="wcpay-reports-feedback-survey__body">
						<TextareaControl
							__nextHasNoMarginBottom
							label={
								rating === 'thumbs-up'
									? thumbsUpLabel
									: thumbsDownLabel
							}
							onChange={ setComments }
							readOnly={ isSubmitting }
							value={ comments }
						/>
						<p className="wcpay-reports-feedback-survey__disclaimer">
							{ privacyDisclaimer }
						</p>
						{ hasSubmitError && (
							<Notice
								status="error"
								isDismissible={ false }
								className="wcpay-reports-feedback-survey__error"
							>
								{ submitErrorMessage }
							</Notice>
						) }
					</div>
					<div className="wcpay-reports-feedback-survey__footer">
						<Button
							disabled={ isSubmitting }
							onClick={ handleCancel }
							variant="tertiary"
						>
							{ cancelLabel }
						</Button>
						<Button
							disabled={ isSubmitting }
							isBusy={ isSubmitting }
							onClick={ handleSubmit }
							variant="primary"
						>
							{ sendLabel }
						</Button>
					</div>
				</>
			) }
		</div>
	);
};

const ReportFeedbackSurvey = () => {
	if ( ! isReportsAreaEnabled() ) {
		return null;
	}

	return <ReportFeedbackSurveyContent />;
};

export default ReportFeedbackSurvey;
