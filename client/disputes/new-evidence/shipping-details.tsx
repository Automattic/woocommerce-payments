/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { TextControl } from 'wcpay/components/wp-components-wrapped';

interface ShippingDetailsProps {
	shippingCarrier: string;
	shippingDate: string;
	shippingTrackingNumber: string;
	shippingAddress: string;
	readOnly?: boolean;
	onShippingCarrierChange: ( value: string ) => void;
	onShippingDateChange: ( value: string ) => void;
	onShippingTrackingNumberChange: ( value: string ) => void;
	onShippingAddressChange: ( value: string ) => void;
}

const ShippingDetails: React.FC< ShippingDetailsProps > = ( {
	shippingCarrier,
	shippingDate,
	shippingTrackingNumber,
	shippingAddress,
	readOnly = false,
	onShippingCarrierChange,
	onShippingDateChange,
	onShippingTrackingNumberChange,
	onShippingAddressChange,
} ) => {
	return (
		<section className="wcpay-dispute-evidence-shipping-details">
			<h3 className="wcpay-dispute-evidence-shipping-details__heading">
				{ __( 'Delivery details', 'woocommerce-payments' ) }
			</h3>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					label={ __( 'SHIPPING CARRIER', 'woocommerce-payments' ) }
					onChange={ onShippingCarrierChange }
					value={ shippingCarrier }
					disabled={ readOnly }
				/>
			</div>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					label={ __( 'SHIPPING DATE', 'woocommerce-payments' ) }
					onChange={ onShippingDateChange }
					type="date"
					value={
						shippingDate
							? new Date( shippingDate )
									.toISOString()
									.split( 'T' )[ 0 ]
							: new Date().toISOString().split( 'T' )[ 0 ]
					}
					disabled={ readOnly }
				/>
			</div>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					label={ __( 'TRACKING NUMBER', 'woocommerce-payments' ) }
					help={ __(
						'Please make sure the tracking number is accurate.',
						'woocommerce-payments'
					) }
					onChange={ onShippingTrackingNumberChange }
					value={ shippingTrackingNumber }
					disabled={ readOnly }
				/>
			</div>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					label={ __( 'SHIPPING ADDRESS', 'woocommerce-payments' ) }
					help={ __(
						"We prefilled the shipping address for you, please make sure it's accurate.",
						'woocommerce-payments'
					) }
					onChange={ onShippingAddressChange }
					value={ shippingAddress.replace( /\n/g, ' ' ) }
					disabled={ readOnly }
				/>
			</div>
		</section>
	);
};

export default ShippingDetails;
