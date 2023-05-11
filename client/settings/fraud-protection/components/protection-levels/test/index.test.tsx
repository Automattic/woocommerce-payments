/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ProtectionLevels from '../index';
import { FraudProtectionRule } from 'wcpay/settings/fraud-protection/interfaces';

declare const global: {
	wcpaySettings: {
		isMultiCurrencyEnabled: string;
	};
};

let mockFraudProtectionRule: FraudProtectionRule[] | string = [];

jest.mock( 'wcpay/data', () => ( {
	...jest.requireActual( 'wcpay/data' ),
	useAdvancedFraudProtectionSettings: jest.fn( () => [
		mockFraudProtectionRule,
		jest.fn(),
	] ),
} ) );

describe( 'ProtectionLevels', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			isMultiCurrencyEnabled: '1',
		};
	} );

	it( 'renders', () => {
		mockFraudProtectionRule = [];
		const { container: protectionLevels } = render( <ProtectionLevels /> );

		expect( protectionLevels ).toMatchSnapshot();
	} );

	it( 'renders with the advanced fraud protection settings selected', () => {
		mockFraudProtectionRule = [
			{
				key: 'international_ip_address',
				outcome: 'review',
				check: {
					key: 'ip_country',
					operator: 'in',
					value: '',
				},
			},
		];
		const { container: protectionLevels } = render( <ProtectionLevels /> );

		expect( protectionLevels ).toMatchSnapshot();
	} );

	it( 'renders an error message when settings can not be fetched from the server', () => {
		mockFraudProtectionRule = 'error';
		const { container: protectionLevels } = render( <ProtectionLevels /> );

		expect( protectionLevels ).toMatchSnapshot();
		expect( protectionLevels ).toHaveTextContent(
			/There was an error retrieving your fraud protection settings/i
		);
		expect(
			protectionLevels.getElementsByTagName( 'fieldset' )[ 0 ]
		).toBeDisabled();
	} );
} );
