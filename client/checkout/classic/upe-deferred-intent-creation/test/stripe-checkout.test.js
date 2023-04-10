/**
 * Internal dependencies
 */
import {
	initializeAppearance,
	inititalizeStripeElements,
} from '../stripe-checkout';
import { getAppearance } from '../../../upe-styles';

jest.mock( '../../../upe-styles' );

jest.mock( 'wcpay/utils/checkout', () => {
	return {
		getUPEConfig: jest.fn(),
		getConfig: jest.fn(),
	};
} );
import { getUPEConfig } from 'wcpay/utils/checkout';

describe( 'UPE appearance initialization', () => {
	let mockApi;
	beforeAll( () => {
		mockApi = {
			saveUPEAppearance: jest.fn(),
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'initializes the appearance when it is not set and saves it', () => {
		const mockAppearance = { backgroundColor: '#fff' };
		getAppearance.mockReturnValue( mockAppearance );

		initializeAppearance();

		expect( getAppearance ).toHaveBeenCalled();
	} );

	test( 'does not call getAppearance or saveUPEAppearance if appearance is already set', () => {
		const mockAppearance = { backgroundColor: '#fff' };
		getAppearance.mockReturnValue( mockAppearance );
		getUPEConfig.mockImplementation( () => {
			return {
				upeAppearance: { backgroundColor: '#fff' },
			};
		} );
		inititalizeStripeElements();

		initializeAppearance();

		expect( getAppearance ).not.toHaveBeenCalled();
		expect( mockApi.saveUPEAppearance ).not.toHaveBeenCalledWith(
			mockAppearance
		);
	} );
} );
