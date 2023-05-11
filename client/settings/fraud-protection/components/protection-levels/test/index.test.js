/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ProtectionLevels from '../index';

let mockAdvancedFraudProtectionSettings = null;

jest.mock( 'wcpay/data', () => ( {
	...jest.requireActual( 'wcpay/data' ),
	useAdvancedFraudProtectionSettings: jest.fn( () => [
		mockAdvancedFraudProtectionSettings,
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
		mockAdvancedFraudProtectionSettings = [];
		const { container: protectionLevels } = render( <ProtectionLevels /> );

		expect( protectionLevels ).toMatchSnapshot();
	} );

	it( 'renders with the advanced fraud protection settings selected', () => {
		mockAdvancedFraudProtectionSettings = [
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
		mockAdvancedFraudProtectionSettings = 'error';
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
