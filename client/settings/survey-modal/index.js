import React, { useContext, useState } from 'react';

import { __ } from '@wordpress/i18n';
import {
	Button,
	Notice,
	RadioControl,
	TextareaControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import ConfirmationModal from 'components/confirmation-modal';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import useIsUpeEnabled from 'settings/wcpay-upe-toggle/hook';
import WcPayUpeContext from 'settings/wcpay-upe-toggle/context';
import { wcpayDisableEarlyAccessSurvey } from './questions';

// create the first modal page
// create state to manage which page you're on
// create functions for each page, pass as children
// create second modal page with form elements
// first(red) button disables UPE and sets page to 2
// second button submits survey
// add state to handle showing modal
// hook up onClick to show modal
// close modal upon successful submission of form(either button click)
// add Payment Methods in bold to header
// write tests

const FirstPageBody = ( { enabledMethods } ) => {
	return (
		<>
			<p>
				{ __(
					// eslint-disable-next-line max-len
					'Without the new payments experience, your customers will no longer be able to pay using the new payment methods listed below.',
					'woocommerce-payments'
				) }
			</p>
			<p>
				{ __(
					'Payment methods that require the new payments experience:',
					'woocommerce-payments'
				) }
			</p>
			<ul>
				{ enabledMethods.map( ( { id, label, Icon } ) => (
					<PaymentMethod key={ id } Icon={ Icon } label={ label } />
				) ) }
			</ul>
		</>
	);
};

// Questions for survey.
const questions = wcpayDisableEarlyAccessSurvey.questions[ 'why-disable' ];
const optionsArray = Object.keys( questions ).map( ( key ) => {
	return {
		label: questions[ key ],
		value: key,
	};
} );

const SecondPageBody = () => {
	const [ surveyAnswer, setAnswer ] = useState( 'missing-features' );
	const [ comments, setComments ] = useState( '' );
	return (
		<>
			<p>
				<strong>
					{ __(
						'What made you disable the new payments experience?',
						'woocommerce-payments'
					) }
				</strong>
			</p>
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

const SurveyModal = ( { enabledMethods } ) => {
	const [ isUpeEnabled, setIsUpeEnabled ] = useIsUpeEnabled();
	const { status } = useContext( WcPayUpeContext );

	return (
		<>
			{ 'error' === status && (
				<Notice>
					{ __(
						'There was an error during submission.',
						'woocommerce-payments'
					) }{ ' ' }
				</Notice>
			) }
			<ConfirmationModal
				className="survey-section"
				title={ __(
					'Disable the new payments experience',
					'woocommerce-payments'
				) }
				isDismissible={ false }
				onRequestClose={ console.log( 'closing modal' ) }
				actions={
					<Button
						isDestructive
						isPrimary
						onClick={ () => setIsUpeEnabled( false ) }
					>
						{ __( 'Disable', 'woocommerce-payments' ) }
					</Button>
				}
			>
				{ isUpeEnabled ? (
					<FirstPageBody enabledMethods={ enabledMethods } />
				) : (
					<SecondPageBody />
				) }
			</ConfirmationModal>
		</>
	);
};
export default SurveyModal;
