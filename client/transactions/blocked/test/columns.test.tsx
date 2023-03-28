/**
 * Internal dependencies
 */
import { FraudOutcomeStatus } from '../../../data';
import {
	getBlockedListColumns,
	getBlockedListColumnsStructure,
} from '../columns';

// Mock the wcpaySettings localized variables needed by these tests.
declare const global: {
	wcpaySettings: {
		accountDefaultCurrency: string;
		zeroDecimalCurrencies: string[];
		connect: {
			country: string;
		};
	};
};
const mockWcPaySettings = {
	accountDefaultCurrency: 'USD',
	zeroDecimalCurrencies: [],
	connect: {
		country: 'US',
	},
};

describe( 'Blocked fraud outcome transactions columns', () => {
	const columns = getBlockedListColumns();

	const data = {
		amount: 1100,
		created: '2023-03-20 20:08:41',
		currency: 'USD',
		customer_name: 'Mock Customer',
		order_id: 123,
		payment_intent: {
			id: '',
			status: '',
		},
		status: 'block' as FraudOutcomeStatus,
	};

	beforeEach( () => {
		global.wcpaySettings = mockWcPaySettings;
	} );

	afterAll( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the column correctly', () => {
		const result = getBlockedListColumnsStructure( data, columns );

		expect( result ).toHaveLength( 4 );
		expect( result ).toMatchSnapshot();
	} );
} );
