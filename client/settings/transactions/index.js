/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Card,
	CheckboxControl,
	Notice,
	TextControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import {
	useAccountStatementDescriptor,
	useGetSavingError,
	useSavedCards,
} from '../../data';
import './style.scss';
import ManualCaptureControl from 'wcpay/settings/transactions/manual-capture-control';
import SupportPhoneInput from 'wcpay/settings/support-phone-input';
import SupportEmailInput from 'wcpay/settings/support-email-input';
import React, { useEffect, useState } from 'react';

const ACCOUNT_STATEMENT_MAX_LENGTH = 22;

const Transactions = ( { setTransactionInputsValid } ) => {
	const [ isSavedCardsEnabled, setIsSavedCardsEnabled ] = useSavedCards();
	const [
		accountStatementDescriptor,
		setAccountStatementDescriptor,
	] = useAccountStatementDescriptor();
	const customerBankStatementErrorMessage = useGetSavingError()?.data?.details
		?.account_statement_descriptor?.message;

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
				<h4>{ __( 'Customer support', 'woocommerce-payments' ) }</h4>

				<div className="transactions__customer-support">
					{ customerBankStatementErrorMessage && (
						<Notice status="error" isDismissible={ false }>
							<span
								dangerouslySetInnerHTML={ {
									__html: customerBankStatementErrorMessage,
								} }
							/>
						</Notice>
					) }
					<TextControl
						className="transactions__account-statement-input"
						help={ __(
							'Enter the name your customers will see on their transactions. Use a recognizable name – e.g. ' +
								'the legal entity name or website address – to avoid potential disputes and chargebacks.',
							'woocommerce-payments'
						) }
						label={ __(
							'Full bank statement',
							'woocommerce-payments'
						) }
						value={ accountStatementDescriptor }
						onChange={ setAccountStatementDescriptor }
						maxLength={ ACCOUNT_STATEMENT_MAX_LENGTH }
						data-testid={ 'store-name-bank-statement' }
					/>
					<span className="input-help-text" aria-hidden="true">
						{ `${ accountStatementDescriptor.length } / ${ ACCOUNT_STATEMENT_MAX_LENGTH }` }
					</span>

					<SupportEmailInput setInputVallid={ setEmailInputValid } />
					<SupportPhoneInput setInputVallid={ setPhoneInputValid } />
				</div>
			</CardBody>
		</Card>
	);
};

export default Transactions;
