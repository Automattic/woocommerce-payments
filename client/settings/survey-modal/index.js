import React, { useContext, useEffect } from 'react';

import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import {
	Button,
	Icon,
	RadioControl,
	TextareaControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import ConfirmationModal from 'components/confirmation-modal';
import useIsUpeEnabled from 'settings/wcpay-upe-toggle/hook';
import { wcPaySurveys } from './questions';
import WcPaySurveyContext from './context';
import { useSurveySubmit, useSurveyAnswers } from './hook';

const DisabledUPESuccessNotice = () => {
	return (
		<div className="disable-success-notice">
			<Icon className="disable-success-icon" icon="yes-alt" />
			<p>
				{ __(
					"You've disabled the new payments experience in your store.",
					'woocommerce-payments'
				) }
			</p>
		</div>
	);
};

const SurveyPromptQuestionDisabledUPE = () => (
	<p>
		<strong>
			{ __(
				'What made you disable the new payments experience?',
				'woocommerce-payments'
			) }
		</strong>
	</p>
);

const SurveyPromptQuestion = () => (
	<p>
		<strong>
			{ __(
				'Do you have any feedback for the new payments experience?',
				'woocommerce-payments'
			) }
		</strong>
	</p>
);

const SurveyModalBody = ( { optionsArray, surveyQuestion } ) => {
	console.log( 'optionsArray: ', optionsArray );
	const [ isUpeEnabled ] = useIsUpeEnabled();
	const [ surveyAnswers, setSurveyAnswers ] = useSurveyAnswers( {
		surveyQuestion: '',
		comments: '',
	} );
	return (
		<>
			{ ! isUpeEnabled ? (
				<>
					<DisabledUPESuccessNotice />
					<SurveyPromptQuestionDisabledUPE />
				</>
			) : (
				<SurveyPromptQuestion />
			) }
			<RadioControl
				options={ optionsArray }
				onChange={ ( value ) =>
					setSurveyAnswers( {
						...surveyAnswers,
						[ surveyQuestion ]: value,
					} )
				}
				selected={
					// This checks the first option by default to signify it's required.
					surveyAnswers[ surveyQuestion ] || optionsArray[ 0 ].value
				}
			/>
			<TextareaControl
				className="comments-text-field"
				label={ __( 'Comments(optional)', 'woocommerce-payments' ) }
				onChange={ ( text ) =>
					setSurveyAnswers( { ...surveyAnswers, comments: text } )
				}
				value={ surveyAnswers.comments }
			/>
			<p className="survey-bottom-disclaimer">
				{ __(
					'Feedback will be sent anonymously to the WooCommerce Payments development team.',
					'woocommerce-payments'
				) }{ ' ' }
			</p>
		</>
	);
};

const SurveySubmitButton = () => {
	const [ , setSurveySubmitted ] = useSurveySubmit();
	const [ surveyAnswers ] = useSurveyAnswers();
	const { status } = useContext( WcPaySurveyContext );
	return (
		<Button
			isBusy={ 'pending' === status }
			disabled={ 'pending' === status }
			isPrimary
			onClick={ () => setSurveySubmitted( surveyAnswers ) }
		>
			{ __( 'Send Feedback', 'woocommerce-payments' ) }
		</Button>
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
			'Thank you for submitting feedback to our developer team!',
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

const SurveyModal = ( { setIsModalOpen, surveyOptions } ) => {
	const { status } = useContext( WcPaySurveyContext );
	const [ isSurveySubmitted ] = useSurveySubmit();
	// Get the questions using key and question pair.
	const { surveyKey, surveyQuestion } = surveyOptions;
	const optionsArray = getOptionsArrayFromQuestions(
		surveyKey,
		surveyQuestion
	);

	useEffect( () => {
		if ( ! surveyKey || ! surveyQuestion ) surveyCannotBeLoadedNotice();
		if ( 'error' === status ) submissionErrorNotice();
		else if ( isSurveySubmitted ) {
			surveySubmittedConfirmation();
			setIsModalOpen( false );
		}
	}, [
		status,
		isSurveySubmitted,
		surveyKey,
		surveyQuestion,
		setIsModalOpen,
	] );

	if ( 1 > optionsArray ) return null;

	return (
		<>
			<ConfirmationModal
				className="survey-section"
				title={ __(
					'Provide feedback about the new payments experience.',
					'woocommerce-payments'
				) }
				onRequestClose={ () => setIsModalOpen( false ) }
				actions={ <SurveySubmitButton /> }
			>
				<SurveyModalBody
					optionsArray={ optionsArray }
					surveyQuestion={ surveyQuestion }
				/>
			</ConfirmationModal>
		</>
	);
};
export default SurveyModal;
