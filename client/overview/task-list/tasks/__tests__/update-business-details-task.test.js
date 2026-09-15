/**
 * Internal dependencies
 */
import { getUpdateBusinessDetailsTask } from '../update-business-details-task';

const mockRootRender = jest.fn();
const mockCreateRoot = jest.fn( () => ( {
	render: mockRootRender,
} ) );

jest.mock( 'react-dom/client', () => ( {
	createRoot: ( ...args ) => mockCreateRoot( ...args ),
} ) );

jest.mock( 'wcpay/overview/modal/update-business-details', () => () => null );

jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'getUpdateBusinessDetailsTask', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		mockCreateRoot.mockClear();
		mockRootRender.mockClear();
	} );

	it( 'reuses the React root when opening the modal more than once', () => {
		const task = getUpdateBusinessDetailsTask(
			[ 'First error', 'Second error' ],
			'restricted',
			'https://example.com/account',
			null,
			true,
			true
		);

		task.action();
		task.action();

		const container = document.querySelector(
			'#wcpay-update-business-details-container'
		);

		expect( mockCreateRoot ).toHaveBeenCalledTimes( 1 );
		expect( mockCreateRoot ).toHaveBeenCalledWith( container );
		expect( mockRootRender ).toHaveBeenCalledTimes( 2 );
		expect( mockRootRender.mock.calls[ 0 ] ).toHaveLength( 1 );
		expect( mockRootRender.mock.calls[ 1 ] ).toHaveLength( 1 );
	} );
} );
