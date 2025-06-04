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
	onShippingDetailsChange?: ( evidence: any ) => void;
}

const ShippingDetails: React.FC< ShippingDetailsProps > = ( {
	dispute,
	readOnly = false,
	onShippingDetailsChange,
} ) => {
	const [ localEvidence, setLocalEvidence ] = React.useState(
		dispute.evidence || {}
	);

	React.useEffect( () => {
		setLocalEvidence( dispute.evidence || {} );
	}, [ dispute ] );

	if ( ! dispute ) return null;

	const handleChange = ( key: string, value: string ) => {
		setLocalEvidence( ( prev: any ) => {
			const next = { ...prev, [ key ]: value };
			if ( onShippingDetailsChange ) {
				onShippingDetailsChange( next );
			}
			return next;
		} );
	};

	return (
		<section className="wcpay-dispute-evidence-shipping-details">
			<h3 className="wcpay-dispute-evidence-shipping-details__heading">
				{ __( 'Delivery details', 'woocommerce-payments' ) }
			</h3>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					label={ __( 'SHIPPING CARRIER', 'woocommerce-payments' ) }
					onChange={ ( value ) =>
						handleChange( 'shipping_carrier', value )
					}
					value={ ( localEvidence.shipping_carrier || '' ).replace(
						/\n/g,
						' '
					) }
					disabled={ readOnly }
				/>
			</div>
			<div className="wcpay-dispute-evidence-shipping-details__field-group">
				<TextControl
					label={ __( 'SHIPPING DATE', 'woocommerce-payments' ) }
					onChange={ ( value ) =>
						handleChange( 'shipping_date', value )
					}
					value={
						localEvidence.shipping_date
							? new Date(
									localEvidence.shipping_date
							  ).toLocaleDateString()
							: new Date().toLocaleDateString()
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
					onChange={ ( value ) =>
						handleChange( 'shipping_tracking_number', value )
					}
					value={ localEvidence.shipping_tracking_number || '' }
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
					onChange={ ( value ) =>
						handleChange( 'shipping_address', value )
					}
					value={ localEvidence.shipping_address || '' }
					disabled={ readOnly }
				/>
			</div>
		</section>
	);
};

export default ShippingDetails;
