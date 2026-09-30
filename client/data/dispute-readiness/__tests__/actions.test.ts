/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	confirmStatementDescriptor,
	dismissDisputeReadinessCard,
} from '../actions';
import { DisputeReadinessData } from '../types';
import { ApiError } from '../../../types/errors';

const readinessPayload: DisputeReadinessData = {
	overview: {
		enabled: true,
		hidden: false,
		score: 4,
		total: 4,
		state: 'complete',
		isDismissed: true,
		completeSignalIds: [],
		incompleteSignalIds: [],
		signals: [],
	},
};
const errorResponse: ApiError = { code: 'error' };

const expectDisputeReadinessPostAction = (
	generator: Generator< unknown, unknown, unknown >,
	path: string
) => {
	expect( generator.next().value ).toEqual(
		apiFetch( {
			path,
			method: 'POST',
		} )
	);
};

const expectUpdatesDisputeReadinessOnSuccess = (
	generator: Generator< unknown, unknown, unknown >
) => {
	expect( generator.next( readinessPayload ).value ).toEqual(
		controls.dispatch(
			'wc/payments/disputeReadiness',
			'updateDisputeReadiness',
			readinessPayload
		)
	);
	expect( generator.next().done ).toStrictEqual( true );
};

const expectUpdatesDisputeReadinessErrorOnFailure = (
	generator: Generator< unknown, unknown, unknown >
) => {
	expect( generator.throw( errorResponse ).value ).toEqual(
		controls.dispatch(
			'wc/payments/disputeReadiness',
			'updateErrorForDisputeReadiness',
			errorResponse
		)
	);
	expect( generator.next().done ).toStrictEqual( true );
};

describe( 'dismissDisputeReadinessCard action', () => {
	test( 'updates dispute readiness on success', () => {
		const generator = dismissDisputeReadinessCard() as Generator<
			unknown,
			unknown,
			unknown
		>;
		expectDisputeReadinessPostAction(
			generator,
			'/wc/v3/payments/dispute-readiness/dismiss'
		);
		expectUpdatesDisputeReadinessOnSuccess( generator );
	} );

	test( 'updates dispute readiness error on failure', () => {
		const generator = dismissDisputeReadinessCard() as Generator<
			unknown,
			unknown,
			unknown
		>;
		expectDisputeReadinessPostAction(
			generator,
			'/wc/v3/payments/dispute-readiness/dismiss'
		);
		expectUpdatesDisputeReadinessErrorOnFailure( generator );
	} );
} );

describe( 'confirmStatementDescriptor action', () => {
	test( 'updates dispute readiness on success', () => {
		const generator = confirmStatementDescriptor() as Generator<
			unknown,
			unknown,
			unknown
		>;
		expectDisputeReadinessPostAction(
			generator,
			'/wc/v3/payments/dispute-readiness/statement-descriptor/confirm'
		);
		expectUpdatesDisputeReadinessOnSuccess( generator );
	} );

	test( 'updates dispute readiness error on failure', () => {
		const generator = confirmStatementDescriptor() as Generator<
			unknown,
			unknown,
			unknown
		>;
		expectDisputeReadinessPostAction(
			generator,
			'/wc/v3/payments/dispute-readiness/statement-descriptor/confirm'
		);
		expectUpdatesDisputeReadinessErrorOnFailure( generator );
	} );
} );
