/**
 * External dependencies
 */
import React, { useContext, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import { Button, RadioControl, TextareaControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import ConfirmationModal from 'components/confirmation-modal';
import { wcPaySurveys } from './questions';
import WcPaySurveyContext from './context';
import { LoadableBlock } from '../../components/loadable';

const SurveyModalBody = ( { options, surveyQuestion } ) => {
	const { surveyAnswers, setSurveyAnswers, isLoadingSsr } = useContext(
		WcPaySurveyContext
	);

	return (
		<>
			<RadioControl
				className="survey-radiocontrols"
				label={ __(
					'Do you have any feedback for the payments experience?',
					'woocommerce-payments'
				) }
				options={ options }
				onChange={ ( value ) => {
					setSurveyAnswers( ( prev ) => ( {
						...prev,
						[ surveyQuestion ]: value,
					} ) );
				} }
				selected={
					// This checks the first option by default to signify it's required.
					surveyAnswers[ surveyQuestion ] || options[ 0 ].value
				}
			/>
			<TextareaControl
				className="comments-text-field"
				label={ __(
					'Please provide additional details to help us improve the experience',
					'woocommerce-payments'
				) }
				onChange={ ( text ) => {
					setSurveyAnswers( ( prev ) => ( {
						...prev,
						comments: text,
					} ) );
				} }
				value={ surveyAnswers.comments }
			/>
			<p className="ssr-label">
				{ __(
					'The following system status information will also be submitted:',
					'woocommerce-payments'
				) }
			</p>
			{ isLoadingSsr ? (
				<LoadableBlock isLoading={ true } numLines={ 5 } />
			) : (
				<TextareaControl
					className="ssr-text-field"
					onChange={ ( value ) => {
						setSurveyAnswers( ( prev ) => ( {
							...prev,
							ssr: value,
						} ) );
					} }
					value={ surveyAnswers.ssr }
				/>
			) }

			<p className="survey-bottom-disclaimer">
				{ __(
					'Feedback will be sent anonymously to the WooCommerce Payments development team.',
					'woocommerce-payments'
				) }
			</p>
		</>
	);
};

const submissionErrorNotice = () => {
	return dispatch( 'core/notices' ).createErrorNotice(
		__( 'There was an error during submission.', 'woocommerce-payments' )
	);
};

const surveySubmittedConfirmation = () => {
	return dispatch( 'core/notices' ).createSuccessNotice(
		__(
			'Thank you! Your feedback will help improve your experience in the future.',
			'woocommerce-payments'
		)
	);
};

const surveyCannotBeLoadedNotice = () => {
	return dispatch( 'core/notices' ).createErrorNotice(
		__( 'The survey could not be loaded.', 'woocommerce-payments' )
	);
};

const getOptionsArrayFromQuestions = ( surveyKey, surveyQuestion ) => {
	const questions = wcPaySurveys.find(
		( survey ) => ( survey.key = surveyKey )
	).questions[ surveyQuestion ];
	return Object.keys( questions ).map( ( key ) => {
		return {
			label: questions[ key ],
			value: key,
		};
	} );
};

const SurveyModal = ( { setOpenModal, surveyKey, surveyQuestion } ) => {
	const { isSurveySubmitted, status, submitSurvey } = useContext(
		WcPaySurveyContext
	);
	// Get the questions using key and question pair.
	const optionsArray = getOptionsArrayFromQuestions(
		surveyKey,
		surveyQuestion
	);

	useEffect( () => {
		if ( ! surveyKey || ! surveyQuestion ) {
			surveyCannotBeLoadedNotice();
		} else if ( 'error' === status ) {
			submissionErrorNotice();
		} else if ( isSurveySubmitted ) {
			surveySubmittedConfirmation();
			setOpenModal( '' );
		}
	}, [ status, isSurveySubmitted, surveyKey, surveyQuestion, setOpenModal ] );

	if ( 1 > optionsArray ) return null;

	return (
		<>
			<ConfirmationModal
				className="survey-section"
				title={ __(
					'Provide feedback about the new payments experience.',
					'woocommerce-payments'
				) }
				onRequestClose={ () => setOpenModal( '' ) }
				actions={
					<>
						<Button
							isSecondary
							disabled={ 'pending' === status }
							onClick={ () => setOpenModal( '' ) }
						>
							{ __( 'Cancel', 'woocommerce-payments' ) }
						</Button>
						<Button
							isPrimary
							isBusy={ 'pending' === status }
							disabled={ 'pending' === status }
							onClick={ () => submitSurvey() }
						>
							{ __( 'Send feedback', 'woocommerce-payments' ) }
						</Button>
					</>
				}
			>
				<SurveyModalBody
					options={ optionsArray }
					surveyQuestion={ surveyQuestion }
				/>
			</ConfirmationModal>
		</>
	);
};
export default SurveyModal;
