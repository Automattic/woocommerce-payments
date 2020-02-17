/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import RiskLevel from '../';

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

	function renderRisk( risk ) {
		return shallow( <RiskLevel risk={ risk } /> );
	}
} );
