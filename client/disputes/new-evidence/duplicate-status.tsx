/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { RadioControl } from 'wcpay/components/wp-components-wrapped';

interface DuplicateStatusProps {
	duplicateStatus: string;
	onDuplicateStatusChange: ( value: string ) => void;
	readOnly?: boolean;
}

const DuplicateStatus: React.FC< DuplicateStatusProps > = ( {
	duplicateStatus,
	onDuplicateStatusChange,
	readOnly = false,
} ) => {
	const handleDuplicateStatusChange = ( value: string ) => {
		if ( ! readOnly ) {
			onDuplicateStatusChange( value );
		}
	};

	return (
		<section className="wcpay-dispute-evidence-duplicate-status">
			<h3 className="wcpay-dispute-evidence-duplicate-status__heading">
				{ __( 'Was this charge a duplicate?', 'woocommerce-payments' ) }
			</h3>
			<div className="wcpay-dispute-evidence-duplicate-status__field-group">
				<RadioControl
					selected={ duplicateStatus }
					options={ [
						{
							// eslint-disable-next-line @typescript-eslint/ban-ts-comment
							// @ts-ignore - description is a valid prop for RadioControl on newer versions of wordpress/components
							description: __(
								'A refund has been issued',
								'woocommerce-payments'
							),
							label: __(
								'It was a duplicate',
								'woocommerce-payments'
							),
							value: 'is_duplicate',
						},
						{
							// eslint-disable-next-line @typescript-eslint/ban-ts-comment
							// @ts-ignore - description is a valid prop for RadioControl on newer versions of wordpress/components
							description: __(
								'No refund needed',
								'woocommerce-payments'
							),
							label: __(
								'It was not a duplicate',
								'woocommerce-payments'
							),
							value: 'is_not_duplicate',
						},
					] }
					onChange={ handleDuplicateStatusChange }
					// eslint-disable-next-line @typescript-eslint/ban-ts-comment
					// @ts-ignore - disabled is not a valid prop for RadioControl, but it is in the HTML Input element
					disabled={ readOnly }
				/>
			</div>
		</section>
	);
};

export default DuplicateStatus;
