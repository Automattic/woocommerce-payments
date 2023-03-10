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
	useIsShortStatementDescriptorEnabled,
	useShortStatementDescriptor,
	useGetSavingError,
	useSavedCards,
} from '../../data';
import TextLengthHelpInputWrapper from './text-lenght-help-input-wrapper';
import './style.scss';
import ManualCaptureControl from 'wcpay/settings/transactions/manual-capture-control';
import SupportPhoneInput from 'wcpay/settings/support-phone-input';
import SupportEmailInput from 'wcpay/settings/support-email-input';
import React, { useEffect, useState } from 'react';

const ACCOUNT_STATEMENT_MAX_LENGTH = 22;
const SHORT_STATEMENT_MAX_LENGTH = 10;

const Transactions = ( { setTransactionInputsValid } ) => {
	const [ isSavedCardsEnabled, setIsSavedCardsEnabled ] = useSavedCards();
	const [
		accountStatementDescriptor,
		setAccountStatementDescriptor,
	] = useAccountStatementDescriptor();
	const [
		isShortStatementEnabled,
		setIsShortStatementEnabled,
	] = useIsShortStatementDescriptorEnabled();
	const [
		shortAccountStatementDescriptor,
		setShortAccountStatementDescriptor,
	] = useShortStatementDescriptor();
	const customerBankStatementErrorMessage = useGetSavingError()?.data?.details
		?.account_statement_descriptor?.message;
	const shortStatementDescriptorErrorMessage = useGetSavingError()?.data
		?.details?.short_statement_descriptor?.message;

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
					<TextLengthHelpInputWrapper
						textLength={ accountStatementDescriptor.length }
						maxLength={ ACCOUNT_STATEMENT_MAX_LENGTH }
						data-testid={ 'store-name-bank-statement' }
					>
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
							data-testid="store-name-bank-statement"
						/>
					</TextLengthHelpInputWrapper>

					<CheckboxControl
						checked={ isShortStatementEnabled }
						onChange={ setIsShortStatementEnabled }
						label={ __(
							'Add customer order number to the bank statement',
							'woocommerce-payments'
						) }
						help={ __(
							"When enabled, we'll include the order number for card and express checkout transactions.",
							'woocommerce-payments'
						) }
					/>

					{ isShortStatementEnabled && (
						<>
							{ shortStatementDescriptorErrorMessage && (
								<Notice status="error" isDismissible={ false }>
									<span
										dangerouslySetInnerHTML={ {
											__html: shortStatementDescriptorErrorMessage,
										} }
									/>
								</Notice>
							) }
							<TextLengthHelpInputWrapper
								textLength={
									shortAccountStatementDescriptor.length
								}
								maxLength={ SHORT_STATEMENT_MAX_LENGTH }
							>
								<TextControl
									help={ __(
										"We'll use the short version in combination with the customer order number.",
										'woocommerce-payments'
									) }
									label={ __(
										'Shortened customer bank statement',
										'woocommerce-payments'
									) }
									value={ shortAccountStatementDescriptor }
									onChange={
										setShortAccountStatementDescriptor
									}
									maxLength={ SHORT_STATEMENT_MAX_LENGTH }
								/>
							</TextLengthHelpInputWrapper>
						</>
					) }

					<SupportEmailInput setInputVallid={ setEmailInputValid } />
					<SupportPhoneInput setInputVallid={ setPhoneInputValid } />
				</div>
			</CardBody>
		</Card>
	);
};

export default Transactions;
