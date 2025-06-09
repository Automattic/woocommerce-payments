/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	CheckboxControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';

const dummyOnChange = () => {
	// eslint-disable-next-line no-console
	console.log( 1 );
};

const TaxDetails: React.FC = () => {
	return (
		<>
			<div className="wcpay-tax-details">
				<p>
					<h4>
						{ __( 'Set your VAT number', 'woocommerce-payments' ) }
					</h4>
					<CheckboxControl
						checked={ true }
						label={ sprintf(
							__(
								/* translators: %$1$s: tax ID name, e.g. VAT Number, GST Number, Corporate Number */
								'I have a valid VAT Number',
								'woocommerce-payments'
							)
						) }
						onChange={ dummyOnChange }
						help="If your sales exceed the VAT threshold for your country, you're required to register for a VAT Number."
					/>
					<TextControl
						label={ __( 'VAT Number', 'woocommerce-payments' ) }
						value="GB 1234"
						onChange={ dummyOnChange }
					/>
				</p>
				<p>
					<h4>
						{ __(
							'Confirm your business details',
							'woocommerce-payments'
						) }
					</h4>
					<TextControl
						label={ __( 'Business name', 'woocommerce-payments' ) }
						value="Big Quid Ltd"
						onChange={ dummyOnChange }
					/>

					<TextareaControl
						label={ __( 'Address', 'woocommerce-payments' ) }
						value="123 Main St, London, UK"
						onChange={ dummyOnChange }
					/>
				</p>
			</div>
		</>
	);
};

export default TaxDetails;
