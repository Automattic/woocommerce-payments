/** @format */

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import riskMappings from './strings';
import Pill from '../pill';

type RiskLevelNames = 'normal' | 'elevated' | 'highest' | 'not_assessed';

type RiskLevelColors = 'success' | 'alert' | 'danger' | 'light';

const riskOrder: RiskLevelNames[] = [ 'normal', 'elevated', 'highest' ];

const colorMappings: Record< RiskLevelNames, RiskLevelColors > = {
	normal: 'success',
	elevated: 'alert',
	highest: 'danger',
	not_assessed: 'light',
};

export function calculateRiskMapping( risk: number ): string {
	const riskLevel = riskOrder[ risk ];
	return riskMappings[ riskLevel ] || riskMappings.not_assessed;
}

const RiskLevel = ( { risk }: { risk: number } ): JSX.Element => {
	const riskLevel = riskOrder[ risk ];

	return (
		<Pill type={ colorMappings[ riskLevel ] }>
			{ calculateRiskMapping( risk ) }
		</Pill>
	);
};

export default RiskLevel;
