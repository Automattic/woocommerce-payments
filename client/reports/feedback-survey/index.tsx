/**
 * External dependencies
 */
import React, { useEffect, useId, useRef, useState } from 'react';
import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
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
	submitSuccessMessage,
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

interface ReportFeedbackSurveyProps {
	focusAfterCloseRef?: React.RefObject< HTMLElement >;
}

const ReportFeedbackSurveyContent = ( {
	focusAfterCloseRef,
}: ReportFeedbackSurveyProps ) => {
	const { dismiss, isDismissed } = useReportFeedbackState();
	const { isSubmitting, submitFeedback } = useSubmitReportFeedback();
	const { createSuccessNotice } = useDispatch( 'core/notices' );
	const expandedRegionId = useId();
	const containerRef = useRef< HTMLDivElement >( null );
	const expandedRegionRef = useRef< HTMLDivElement >( null );
	const hasFocusedExpandedForm = useRef( false );
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

	useEffect( () => {
		if ( ! isExpanded ) {
			hasFocusedExpandedForm.current = false;
			return;
		}
		if ( hasFocusedExpandedForm.current ) {
			return;
		}

		expandedRegionRef.current
			?.querySelector< HTMLTextAreaElement >( 'textarea' )
			?.focus();
		hasFocusedExpandedForm.current = true;
	}, [ isExpanded ] );

	if ( ! isVisible ) {
		return null;
	}

	const focusAfterClose = () => {
		const activeElement = containerRef.current?.ownerDocument.activeElement;

		if (
			! activeElement ||
			! containerRef.current?.contains( activeElement )
		) {
			return;
		}

		focusAfterCloseRef?.current?.focus( { preventScroll: true } );
	};

	const persistDismissal = () => {
		try {
			void Promise.resolve( dismiss() ).catch( () => undefined );
		} catch {
			// Survey submission/dismissal succeeded locally; a preference write failure should not reopen it.
		}
	};

	const hideSurvey = () => {
		focusAfterClose();
		setIsHidden( true );
	};

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
		persistDismissal();
		hideSurvey();
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
		} catch {
			recordReportFeedbackSubmitError( rating, hasText );
			setHasSubmitError( true );
			return;
		}

		persistDismissal();
		createSuccessNotice( submitSuccessMessage, {
			id: 'wcpay-reports-feedback-submitted',
			type: 'snackbar',
		} );
		hideSurvey();
	};

	return (
		<div className="wcpay-reports-feedback-survey" ref={ containerRef }>
			<div className="wcpay-reports-feedback-survey__header">
				<div className="wcpay-reports-feedback-survey__header-content">
					<p className="wcpay-reports-feedback-survey__question">
						{ feedbackQuestion }
					</p>
					<ThumbsControl
						controlsId={ expandedRegionId }
						disabled={ isSubmitting }
						isExpanded={ isExpanded }
						onSelect={ handleRatingSelect }
						selectedRating={ rating }
					/>
				</div>
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
					<div
						className="wcpay-reports-feedback-survey__body"
						id={ expandedRegionId }
						ref={ expandedRegionRef }
					>
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

const ReportFeedbackSurvey = ( props: ReportFeedbackSurveyProps ) => {
	if ( ! isReportsAreaEnabled() ) {
		return null;
	}

	return <ReportFeedbackSurveyContent { ...props } />;
};

export default ReportFeedbackSurvey;
