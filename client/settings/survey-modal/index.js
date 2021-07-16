import React, { useState } from 'react';

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

// create survey modal page with form elements
// button submits survey(POST request)
// add success notification for submission, auto-close?
// write tests

// Questions for survey.
const questions = wcpayDisableEarlyAccessSurvey.questions[ 'why-disable' ];
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
	const [ surveyAnswer, setAnswer ] = useState( 'missing-features' );
	const [ comments, setComments ] = useState( '' );
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
				onChange={ ( value ) => setAnswer( value ) }
				selected={ surveyAnswer }
			/>
			<TextareaControl
				className="comments-text-field"
				label={ __( 'Comments(optional)', 'woocommerce-payments' ) }
				onChange={ ( text ) => setComments( text ) }
				value={ comments }
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
	const { submitStatus } = WcPaySurveyContext;
	return (
		<Button
			isBusy={ 'pending' === submitStatus }
			isPrimary
			onClick={ () => console.log( 'Submitting' ) }
		>
			{ __( 'Send Feedback', 'woocommerce-payments' ) }
		</Button>
	);
};

const SubmissionErrorNotice = () => {
	const { submitStatus } = WcPaySurveyContext;
	return 'error' === submitStatus ? (
		<Notice>
			{ __(
				'There was an error during submission.',
				'woocommerce-payments'
			) }
		</Notice>
	) : null;
};

const SurveyModal = ( { setIsModalOpen } ) => {
	return (
		<>
			<SubmissionErrorNotice />
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
		</>
	);
};
export default SurveyModal;
