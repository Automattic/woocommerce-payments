import React, { useContext } from 'react';

import { __ } from '@wordpress/i18n';
import {
	Button,
	Icon,
	Notice,
	RadioControl,
	TextareaControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import ConfirmationModal from 'components/confirmation-modal';
import useIsUpeEnabled from 'settings/wcpay-upe-toggle/hook';
import { wcpayDisableEarlyAccessSurvey } from './questions';
import WcPaySurveyContext from './context';
import { useSurveySubmit, useSurveyAnswers } from './hook';

// create survey modal page with form elements
// button submits survey(POST request)
// add success notification for submission, auto-close?
// write tests

// @todo: get rid of hard-coding for re-use.
const questionsKey = 'why-disable';
const questions = wcpayDisableEarlyAccessSurvey.questions[ questionsKey ];
const optionsArray = Object.keys( questions ).map( ( key ) => {
	return {
		label: questions[ key ],
		value: key,
	};
} );

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

const SurveyModalBody = () => {
	const [ isUpeEnabled ] = useIsUpeEnabled();
	const [ surveyAnswers, setSurveyAnswers ] = useSurveyAnswers();
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
						[ questionsKey ]: value,
					} )
				}
				// @todo - can we abstract this key out and get rid of hard-coding?
				selected={ surveyAnswers[ questionsKey ] }
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
	// @todo - can we get rid of this hard-coding for re-use?
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

const SubmissionErrorNotice = () => {
	return 'error' === status ? (
		<Notice>
			{ __(
				'There was an error during submission.',
				'woocommerce-payments'
			) }
		</Notice>
	) : null;
};

const SurveySuccessMessage = () => {
	return (
		<div className="disable-success-notice">
			<Icon className="disable-success-icon" icon="yes-alt" />
			<p>
				{ __(
					'Thank you for submitting feedback to our developer team!',
					'woocommerce-payments'
				) }
			</p>
		</div>
	);
};

const SurveySubmittedConfirmation = ( { setIsModalOpen } ) => {
	return (
		<ConfirmationModal
			className="survey-section"
			title={ __( 'Feedback submitted.', 'woocommerce-payments' ) }
			onRequestClose={ () => setIsModalOpen( false ) }
			actions={
				<Button
					variant="link"
					onClick={ () => setIsModalOpen( false ) }
				>
					{ __( 'Close', 'woocommerce-payments' ) }{ ' ' }
				</Button>
			}
		>
			<SurveySuccessMessage />
		</ConfirmationModal>
	);
};

const SurveyModal = ( { setIsModalOpen } ) => {
	const { status } = useContext( WcPaySurveyContext );
	const [ isSurveySubmitted ] = useSurveySubmit();

	return (
		<>
			{ 'error' === status && <SubmissionErrorNotice /> }
			{ isSurveySubmitted ? (
				<SurveySubmittedConfirmation
					setIsModalOpen={ setIsModalOpen }
				/>
			) : (
				<ConfirmationModal
					className="survey-section"
					title={ __(
						'Provide feedback about the new payments experience.',
						'woocommerce-payments'
					) }
					onRequestClose={ () => setIsModalOpen( false ) }
					actions={ <SurveySubmitButton /> }
				>
					<SurveyModalBody />
				</ConfirmationModal>
			) }
		</>
	);
};
export default SurveyModal;
