/**
 * Internal dependencies
 */
import { FraudMetaBoxType, FraudOutcomeStatus } from '../../../data';
import {
	getRiskReviewListColumns,
	getRiskReviewListColumnsStructure,
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

describe( 'Review fraud outcome transactions columns', () => {
	const columns = getRiskReviewListColumns();

	const data = {
		amount: 1100,
		created: '2023-03-20 20:08:41',
		currency: 'USD',
		customer_name: 'Mock Customer',
		order_id: 123,
		payment_intent: {
			id: 'pi_mock',
			status: 'requires_capture',
		},
		status: 'review' as FraudOutcomeStatus,
		fraud_meta_box_type: 'review' as FraudMetaBoxType,
	};

	beforeEach( () => {
		global.wcpaySettings = mockWcPaySettings;
	} );

	afterAll( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the column correctly', () => {
		const result = getRiskReviewListColumnsStructure( data, columns );

		expect( result ).toHaveLength( 5 );
		expect( result ).toMatchSnapshot();
	} );
} );
