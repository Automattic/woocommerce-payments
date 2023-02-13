/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ExceedsDollarAmountRule from './index';

describe( 'ExceedsDollarAmountRule', () => {
	it( 'renders the high level dollar amount rule when USD is store currency', () => {
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };

		const { container: highUSDRule } = render(
			<ExceedsDollarAmountRule
				level="high"
				storeCurrency={ storeCurrency }
			/>
		);

		expect( highUSDRule ).toMatchSnapshot();
	} );
	it( 'renders the standard level dollar amount rule when USD is store currency', () => {
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };

		const { container: standardUSDRule } = render(
			<ExceedsDollarAmountRule
				level="standard"
				storeCurrency={ storeCurrency }
			/>
		);

		expect( standardUSDRule ).toMatchSnapshot();
	} );
	it( 'renders the high level dollar amount rule equivalent when USD is not store currency', () => {
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };

		const { container: highNonUSDRule } = render(
			<ExceedsDollarAmountRule
				level="high"
				storeCurrency={ storeCurrency }
			/>
		);

		expect( highNonUSDRule ).toMatchSnapshot();
	} );
	it( 'renders the standard level dollar amount rule equivalent when USD is not store currency', () => {
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };

		const { container: standardNonUSDRule } = render(
			<ExceedsDollarAmountRule
				level="standard"
				storeCurrency={ storeCurrency }
			/>
		);

		expect( standardNonUSDRule ).toMatchSnapshot();
	} );
} );
