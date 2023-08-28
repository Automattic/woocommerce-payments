/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies
 */
import Chip from '../chip';
import { getChipMessage } from './utils';

const PaymentMethodLabel = ( {
	label,
	required,
	status,
}: {
	label: string;
	required: boolean;
	status: string;
} ): React.ReactElement => {
	const chipMessage = getChipMessage( status );
	return (
		<>
			{ label }
			{ required && (
				<span className="payment-method__required-label">
					{ '(' + __( 'Required', 'woocommerce-payments' ) + ')' }
				</span>
			) }
			{ chipMessage && (
				<Chip message={ getChipMessage( status ) } type="warning" />
			) }
		</>
	);
};

export default PaymentMethodLabel;
