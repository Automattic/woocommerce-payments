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
	useAccountStatementDescriptorKanji,
	useAccountStatementDescriptorKana,
	useGetSavingError,
	useSavedCards,
} from '../../data';
import './style.scss';
import ManualCaptureControl from 'wcpay/settings/transactions/manual-capture-control';
import SupportPhoneInput from 'wcpay/settings/support-phone-input';
import SupportEmailInput from 'wcpay/settings/support-email-input';
import React, { useEffect, useState } from 'react';
import { select } from '@wordpress/data';
import { STORE_NAME } from 'wcpay/data/constants';

const ACCOUNT_STATEMENT_MAX_LENGTH = 22;
const ACCOUNT_STATEMENT_MAX_LENGTH_KANJI = 17;
const ACCOUNT_STATEMENT_MAX_LENGTH_KANA = 22;

const Transactions = ( { setTransactionInputsValid } ) => {
	const [ isSavedCardsEnabled, setIsSavedCardsEnabled ] = useSavedCards();
	const [
		accountStatementDescriptor,
		setAccountStatementDescriptor,
	] = useAccountStatementDescriptor();
	const [
		accountStatementDescriptorKanji,
		setAccountStatementDescriptorKanji,
	] = useAccountStatementDescriptorKanji();
	const [
		accountStatementDescriptorKana,
		setAccountStatementDescriptorKana,
	] = useAccountStatementDescriptorKana();
	const customerBankStatementErrorMessage = useGetSavingError()?.data?.details
		?.account_statement_descriptor?.message;

	const [ isEmailInputValid, setEmailInputValid ] = useState( true );
	const [ isPhoneInputValid, setPhoneInputValid ] = useState( true );
	const settings = select( STORE_NAME ).getSettings();

	useEffect( () => {
		if ( setTransactionInputsValid ) {
			setTransactionInputsValid( isEmailInputValid && isPhoneInputValid );
		}
	}, [ isEmailInputValid, isPhoneInputValid, setTransactionInputsValid ] );

	let labelCustomerStatement = __( 'Customer bank statement' );
	if ( settings.account_country === 'JP' ) {
		labelCustomerStatement = __( 'Customer bank statement (Latin)' );
	}

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
							"Edit the way your store name appears on your customers' bank statements.",
							'woocommerce-payments'
						) }
						label={ labelCustomerStatement }
						value={ accountStatementDescriptor }
						onChange={ setAccountStatementDescriptor }
						maxLength={ ACCOUNT_STATEMENT_MAX_LENGTH }
						data-testid={ 'store-name-bank-statement' }
					/>
					<span className="input-help-text" aria-hidden="true">
						{ `${ accountStatementDescriptor.length } / ${ ACCOUNT_STATEMENT_MAX_LENGTH }` }
					</span>
					{ settings.account_country === 'JP' && (
						<>
							<div className="transactions__customer-support">
								<TextControl
									className="transactions__account-statement-input"
									help={ __(
										"Edit the way your store name appears on your customers' bank statements.",
										'woocommerce-payments'
									) }
									label={ __(
										'Customer bank statement (Kanji)',
										'woocommerce-payments'
									) }
									value={ accountStatementDescriptorKanji }
									onChange={
										setAccountStatementDescriptorKanji
									}
									maxLength={
										ACCOUNT_STATEMENT_MAX_LENGTH_KANJI
									}
									data-testid={ 'store-name-bank-statement' }
								/>
								<span
									className="input-help-text"
									aria-hidden="true"
								>
									{ `${ accountStatementDescriptorKanji.length } / ${ ACCOUNT_STATEMENT_MAX_LENGTH_KANJI }` }
								</span>
							</div>
							<div className="transactions__customer-support">
								<TextControl
									className="transactions__account-statement-input"
									help={ __(
										"Edit the way your store name appears on your customers' bank statements.",
										'woocommerce-payments'
									) }
									label={ __(
										'Customer bank statement (Kana)',
										'woocommerce-payments'
									) }
									value={ accountStatementDescriptorKana }
									onChange={
										setAccountStatementDescriptorKana
									}
									maxLength={
										ACCOUNT_STATEMENT_MAX_LENGTH_KANA
									}
									data-testid={ 'store-name-bank-statement' }
								/>
								<span
									className="input-help-text"
									aria-hidden="true"
								>
									{ `${ accountStatementDescriptorKana.length } / ${ ACCOUNT_STATEMENT_MAX_LENGTH_KANA }` }
								</span>
							</div>
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
