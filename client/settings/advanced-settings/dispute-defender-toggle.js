/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { CheckboxControl } from '@wordpress/components';
import { useDisputeDefender } from 'wcpay/data';

const DisputeDefenderToggle = () => {
	const [ isDisputeDefenderEnabled, updateIsDisputeDefenderEnabled ] =
		useDisputeDefender();

	const handleDisputeDefenderStatusChange = ( value ) => {
		updateIsDisputeDefenderEnabled( value );
	};

	return (
		<CheckboxControl
			label={ __( 'Enable Dispute Defender AI', 'woocommerce-payments' ) }
			help={ __(
				'Use AI to draft evidence for fraudulent disputes. You always review before submitting.',
				'woocommerce-payments'
			) }
			checked={ isDisputeDefenderEnabled }
			onChange={ handleDisputeDefenderStatusChange }
			data-testid="dispute-defender-toggle"
			__nextHasNoMarginBottom
		/>
	);
};

export default DisputeDefenderToggle;
