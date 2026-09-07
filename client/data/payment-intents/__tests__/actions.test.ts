/** @format */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { dispatch, registerStore, select } from '@wordpress/data';

/**
 * Internal dependencies
 */
// Importing the store registers it, and pulls in whatever refundCharge dispatches to.
// Nothing else here may import the early fraud warnings store: the point of the test is
// that refundCharge registers it itself, the way a hard refresh on the payment details
// page has to.
import '../store';
import {
	PAYMENT_INTENTS_STORE_NAME,
	EARLY_FRAUD_WARNINGS_STORE_NAME,
} from '../../store-names';
import { Charge } from 'wcpay/types/charges';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

// WP admin ships core/notices; @wordpress/notices is an external here, so stub it.
registerStore( 'core/notices', {
	reducer: ( state = [] ) => state,
	actions: {
		createSuccessNotice: ( content: string ) => ( {
			type: 'CREATE_NOTICE',
			content,
		} ),
		createErrorNotice: ( content: string ) => ( {
			type: 'CREATE_NOTICE',
			content,
		} ),
	},
} );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const charge = {
	id: 'ch_flagged',
	amount: 2500,
	payment_intent: 'pi_1',
	order: { id: 42 },
} as Charge;

const refundCharge = ( target: Charge, reason: string | null ) =>
	// The store's action signatures are not typed on the registry's dispatch.
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	( dispatch( PAYMENT_INTENTS_STORE_NAME ) as any ).refundCharge(
		target,
		reason
	);

const hasResolvedWarnings = () =>
	select( EARLY_FRAUD_WARNINGS_STORE_NAME ).hasFinishedResolution(
		'getActiveEarlyFraudWarnings'
	);

describe( 'refundCharge', () => {
	beforeEach( () => {
		mockApiFetch.mockReset();
		mockApiFetch.mockResolvedValue( {} );
	} );

	// Every store refundCharge invalidates has to be registered by its own import graph.
	// Miss one and the dispatch throws past the action's own catch, leaving the refund
	// modal spinning on a refund that already went through.
	it( 'settles after a successful refund', async () => {
		await expect( refundCharge( charge, null ) ).resolves.toBeUndefined();
	} );

	it( 'settles after a failed refund', async () => {
		mockApiFetch.mockRejectedValue( new Error( 'nope' ) );

		await expect( refundCharge( charge, null ) ).resolves.toBeUndefined();
	} );

	it( 'invalidates the active early fraud warnings so the Overview task stops showing a refunded payment', async () => {
		// Resolve the selector once so there is a resolution for the refund to invalidate.
		select( EARLY_FRAUD_WARNINGS_STORE_NAME ).getActiveEarlyFraudWarnings();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		expect( hasResolvedWarnings() ).toBe( true );

		await refundCharge( charge, null );

		expect( hasResolvedWarnings() ).toBe( false );
	} );
} );
