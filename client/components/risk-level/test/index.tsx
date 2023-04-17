/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import RiskLevel, { calculateRiskMapping } from '..';

describe( 'RiskLevel', () => {
	function renderRisk( risk: number ): HTMLElement {
		return render( <RiskLevel risk={ risk } /> ).container;
	}

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
		const riskLevel = renderRisk( 99 );
		expect( riskLevel ).toMatchSnapshot();
	} );
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
