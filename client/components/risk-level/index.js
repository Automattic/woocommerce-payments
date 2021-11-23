/** @format */

/**
 * Internal dependencies
 */
import riskMappings from './strings';

const riskOrder = [ 'normal', 'elevated', 'highest' ];

const colorMappings = {
	normal: 'green',
	elevated: 'orange',
	highest: 'red',
};

export function calculateRiskMapping( risk ) {
	const riskLevel = riskOrder[ risk ];
	return riskMappings[ riskLevel ] || riskMappings.not_assessed;
}

const RiskLevel = ( { risk } ) => {
	const riskLevel = riskOrder[ risk ];

	return (
		<span style={ { color: colorMappings[ riskLevel ] } }>
			{ calculateRiskMapping( risk ) }
		</span>
	);
};

export default RiskLevel;
