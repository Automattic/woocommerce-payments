/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useActiveEarlyFraudWarnings } from '../hooks';
import { ActiveEarlyFraudWarning } from '../types';

jest.mock( '@wordpress/data' );

describe( 'Early fraud warning data hooks', () => {
	const getActiveEarlyFraudWarnings = jest.fn();
	const getActiveEarlyFraudWarningsError = jest.fn();
	const hasFinishedResolution = jest.fn().mockReturnValue( false );

	beforeEach( () => {
		jest.clearAllMocks();
		hasFinishedResolution.mockReturnValue( false );
		( useSelect as jest.Mock ).mockImplementation( ( callback ) =>
			callback( () => ( {
				getActiveEarlyFraudWarnings,
				getActiveEarlyFraudWarningsError,
				hasFinishedResolution,
			} ) )
		);
	} );

	it( 'returns the stored warnings once the resolver has settled', () => {
		const warnings: ActiveEarlyFraudWarning[] = [
			{ order_id: 12, charge_id: 'ch_efw_1', created: 1719800000 },
		];
		getActiveEarlyFraudWarnings.mockReturnValue( warnings );
		hasFinishedResolution.mockReturnValue( true );

		const result = useActiveEarlyFraudWarnings();

		expect( result.activeEarlyFraudWarnings ).toBe( warnings );
		expect( result.hasLoaded ).toBe( true );
	} );

	// useSelect keeps the mapped object only while its values compare equal by identity,
	// so an inline [] fallback would re-render the Overview on every unrelated store change.
	it( 'reuses one empty array while the warnings are unresolved', () => {
		getActiveEarlyFraudWarnings.mockReturnValue( undefined );

		const first = useActiveEarlyFraudWarnings();
		const second = useActiveEarlyFraudWarnings();

		expect( first.activeEarlyFraudWarnings ).toEqual( [] );
		expect( first.activeEarlyFraudWarnings ).toBe(
			second.activeEarlyFraudWarnings
		);
	} );

	it( 'reports not loaded before the first list arrives', () => {
		getActiveEarlyFraudWarnings.mockReturnValue( undefined );
		hasFinishedResolution.mockReturnValue( false );

		expect( useActiveEarlyFraudWarnings().hasLoaded ).toBe( false );
	} );

	// Invalidating the list after a dismissal starts a refetch; blanking every task until it
	// returns made the Overview blink.
	it( 'keeps reporting loaded, with the earlier list, while a refetch is in flight', () => {
		const warnings: ActiveEarlyFraudWarning[] = [
			{ order_id: 12, charge_id: 'ch_efw_1', created: 1719800000 },
		];
		getActiveEarlyFraudWarnings.mockReturnValue( warnings );
		hasFinishedResolution.mockReturnValue( false );

		const result = useActiveEarlyFraudWarnings();

		expect( result.hasLoaded ).toBe( true );
		expect( result.activeEarlyFraudWarnings ).toBe( warnings );
	} );

	it( 'passes the resolver error through', () => {
		const error = { code: 'boom' };
		getActiveEarlyFraudWarnings.mockReturnValue( undefined );
		getActiveEarlyFraudWarningsError.mockReturnValue( error );

		expect(
			useActiveEarlyFraudWarnings().activeEarlyFraudWarningsError
		).toBe( error );
	} );
} );
