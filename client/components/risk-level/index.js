/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const riskMappings = [
	__( 'Normal', 'woocommerce-payments' ),
	__( 'Elevated', 'woocommerce-payments' ),
	__( 'Highest', 'woocommerce-payments' ),
];

const colorMappings = [
	'green',
	'orange',
	'red',
];

const RiskLevel = ( props ) => {
	const { risk } = props;

	return (
		<span style={ { color: colorMappings[ risk ] } }>{ riskMappings[ risk ] }</span>
	);
};

export default RiskLevel;
