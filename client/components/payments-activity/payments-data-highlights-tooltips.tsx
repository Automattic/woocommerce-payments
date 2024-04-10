/**
 * External dependencies
 */
import * as React from 'react';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ClickTooltip } from 'components/tooltip';

export const TotalPaymentsVolumeTooltip: React.FC = () => {
	return (
		<ClickTooltip
			className="total-payments-volume__tooltip"
			buttonIcon={ <HelpOutlineIcon /> }
			buttonLabel={ __(
				'Total payments volume tooltip',
				'woocommerce-payments'
			) }
			content={ __(
				'test total payments volume content',
				'woocommerce-payments'
			) }
		/>
	);
};

export const PaymentsDataChargeTooltip: React.FC = () => {
	return (
		<ClickTooltip
			className="payments-data-highlights__charges__tooltip"
			buttonIcon={ <HelpOutlineIcon /> }
			buttonLabel={ __( 'Charges tooltip', 'woocommerce-payments' ) }
			content={ __( 'test charge content' ) }
		/>
	);
};

export const PaymentsDataFeesTooltip: React.FC = () => {
	return (
		<ClickTooltip
			className="payments-data-highlights__fees__tooltip"
			buttonIcon={ <HelpOutlineIcon /> }
			buttonLabel={ __( 'Fees tooltip', 'woocommerce-payments' ) }
			content={ __( 'test fees content', 'woocommerce-payments' ) }
		/>
	);
};
