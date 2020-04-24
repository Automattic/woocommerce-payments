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

const RiskLevel = ( { risk } ) => {
	const riskLevel = riskOrder[ risk ];

	return (
		<span style={ { color: colorMappings[ riskLevel ] } }>
			{ riskMappings[ riskLevel ] }
		</span>
	);
};

export default RiskLevel;
