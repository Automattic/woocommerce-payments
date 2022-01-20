/* eslint-disable max-len */
/**
 * External dependencies
 */
import React from 'react';
import { useState } from '@wordpress/element';
import { CheckboxControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import LockIcon from '../icons/lock-icon';
import PhoneIcon from '../icons/phone-icon';
import './style.scss';

const CheckoutPageSaveUser = () => {
	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState( true );

	return (
		<div className="platform-checkout-save-new-user-container">
			<h3>Remember your details?</h3>
			<CheckboxControl
				checked={ isSaveDetailsChecked }
				onChange={ () =>
					setIsSaveDetailsChecked( ! isSaveDetailsChecked )
				}
				label={ __(
					'Save my information for faster checkouts',
					'woocommerce-payments'
				) }
			/>
			{ isSaveDetailsChecked && (
				<div className="save-details-form">
					<div className="about-platform-checkout">
						{ interpolateComponents( {
							mixedString: __(
								'Enter your phone number to save your checkout information. You’ll get {{strong}}secure single-click checkouts{{/strong}} here, and at 1,000s of other stores using XYZ.',
								'woocommerce-payments'
							),
							components: { strong: <b /> },
						} ) }
					</div>
					<TextControl
						type="text"
						label={ __(
							'Mobile phone number',
							'woocommerce-payments'
						) }
						value={ '' }
						onChange={ () => {} }
					/>
					<div className="additional-information">
						<PhoneIcon />
						<span>
							{ __(
								'Next time time you checkout, we’ll send you a text message to access your saved information.',
								'woocommerce-payments'
							) }
						</span>
					</div>
					<div className="additional-information">
						<LockIcon />
						<span>
							{ __(
								'Your personal details will be encrypted from end to end and payments go through 100% secure servers.',
								'woocommerce-payments'
							) }
						</span>
					</div>
					<div className="tos">
						{ interpolateComponents( {
							mixedString: __(
								'By entering your phone number and completing your purchase, you will create a XYZ account and agree to {{termsOfService/}} and {{privacyPolicy/}}.',
								'woocommerce-payments'
							),
							components: {
								termsOfService: (
									<a href="?TODO">
										{ __(
											'Terms of Service',
											'woocommerce-payments'
										) }
									</a>
								),
								privacyPolicy: (
									<a href="?TODO">
										{ __(
											'Privacy Policy',
											'woocommerce-payments'
										) }
									</a>
								),
							},
						} ) }
					</div>
				</div>
			) }
		</div>
	);
};

export default CheckoutPageSaveUser;
