/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { useSavedCards } from '../../data';
import './style.scss';
import ManualCaptureControl from 'wcpay/settings/transactions/manual-capture-control';
import SupportPhoneInput from 'wcpay/settings/support-phone-input';
import SupportEmailInput from 'wcpay/settings/support-email-input';
import React, { useEffect, useState } from 'react';

const Transactions = ( { setTransactionInputsValid } ) => {
	const [ isSavedCardsEnabled, setIsSavedCardsEnabled ] = useSavedCards();
	const [ isEmailInputValid, setEmailInputValid ] = useState( true );
	const [ isPhoneInputValid, setPhoneInputValid ] = useState( true );

	useEffect( () => {
		if ( setTransactionInputsValid ) {
			setTransactionInputsValid( isEmailInputValid && isPhoneInputValid );
		}
	}, [ isEmailInputValid, isPhoneInputValid, setTransactionInputsValid ] );

	return (
		<Card className="transactions">
			<CardBody>
				<h4>
					{ __( 'Transaction preferences', 'woocommerce-payments' ) }
				</h4>
				<CheckboxControl
					checked={ isSavedCardsEnabled }
					onChange={ setIsSavedCardsEnabled }
					label={ __(
						'Enable payments via saved cards',
						'woocommerce-payments'
					) }
					help={ __(
						'When enabled, users will be able to pay with a saved card during checkout. ' +
							'Card details are stored in our platform, not on your store.',
						'woocommerce-payments'
					) }
				/>
				<ManualCaptureControl></ManualCaptureControl>
				<h4>{ __( 'Customer statements', 'woocommerce-payments' ) }</h4>
				<p className="transactions-customer-details">
					{ __(
						"Edit the way your store name appears on your customers' bank statements.",
						'woocommerce-payments'
					) }
				</p>

				<h4>{ __( 'Customer support', 'woocommerce-payments' ) }</h4>

				<p className="transactions-customer-details">
					{ __(
						'Provide contact information where customers can reach you for support.',
						'woocommerce-payments'
					) }
				</p>
				<div className="transactions__customer-support">
					<SupportEmailInput setInputVallid={ setEmailInputValid } />
					<SupportPhoneInput setInputVallid={ setPhoneInputValid } />
				</div>
			</CardBody>
		</Card>
	);
};

export default Transactions;
