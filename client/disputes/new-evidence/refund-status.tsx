/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { RadioControl } from '@wordpress/components';

interface RefundStatusProps {
	refundStatus: string;
	onRefundStatusChange: ( value: string ) => void;
	readOnly?: boolean;
}

const RefundStatus: React.FC< RefundStatusProps > = ( {
	refundStatus,
	onRefundStatusChange,
	readOnly = false,
} ) => {
	const handleRefundStatusChange = ( value: string ) => {
		if ( ! readOnly ) {
			onRefundStatusChange( value );
		}
	};

	return (
		<section className="wcpay-dispute-evidence-refund-status">
			<h3 className="wcpay-dispute-evidence-refund-status__heading">
				{ __( 'Refund status', 'woocommerce-payments' ) }
			</h3>
			<div className="wcpay-dispute-evidence-refund-status__field-group">
				<RadioControl
					selected={ refundStatus }
					options={ [
						{
							label: __(
								'Refund has been issued',
								'woocommerce-payments'
							),
							value: 'refund_has_been_issued',
						},
						{
							label: __(
								'Refund was not owed',
								'woocommerce-payments'
							),
							value: 'refund_was_not_owed',
						},
					] }
					onChange={ handleRefundStatusChange }
					// eslint-disable-next-line @typescript-eslint/ban-ts-comment
					// @ts-ignore - disabled is not a valid prop for RadioControl, but it is in the HTML Input element
					disabled={ readOnly }
				/>
			</div>
		</section>
	);
};

export default RefundStatus;
