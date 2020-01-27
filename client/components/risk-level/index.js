/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { capitalize } from 'lodash';

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
		<p style={ { color: colorMappings[ risk ] } }>{ capitalize( riskMappings[ risk ] ) }</p>
	);
};

export default RiskLevel;
