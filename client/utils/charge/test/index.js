/** @format */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import * as utils from '../';

const paidCharge = { status: 'succeeded', paid: true, captured: true };
const failedCharge = {
	status: 'failed',
	paid: false,
	captured: false,
	outcome: { type: 'issuer_declined' },
};
const blockedCharge = {
	status: 'failed',
	paid: false,
	captured: false,
	outcome: { type: 'blocked' },
};
const authorizedCharge = { status: 'succeeded', paid: true, captured: false };
const getDisputedChargeWithStatus = ( status ) => ( {
	disputed: true,
	dispute: { status: status },
} );
const fullyRefundedCharge = {
	amount: 1500,
	refunded: true,
	// eslint-disable-next-line camelcase
	amount_refunded: 1500,
};
const partiallyRefundedCharge = {
	amount: 1500,
	refunded: false,
	// eslint-disable-next-line camelcase
	amount_refunded: 1200,
};

describe( 'Charge utilities', () => {
	describe( 'isCharge methods', () => {
		test( 'should identify a captured successful charge as successful', () => {
			expect( utils.isChargeSuccessful( paidCharge ) ).toEqual( true );
		} );

		test( 'should identify a not captured successful charge as successful', () => {
			expect( utils.isChargeSuccessful( authorizedCharge ) ).toEqual(
				true
			);
		} );

		test( 'should identify a captured successful charge as captured', () => {
			expect( utils.isChargeCaptured( paidCharge ) ).toEqual( true );
		} );

		test( 'should not identify a not captured successful charge as captured', () => {
			expect( utils.isChargeCaptured( authorizedCharge ) ).toEqual(
				false
			);
		} );

		test( 'should not identify a failed charge as successful', () => {
			expect( utils.isChargeSuccessful( failedCharge ) ).toEqual( false );
		} );

		test( 'should not identify a blocked charge as successful', () => {
			expect( utils.isChargeSuccessful( blockedCharge ) ).toEqual(
				false
			);
		} );

		test( 'should identify a failed charge as failed', () => {
			expect( utils.isChargeFailed( failedCharge ) ).toEqual( true );
		} );

		test( 'should identify a blocked charge as blocked', () => {
			expect( utils.isChargeBlocked( blockedCharge ) ).toEqual( true );
		} );

		test( 'should not identify a successful charge as failed', () => {
			expect( utils.isChargeFailed( paidCharge ) ).toEqual( false );
		} );

		test( 'should not identify a successful charge as failed', () => {
			expect( utils.isChargeBlocked( paidCharge ) ).toEqual( false );
		} );

		test( 'should identify a fully refunded charge as fully refunded', () => {
			expect(
				utils.isChargeFullyRefunded( fullyRefundedCharge )
			).toEqual( true );
		} );

		test( 'should not identify a partially refunded charge as fully refunded', () => {
			expect(
				utils.isChargeFullyRefunded( partiallyRefundedCharge )
			).toEqual( false );
		} );

		test( 'should not identify a successful charge as fully refunded', () => {
			expect( utils.isChargeFullyRefunded( paidCharge ) ).toEqual(
				false
			);
		} );

		test( 'should identify a partially refunded charge as partially refunded', () => {
			expect(
				utils.isChargePartiallyRefunded( partiallyRefundedCharge )
			).toEqual( true );
		} );

		test( 'should not identify a fully refunded charge as partially refunded', () => {
			expect(
				utils.isChargePartiallyRefunded( fullyRefundedCharge )
			).toEqual( false );
		} );

		test( 'should not identify a successful charge as partially refunded', () => {
			expect( utils.isChargePartiallyRefunded( paidCharge ) ).toEqual(
				false
			);
		} );
	} );

	describe( 'getChargeStatus', () => {
		const chargeStatuses = [
			[ 'paid', paidCharge ],
			[ 'authorized', authorizedCharge ],
			[ 'failed', failedCharge ],
			[ 'refunded_full', fullyRefundedCharge ],
			[ 'refunded_partial', partiallyRefundedCharge ],
		];

		test.each( chargeStatuses )(
			'returns %s status for charge',
			( status, charge ) => {
				expect( utils.getChargeStatus( charge ) ).toEqual( status );
			}
		);

		const disputeStatuses = [
			'needs_response',
			'under_review',
			'won',
			'lost',
			'warning_needs_response',
			'warning_under_review',
			'warning_closed',
		];

		test.each( disputeStatuses )(
			'returns disputed status for %s',
			( status ) => {
				expect(
					utils.getChargeStatus(
						getDisputedChargeWithStatus( status )
					)
				).toEqual( 'disputed_' + status );
			}
		);

		test.each( disputeStatuses )(
			'disputed statuses take precedence over refunds',
			( status ) => {
				const charge = {
					...getDisputedChargeWithStatus( status ),
					...fullyRefundedCharge,
				};
				expect( utils.getChargeStatus( charge ) ).toEqual(
					'disputed_' + status
				);
			}
		);
	} );
} );

describe( 'Charge utilities / getChargeAmounts', () => {
	test( 'basic charge', () => {
		const charge = {
			amount: 1800,
			// eslint-disable-next-line camelcase
			application_fee_amount: 82,
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'USD',
			net: charge.amount - charge.application_fee_amount,
			fee: charge.application_fee_amount,
			refunded: 0,
		} );
	} );

	test( 'partial refund', () => {
		const charge = {
			amount: 1800,
			// eslint-disable-next-line camelcase
			application_fee_amount: 82,
			// eslint-disable-next-line camelcase
			amount_refunded: 300,
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'USD',
			net:
				charge.amount -
				charge.application_fee_amount -
				charge.amount_refunded,
			fee: charge.application_fee_amount,
			refunded: charge.amount_refunded,
		} );
	} );

	test( 'full refund', () => {
		const charge = {
			amount: 1800,
			// eslint-disable-next-line camelcase
			application_fee_amount: 82,
			// eslint-disable-next-line camelcase
			amount_refunded: 1800,
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'USD',
			net:
				charge.amount -
				charge.application_fee_amount -
				charge.amount_refunded,
			fee: charge.application_fee_amount,
			refunded: charge.amount_refunded,
		} );
	} );

	test( 'full dispute', () => {
		const charge = {
			amount: 1800,
			// eslint-disable-next-line camelcase
			application_fee_amount: 82,
			disputed: true,
			dispute: {
				amount: 1800,
				// eslint-disable-next-line camelcase
				balance_transactions: [
					{
						amount: -1800,
						fee: 1500,
					},
				],
			},
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'USD',
			net: 0 - charge.application_fee_amount - 1500,
			fee: charge.application_fee_amount + 1500,
			refunded: charge.dispute.amount,
		} );
	} );

	test( 'reversed dispute', () => {
		const charge = {
			amount: 1800,
			// eslint-disable-next-line camelcase
			application_fee_amount: 82,
			disputed: true,
			dispute: {
				amount: 1800,
				// eslint-disable-next-line camelcase
				balance_transactions: [
					{
						amount: -1800,
						fee: 1500,
					},
					{
						amount: 1800,
						fee: -1500,
					},
				],
			},
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'USD',
			net: charge.amount - charge.application_fee_amount,
			fee: charge.application_fee_amount,
			refunded: 0,
		} );
	} );

	test( 'inquiry', () => {
		const charge = {
			amount: 1800,
			// eslint-disable-next-line camelcase
			application_fee_amount: 82,
			disputed: true,
			dispute: {
				amount: 1800,
				// eslint-disable-next-line camelcase
				balance_transactions: [],
			},
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'USD',
			net: charge.amount - charge.application_fee_amount,
			fee: charge.application_fee_amount,
			refunded: 0,
		} );
	} );
} );
