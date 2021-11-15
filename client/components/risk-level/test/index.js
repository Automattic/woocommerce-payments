/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import RiskLevel, { calculateRiskMapping } from '../';

describe( 'RiskLevel', () => {
	test( 'Renders normal risk correctly.', () => {
		const riskLevel = renderRisk( 0 );
		expect( riskLevel ).toMatchSnapshot();
	} );

	test( 'Renders elevated risk correctly.', () => {
		const riskLevel = renderRisk( 1 );
		expect( riskLevel ).toMatchSnapshot();
	} );

	test( 'Renders highest risk correctly.', () => {
		const riskLevel = renderRisk( 2 );
		expect( riskLevel ).toMatchSnapshot();
	} );

	test( 'Renders not assessed risk correctly.', () => {
		const riskLevel = renderRisk( undefined );
		expect( riskLevel ).toMatchSnapshot();
	} );

	function renderRisk( risk ) {
		return render( <RiskLevel risk={ risk } /> ).container;
	}
} );

describe( 'Test calculateRiskMapping', () => {
	test( 'Returns correct risk mapping as Normal when value is 0', () => {
		const riskMapping = calculateRiskMapping( 0 );
		expect( riskMapping ).toEqual( 'Normal' );
	} );

	test( 'Returns correct risk mapping as Elevated when value is 1', () => {
		const riskMapping = calculateRiskMapping( 1 );
		expect( riskMapping ).toEqual( 'Elevated' );
	} );

	test( 'Returns correct risk mapping as Highest when value is 2', () => {
		const riskMapping = calculateRiskMapping( 2 );
		expect( riskMapping ).toEqual( 'Highest' );
	} );
} );
