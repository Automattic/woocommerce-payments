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
	dispute: any;
	readOnly?: boolean;
}

const ShippingDetails: React.FC< ShippingDetailsProps > = ( {
	dispute,
	readOnly = false,
} ) => {
	if ( ! dispute ) return null;
	// These would typically come from dispute.evidence or dispute.order
	const evidence = dispute.evidence || {};
	const carrier = evidence.shipping_carrier || '-';
	const tracking = evidence.shipping_tracking_number || '-';
	const address = evidence.shipping_address || '-';
	const date = evidence.shipping_date || new Date();

	return (
		<section className="wcpay-dispute-evidence-shipping-details">
			<h3 className="wcpay-dispute-evidence-shipping-details__heading">
				{ __( 'Delivery details', 'woocommerce-payments' ) }
			</h3>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					// @ts-ignore
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'SHIPPING CARRIER', 'woocommerce-payments' ) }
					onChange={ () => {} }
					value={ carrier }
				/>
			</div>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					// @ts-ignore
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'SHIPPING DATE', 'woocommerce-payments' ) }
					onChange={ () => {} }
					value={ date.toLocaleDateString() }
				/>
			</div>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					// @ts-ignore
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'TRACKING NUMBER', 'woocommerce-payments' ) }
					help={ __(
						'Please make sure the tracking number is accurate.',
						'woocommerce-payments'
					) }
					onChange={ () => {} }
					value={ tracking }
				/>
			</div>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					// @ts-ignore
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'SHIPPING ADDRESS', 'woocommerce-payments' ) }
					help={ __(
						'We prefilled the shipping address for you, please make sure it’s accurate.',
						'woocommerce-payments'
					) }
					onChange={ () => {} }
					value={ address }
				/>
			</div>
		</section>
	);
};

export default ShippingDetails;
