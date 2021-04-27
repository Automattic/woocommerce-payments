/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CheckboxControl,
	TextControl,
	ExternalLink,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';

const GeneralSettings = ( { accountLink } ) => {
	const [ isEnabled, setIsEnabled ] = useState( false );
	const [ isManualCaptureEnabled, setIsManualCaptureEnabled ] = useState(
		false
	);
	const [ accountStatement, setAccountStatement ] = useState( '' );

	return (
		<Card className="general-settings">
			<CardBody size="large">
				<CheckboxControl
					checked={ isEnabled }
					onChange={ setIsEnabled }
					label={ __(
						'Enable WooCommerce Payments',
						'woocommerce-payments'
					) }
				/>
				<h4>
					{ __(
						'Credit card payment capture',
						'woocommerce-payments'
					) }
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
				<TextControl
					help={ __(
						'Edit the way your store name appears on your customers’ bank statements.',
						'woocommerce-payments'
					) }
					label={ __(
						'Customer bank statement',
						'woocommerce-payments'
					) }
					value={ accountStatement }
					onChange={ setAccountStatement }
					maxLength={ 22 }
					hideLabelFromVision
				/>
				<div className="general-settings__bank-information">
					<div>
						<h4>
							{ __(
								'Bank account information',
								'woocommerce-payments'
							) }
						</h4>
						<p>
							{ __(
								'Manage and update your bank account information to receive payments and payouts.',
								'woocommerce-payments'
							) }
						</p>
					</div>
					<ExternalLink
						className="components-button is-secondary"
						href={ accountLink }
					>
						Manage in Stripe
					</ExternalLink>
				</div>
			</CardBody>
		</Card>
	);
};

export default GeneralSettings;
