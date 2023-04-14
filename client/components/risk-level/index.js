/** @format */

/**
 * Internal dependencies
 */
import riskMappings from './strings';
import Pill from '../pill';

const riskOrder = [ 'normal', 'elevated', 'highest' ];

const colorMappings = {
	normal: 'success',
	elevated: 'alert',
	highest: 'danger',
};

export function calculateRiskMapping( risk ) {
	const riskLevel = riskOrder[ risk ];
	return riskMappings[ riskLevel ] || riskMappings.not_assessed;
}

const RiskLevel = ( { risk } ) => {
	const riskLevel = riskOrder[ risk ];

	return (
		<Pill type={ colorMappings[ riskLevel ] }>
			{ calculateRiskMapping( risk ) }
		</Pill>
	);
};

export default RiskLevel;
