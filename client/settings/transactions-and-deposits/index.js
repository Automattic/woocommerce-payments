/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CheckboxControl,
	ExternalLink,
	TextControl,
	Notice,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import WCPaySettingsContext from '../wcpay-settings-context';
import CardBody from '../card-body';
import TextLengthHelpInputWrapper from './text-length-help-input-wrapper';
import {
	useAccountStatementDescriptor,
	useIsShortStatementEnabled,
	useShortStatementDescriptor,
	useManualCapture,
	useGetSavingError,
	useSavedCards,
	useCardPresentEligible,
} from '../../data';
import './style.scss';

const ACCOUNT_STATEMENT_MAX_LENGTH = 22;
const SHORT_STATEMENT_MAX_LENGTH = 10;

const TransactionsAndDeposits = () => {
	const {
		accountStatus: { accountLink },
	} = useContext( WCPaySettingsContext );
	const [
		isManualCaptureEnabled,
		setIsManualCaptureEnabled,
	] = useManualCapture();
	const [ isSavedCardsEnabled, setIsSavedCardsEnabled ] = useSavedCards();
	const [
		accountStatementDescriptor,
		setAccountStatementDescriptor,
	] = useAccountStatementDescriptor();
	const [
		isShortStatementEnabled,
		setIsShortStatementEnabled,
	] = useIsShortStatementEnabled();
	const [
		shortAccountStatementDescriptor,
		setShortAccountStatementDescriptor,
	] = useShortStatementDescriptor();
	const customerBankStatementErrorMessage = useGetSavingError()?.data?.details
		?.account_statement_descriptor?.message;
	const [ isCardPresentEligible ] = useCardPresentEligible();

	return (
		<Card className="transactions-and-deposits">
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
				<CheckboxControl
					checked={ isManualCaptureEnabled }
					onChange={ setIsManualCaptureEnabled }
					data-testid={ 'capture-later-checkbox' }
					label={ __(
						'Issue an authorization on checkout, and capture later',
						'woocommerce-payments'
					) }
					help={
						<span>
							{ __(
								'Charge must be captured on the order details screen within 7 days of authorization, ' +
									'otherwise the authorization and order will be canceled.',
								'woocommerce-payments'
							) }
							{ isCardPresentEligible
								? __(
										' The setting is not applied to In-Person Payments ' +
											'(please note that In-Person Payments should be captured within 2 days of authorization).',
										'woocommerce-payments'
								  )
								: '' }
						</span>
					}
				/>
				{ customerBankStatementErrorMessage && (
					<Notice status="error" isDismissible={ false }>
						<span
							dangerouslySetInnerHTML={ {
								__html: customerBankStatementErrorMessage,
							} }
						/>
					</Notice>
				) }
				<div className="transactions-and-deposits__account-statement-wrapper">
					<h4>
						{ __(
							'Customer bank statement',
							'woocommerce-payments'
						) }
					</h4>
					<TextLengthHelpInputWrapper
						textLength={ accountStatementDescriptor.length }
						maxLength={ ACCOUNT_STATEMENT_MAX_LENGTH }
						data-testid={ 'store-name-bank-statement' }
					>
						<TextControl
							className="transactions-and-deposits__account-statement-input"
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
							{ /* { shortStatementDescriptorErrorMessage && (
								<Notice status="error" isDismissible={ false }>
									<span
										dangerouslySetInnerHTML={ {
											__html: shortStatementDescriptorErrorMessage,
										} }
									/>
								</Notice>
							) } */ }
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
				</div>
				<div className="transactions-and-deposits__bank-information">
					<h4>
						{ __(
							'Bank account information',
							'woocommerce-payments'
						) }
					</h4>
					<p className="transactions-and-deposits__bank-information-help">
						{ __(
							'Manage and update your deposit account information to receive payments and payouts.',
							'woocommerce-payments'
						) }{ ' ' }
						<ExternalLink href={ accountLink }>
							{ __( 'Manage in Stripe', 'woocommerce-payments' ) }
						</ExternalLink>
					</p>
				</div>
			</CardBody>
		</Card>
	);
};

export default TransactionsAndDeposits;
