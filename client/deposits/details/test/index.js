/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import { DepositDetails, DepositOverview } from '../';
import { useDeposit } from 'data';

jest.mock( 'data', () => ( {
	useDeposit: jest.fn(),
} ) );

const mockDeposit = {
	id: 'po_mock',
	date: '2020-01-02 17:46:02',
	type: 'deposit',
	amount: 2000,
	status: 'paid',
	bankAccount: 'MOCK BANK •••• 1234 (USD)',
};

describe( 'Deposit overview', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly', () => {
		useDeposit.mockReturnValue( {
			deposit: mockDeposit,
			isLoading: false,
		} );

		const overview = shallow(
			<DepositOverview depositId="po_mock" />
		);
		expect( overview ).toMatchSnapshot();
	} );
} );

describe( 'Deposit details page', () => {
	test( 'renders correctly', () => {
		const page = shallow(
			<DepositDetails query={ { id: 'po_mock' } } />
		);
		expect( page ).toMatchSnapshot();
	} );
} );
