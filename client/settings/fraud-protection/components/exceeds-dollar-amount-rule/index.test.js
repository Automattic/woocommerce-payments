/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ExceedsDollarAmountRule from './index';
import { useCurrencies } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useCurrencies: jest.fn(),
} ) );

describe( 'ExceedsDollarAmountRule', () => {
	beforeEach( () =>
		useCurrencies.mockReturnValue( {
			isLoading: false,
			currencies: {
				available: {
					EUR: { name: 'Euro', symbol: '€', code: 'EUR' },
					USD: { name: 'US Dollar', symbol: '$', code: 'USD' },
					PLN: { name: 'Polish złoty', symbol: 'zł', code: 'PLN' },
				},
				default: {
					USD: { name: 'US Dollar', symbol: '$', code: 'usd' },
				},
			},
		} )
	);
	it( 'renders the high level dollar amount rule when USD is store currency', () => {
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };

		render(
			<ExceedsDollarAmountRule
				level="high"
				storeCurrency={ storeCurrency }
			/>
		);

		const highUSDRule = screen.getByText( /exceeds/ );
		expect( highUSDRule ).toBeInTheDocument();
		expect( highUSDRule ).toHaveTextContent(
			'An order exceeds $1,000.00.'
		);
	} );
	it( 'renders the standard level dollar amount rule when USD is store currency', () => {
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };

		render(
			<ExceedsDollarAmountRule
				level="standard"
				storeCurrency={ storeCurrency }
			/>
		);

		const standardUSDRule = screen.getByText( /exceeds/ );
		expect( standardUSDRule ).toBeInTheDocument();
		expect( standardUSDRule ).toHaveTextContent(
			'An order exceeds $1,000.00 or 10 items.'
		);
	} );
	it( 'renders the high level dollar amount rule equivalent when USD is not store currency', () => {
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };

		render(
			<ExceedsDollarAmountRule
				level="high"
				storeCurrency={ storeCurrency }
			/>
		);

		const highNonUSDRule = screen.getByText( /exceeds/ );
		expect( highNonUSDRule ).toBeInTheDocument();
		expect( highNonUSDRule ).toHaveTextContent(
			'An order exceeds the equivalent of $1,000.00 USD in Euro.'
		);
	} );
	it( 'renders the standard level dollar amount rule equivalent when USD is not store currency', () => {
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };

		render(
			<ExceedsDollarAmountRule
				level="standard"
				storeCurrency={ storeCurrency }
			/>
		);

		const standardNonUSDRule = screen.getByText( /exceeds/ );
		expect( standardNonUSDRule ).toBeInTheDocument();
		expect( standardNonUSDRule ).toHaveTextContent(
			'An order exceeds the equivalent of $1,000.00 USD in Euro or 10 items.'
		);
	} );
} );
