/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import CardPresentDetails from '../';

describe( 'CardPresentDetails', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcSettings = {
			countries: {
				AU: 'Australia',
				US: 'United States of America',
			},
		};
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	afterEach( () => {
		delete global.wcSettings;
		delete global.wcpaySettings;
	} );

	test( 'renders loading', () => {
		const charge = {
			payment_method_details: {
				type: 'card_present',
				card_present: {
					brand: 'network',
					last4: '9999',
					fingerprint: '123456789abc',
					exp_month: '11',
					exp_year: '2023',
					funding: 'funding',
					network: 'network',
					country: 'US',
				},
			},
			billing_details: {
				name: 'foo',
				email: 'bar',
				formattedAddress: 'baz',
			},
		};

		const { container } = render(
			<CardPresentDetails charge={ charge } isLoading={ false } />
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders eftpos_au as eftpos', () => {
		const charge = {
			payment_method_details: {
				type: 'card_present',
				card_present: {
					brand: 'visa',
					last4: '0978',
					fingerprint: '123456789abc',
					exp_month: '12',
					exp_year: '2030',
					funding: 'debit',
					network: 'eftpos_au',
					country: 'AU',
				},
			},
			billing_details: {
				name: 'foo',
				email: 'bar',
				formattedAddress: 'baz',
			},
		};

		const { container } = render(
			<CardPresentDetails charge={ charge } isLoading={ false } />
		);

		expect( container.textContent ).toContain( 'eftpos debit card' );
		expect( container.textContent ).not.toContain( 'Eftpos_au' );
	} );

	test( 'renders unsupported networks using the network value', () => {
		const charge = {
			payment_method_details: {
				type: 'card_present',
				card_present: {
					brand: 'visa',
					last4: '0978',
					fingerprint: '123456789abc',
					exp_month: '12',
					exp_year: '2030',
					funding: 'debit',
					network: 'unsupported_network',
					country: 'US',
				},
			},
			billing_details: {
				name: 'foo',
				email: 'bar',
				formattedAddress: 'baz',
			},
		};

		const { container } = render(
			<CardPresentDetails charge={ charge } isLoading={ false } />
		);

		expect( container.textContent ).toContain(
			'Unsupported_network debit card'
		);
	} );

	test( 'renders cartes_bancaires as Cartes Bancaires', () => {
		const charge = {
			payment_method_details: {
				type: 'card_present',
				card_present: {
					brand: 'visa',
					last4: '4242',
					fingerprint: '123456789abc',
					exp_month: '12',
					exp_year: '2030',
					funding: 'debit',
					network: 'cartes_bancaires',
					country: 'FR',
				},
			},
			billing_details: {
				name: 'foo',
				email: 'bar',
				formattedAddress: 'baz',
			},
		};

		global.wcSettings.countries.FR = 'France';

		const { container } = render(
			<CardPresentDetails charge={ charge } isLoading={ false } />
		);

		expect( container.textContent ).toContain(
			'Cartes Bancaires debit card'
		);
		expect( container.textContent ).not.toContain( 'Cartes_bancaires' );
	} );
} );
