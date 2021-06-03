/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CheckboxControl,
	TextControl,
	ExternalLink,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	useAccountStatementDescriptor,
	useDevMode,
	useIsWCPayEnabled,
	useManualCapture,
	useTestMode,
} from 'data';

const ACCOUNT_STATEMENT_MAX_LENGTH = 22;

const GeneralSettings = ( { accountLink } ) => {
	const [
		accountStatementDescriptor,
		setAccountStatementDescriptor,
	] = useAccountStatementDescriptor();
	const [ isWCPayEnabled, setIsWCPayEnabled ] = useIsWCPayEnabled();
	const [
		isManualCaptureEnabled,
		setIsManualCaptureEnabled,
	] = useManualCapture();
	const [ isEnabled, updateIsTestModeEnabled ] = useTestMode();
	const isDevModeEnabled = useDevMode();

	return (
		<Card className="general-settings">
			<CardBody size="large">
				<CheckboxControl
					checked={ isWCPayEnabled }
					onChange={ setIsWCPayEnabled }
					label={ __(
						'Enable WooCommerce Payments',
						'woocommerce-payments'
					) }
					help={ __(
						'When enabled, payment methods powered by WooCommerce Payments will appear on checkout.',
						'woocommerce-payments'
					) }
				/>
				<h4>{ __( 'Test mode', 'woocommerce-payments' ) }</h4>
				<CheckboxControl
					checked={ isDevModeEnabled || isEnabled }
					disabled={ isDevModeEnabled }
					onChange={ updateIsTestModeEnabled }
					label={
						isDevModeEnabled
							? __(
									'Dev mode is active so all transactions will be in test mode. ' +
										'This setting is only available to live accounts.',
									'woocommerce-payments'
							  )
							: __(
									'Enable test mode for payments on the store',
									'woocommerce-payments'
							  )
					}
					help={ interpolateComponents( {
						mixedString: __(
							'Use {{testCardHelpLink}}test card numbers{{/testCardHelpLink}} to simulate various transactions. ' +
								'{{learnMoreLink}}Learn more{{/learnMoreLink}}.',
							'woocommerce-payments'
						),
						components: {
							testCardHelpLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="https://docs.woocommerce.com/document/payments/testing/#test-cards"
								/>
							),
							learnMoreLink: (
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a
									target="_blank"
									rel="noreferrer"
									href="https://docs.woocommerce.com/document/payments/testing/"
								/>
							),
						},
					} ) }
				/>
				<h4>
					{ __( 'Transaction preferences', 'woocommerce-payments' ) }
				</h4>
				<CheckboxControl
					checked={ isManualCaptureEnabled }
					onChange={ setIsManualCaptureEnabled }
					label={ __(
						'Issue an authorization on checkout, and capture later',
						'woocommerce-payments'
					) }
					help={ __(
						'Charge must be captured within 7 days of authorization, otherwise the authorization and order will be canceled.',
						'woocommerce-payments'
					) }
				/>
				<h4>
					{ __( 'Customer bank statement', 'woocommerce-payments' ) }
				</h4>
				<div className="general-settings__account-statement-wrapper">
					<TextControl
						className="general-settings__account-statement-input"
						help={ __(
							"Edit the way your store name appears on your customers' bank statements.",
							'woocommerce-payments'
						) }
						label={ __(
							'Customer bank statement',
							'woocommerce-payments'
						) }
						value={ accountStatementDescriptor }
						onChange={ setAccountStatementDescriptor }
						maxLength={ ACCOUNT_STATEMENT_MAX_LENGTH }
						hideLabelFromVision
					/>
					<span className="input-help-text" aria-hidden="true">
						{ `${ accountStatementDescriptor.length } / ${ ACCOUNT_STATEMENT_MAX_LENGTH }` }
					</span>
				</div>
				<div className="general-settings__bank-information">
					<h4>
						{ __(
							'Bank account information',
							'woocommerce-payments'
						) }
					</h4>
					<p className="general-settings__bank-information-help">
						{ __(
							'Manage and update your bank account information to receive payments and payouts.',
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

export default GeneralSettings;
