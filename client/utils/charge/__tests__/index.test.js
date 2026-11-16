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
const authorizedPaymentIntent = { status: 'requires_capture' };
const getDisputedChargeWithStatus = ( status ) => ( {
	disputed: true,
	dispute: { status: status },
} );
const fullyRefundedCharge = {
	amount: 1500,
	refunded: true,
	amount_refunded: 1500,
};
const partiallyRefundedCharge = {
	amount: 1500,
	refunded: false,
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

		test.each( [ 'failed', 'canceled' ] )(
			'should identify a charge with only %s refunds as refund failed',
			( status ) => {
				const charge = {
					...paidCharge,
					refunded: false,
					amount_refunded: 300,
					refunds: {
						data: [
							{
								status,
								balance_transaction: { amount: -300 },
							},
						],
					},
				};

				expect( utils.getChargeStatus( charge ) ).toEqual(
					'refund_failed'
				);
			}
		);

		test.each( [ 'succeeded', 'future_status', undefined, null ] )(
			'should not identify a %s refund as refund failed',
			( status ) => {
				const charge = {
					...paidCharge,
					refunded: false,
					amount_refunded: 0,
					refunds: {
						data: [
							{
								status,
								balance_transaction: { amount: -300 },
							},
						],
					},
				};

				expect( utils.isChargeRefundFailed( charge ) ).toEqual( false );
				expect( utils.getChargeStatus( charge ) ).toEqual( 'paid' );
			}
		);

		test( 'should not identify an empty refund list as refund failed', () => {
			const charge = {
				...paidCharge,
				refunded: false,
				amount_refunded: 0,
				refunds: { data: [] },
			};

			expect( utils.isChargeRefundFailed( charge ) ).toEqual( false );
			expect( utils.getChargeStatus( charge ) ).toEqual( 'paid' );
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

		test( 'returns authorized when payment intent requires capture', () => {
			const pendingCharge = {
				status: 'pending',
				paid: false,
				captured: false,
			};
			expect(
				utils.getChargeStatus( pendingCharge, authorizedPaymentIntent )
			).toEqual( 'authorized' );
		} );

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

describe( 'Charge utilities / getDisputeOrdinals', () => {
	test( 'numbers disputes oldest-first regardless of array order', () => {
		const charge = {
			disputes: [
				{ id: 'dp_newer', created: 2000 },
				{ id: 'dp_older', created: 1000 },
			],
		};

		const { orderById, total } = utils.getDisputeOrdinals( charge );

		expect( orderById ).toEqual( { dp_older: 1, dp_newer: 2 } );
		expect( total ).toBe( 2 );
	} );

	test( 'reports total 1 for a single dispute', () => {
		const charge = {
			disputes: [ { id: 'dp_only', created: 1000 } ],
		};

		const { orderById, total } = utils.getDisputeOrdinals( charge );

		expect( orderById ).toEqual( { dp_only: 1 } );
		expect( total ).toBe( 1 );
	} );

	test( 'falls back to the singular dispute field', () => {
		const charge = { dispute: { id: 'dp_single', created: 1000 } };

		const { orderById, total } = utils.getDisputeOrdinals( charge );

		expect( orderById ).toEqual( { dp_single: 1 } );
		expect( total ).toBe( 1 );
	} );

	test( 'reports total 0 when there are no disputes', () => {
		const { orderById, total } = utils.getDisputeOrdinals( {} );

		expect( orderById ).toEqual( {} );
		expect( total ).toBe( 0 );
	} );
} );

describe( 'Charge utilities / getChargeAmounts', () => {
	test( 'basic charge', () => {
		const charge = {
			amount: 1800,
			currency: 'usd',
			application_fee_amount: 82,
			balance_transaction: {
				amount: 1800,
				currency: 'usd',
				fee: 82,
			},
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'usd',
			net: charge.amount - charge.application_fee_amount,
			fee: charge.application_fee_amount,
			refunded: 0,
		} );
	} );

	test( 'Multi-Currency basic charge', () => {
		const charge = {
			amount: 1800,
			currency: 'usd',
			application_fee_amount: 82,
			balance_transaction: {
				amount: 1482,
				fee: 68,
				currency: 'eur',
			},
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1482,
			currency: 'eur',
			net:
				charge.balance_transaction.amount -
				charge.balance_transaction.fee,
			fee: 68,
			refunded: 0,
		} );
	} );

	test( 'partial refund', () => {
		const charge = {
			amount: 1800,
			application_fee_amount: 82,
			amount_refunded: 300,
			balance_transaction: {
				amount: 1800,
				currency: 'usd',
				fee: 82,
			},
			refunds: {
				data: [
					{
						balance_transaction: {
							amount: -300,
						},
					},
				],
			},
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'usd',
			net:
				charge.amount -
				charge.application_fee_amount -
				charge.amount_refunded,
			fee: charge.application_fee_amount,
			refunded: charge.amount_refunded,
		} );
	} );

	test.each( [ 'failed', 'canceled' ] )(
		'does not deduct a %s refund from legacy amounts',
		( status ) => {
			const charge = {
				amount: 1800,
				currency: 'usd',
				application_fee_amount: 82,
				amount_refunded: 300,
				balance_transaction: {
					amount: 1800,
					currency: 'usd',
					fee: 82,
				},
				refunds: {
					data: [
						{
							status,
							balance_transaction: { amount: -300 },
						},
					],
				},
			};

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				amount: 1800,
				currency: 'usd',
				net: 1718,
				fee: 82,
				refunded: 0,
			} );
		}
	);

	test( 'deducts compatible refunds from mixed legacy amounts', () => {
		const charge = {
			amount: 1800,
			currency: 'usd',
			application_fee_amount: 82,
			amount_refunded: 900,
			balance_transaction: {
				amount: 1800,
				currency: 'usd',
				fee: 82,
			},
			refunds: {
				data: [
					{
						status: 'failed',
						balance_transaction: { amount: -100 },
					},
					{
						status: 'succeeded',
						balance_transaction: { amount: -200 },
					},
					{
						status: 'future_status',
						balance_transaction: { amount: -300 },
					},
					{
						balance_transaction: { amount: -400 },
					},
				],
			},
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'usd',
			net: 818,
			fee: 82,
			refunded: 900,
		} );
	} );

	test( 'Multi-Currency partial refund', () => {
		const refunds = [ 1000, 500 ];
		const charge = {
			amount: 1800,
			currency: 'usd',
			application_fee_amount: 82,
			amount_refunded: 1500,
			balance_transaction: {
				currency: 'eur',
				amount: 1482,
				fee: 68,
			},
			refunds: {
				data: refunds.map( ( refundedAmount ) => ( {
					balance_transaction: {
						amount: -refundedAmount,
					},
				} ) ),
			},
		};

		const expectedRefunds = refunds.reduce(
			( refund, acc ) => refund + acc,
			0
		);
		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1482,
			currency: 'eur',
			net:
				charge.balance_transaction.amount -
				charge.balance_transaction.fee -
				expectedRefunds,
			fee: 68,
			refunded: expectedRefunds,
		} );
	} );

	test( 'full refund', () => {
		const charge = {
			amount: 1800,
			application_fee_amount: 82,
			amount_refunded: 1800,
			balance_transaction: {
				amount: 1800,
				currency: 'usd',
				fee: 82,
			},
			refunds: {
				data: [
					{
						balance_transaction: {
							amount: -1800,
						},
					},
				],
			},
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'usd',
			net:
				charge.amount -
				charge.application_fee_amount -
				charge.amount_refunded,
			fee: charge.application_fee_amount,
			refunded: charge.amount_refunded,
		} );
	} );

	test( 'Multi-Currency full refund', () => {
		// Refund at higher rate
		const refunds = [ 1000, 800 ];
		const charge = {
			amount: 1800,
			currency: 'usd',
			application_fee_amount: 82,
			amount_refunded: 1800,
			balance_transaction: {
				currency: 'eur',
				amount: 1482,
				fee: 68,
			},
			refunds: {
				data: refunds.map( ( refundedAmount ) => ( {
					balance_transaction: {
						amount: -refundedAmount,
					},
				} ) ),
			},
		};

		const expectedRefunds = refunds.reduce(
			( refund, acc ) => refund + acc,
			0
		);
		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1482,
			currency: 'eur',
			net:
				charge.balance_transaction.amount -
				charge.balance_transaction.fee -
				expectedRefunds,
			fee: 68,
			refunded: expectedRefunds,
		} );
	} );

	test( 'full dispute', () => {
		const charge = {
			amount: 1800,
			application_fee_amount: 82,
			balance_transaction: {
				amount: 1800,
				currency: 'usd',
				fee: 82,
			},
			disputed: true,
			dispute: {
				amount: 1800,
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
			currency: 'usd',
			net: 0 - charge.application_fee_amount - 1500,
			fee: charge.application_fee_amount + 1500,
			refunded: charge.dispute.amount,
		} );
	} );

	test( 'legacy path folds every dispute in the disputes array', () => {
		const charge = {
			amount: 1800,
			application_fee_amount: 82,
			balance_transaction: {
				amount: 1800,
				currency: 'usd',
				fee: 82,
			},
			disputed: true,
			dispute: {
				amount: 1000,
				balance_transactions: [
					{
						amount: -1000,
						fee: 1500,
					},
				],
			},
			disputes: [
				{
					amount: 1000,
					balance_transactions: [
						{
							amount: -1000,
							fee: 1500,
						},
					],
				},
				{
					amount: 800,
					balance_transactions: [
						{
							amount: -800,
							fee: 1500,
						},
					],
				},
			],
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'usd',
			net: 1800 - 82 - 3000 - 1800,
			fee: 82 + 3000,
			refunded: 1800,
		} );
	} );

	test( 'reversed dispute', () => {
		const charge = {
			amount: 1800,
			application_fee_amount: 82,
			balance_transaction: {
				amount: 1800,
				currency: 'usd',
				fee: 82,
			},
			disputed: true,
			dispute: {
				amount: 1800,
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
			currency: 'usd',
			net: charge.amount - charge.application_fee_amount,
			fee: charge.application_fee_amount,
			refunded: 0,
		} );
	} );

	test( 'Multi-Currency full dispute', () => {
		const charge = {
			amount: 1800,
			currency: 'usd',
			application_fee_amount: 82,
			balance_transaction: {
				currency: 'eur',
				amount: 1482,
				fee: 68,
			},
			disputed: true,
			dispute: {
				amount: 1800,
				balance_transactions: [
					{
						amount: 1482,
						fee: 1500,
						currency: 'eur',
					},
				],
			},
		};

		const disputedAmount = -charge.dispute.balance_transactions[ 0 ].amount;
		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1482,
			currency: 'eur',
			net: charge.balance_transaction.amount - disputedAmount - 68 - 1500,
			fee: 68 + 1500,
			refunded: disputedAmount,
		} );
	} );

	test( 'inquiry', () => {
		const charge = {
			amount: 1800,
			application_fee_amount: 82,
			disputed: true,
			dispute: {
				amount: 1800,
				balance_transactions: [],
			},
			balance_transaction: {
				amount: 1800,
				currency: 'usd',
				fee: 82,
			},
		};

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			amount: 1800,
			currency: 'usd',
			net: charge.amount - charge.application_fee_amount,
			fee: charge.application_fee_amount,
			refunded: 0,
		} );
	} );
} );

jest.mock( '@wordpress/i18n', () => ( {
	__: jest.fn( ( text ) => text ),
} ) );

describe( 'Charge utilities / get channel string', () => {
	describe( 'getTransactionChannel', () => {
		test( 'should return "In-Person (POS)" for in_person_pos channel', () => {
			const result = utils.getTransactionChannel( 'in_person_pos' );
			expect( result ).toBe( 'In-Person (POS)' );
		} );

		test( 'should return "In-Person" for in_person channel', () => {
			const result = utils.getTransactionChannel( 'in_person' );
			expect( result ).toBe( 'In-Person' );
		} );

		test( 'should return "Online store" for online channel', () => {
			const result = utils.getTransactionChannel( 'online' );
			expect( result ).toBe( 'Online store' );
		} );

		test( 'should return "Online store" for null channel', () => {
			const result = utils.getTransactionChannel( null );
			expect( result ).toBe( 'Online store' );
		} );
	} );

	describe( 'getChargeChannel', () => {
		test( 'should return "In-Person (POS)" for card_present type with mobile_pos metadata', () => {
			const result = utils.getChargeChannel( 'card_present', {
				ipp_channel: 'mobile_pos',
			} );
			expect( result ).toBe( 'In-Person (POS)' );
		} );

		test( 'should return "In-Person" for card_present type with mobile_store_management metadata', () => {
			const result = utils.getChargeChannel( 'card_present', {
				ipp_channel: 'mobile_store_management',
			} );
			expect( result ).toBe( 'In-Person' );
		} );

		test( 'should return "In-Person" for card_present type with null ipp_channel metadata', () => {
			const result = utils.getChargeChannel( 'card_present', {} );
			expect( result ).toBe( 'In-Person' );
		} );

		test( 'should return "Online store" for online type', () => {
			const result = utils.getChargeChannel( 'online', {
				ipp_channel: 'mobile_pos',
			} );
			expect( result ).toBe( 'Online store' );
		} );
	} );

	describe( 'getBankName', () => {
		test( 'should return "Affirm" for affirm type', () => {
			const result = utils.getBankName( {
				payment_method_details: { type: 'affirm' },
			} );
			expect( result ).toBe( 'Affirm' );
		} );

		test( 'should return "Afterpay / Clearpay" for afterpay_clearpay type', () => {
			const result = utils.getBankName( {
				payment_method_details: { type: 'afterpay_clearpay' },
			} );
			expect( result ).toBe( 'Afterpay / Clearpay' );
		} );

		test( 'should return "Klarna" for klarna type', () => {
			const result = utils.getBankName( {
				payment_method_details: { type: 'klarna' },
			} );
			expect( result ).toBe( 'Klarna' );
		} );

		test( 'should return null for unknown type', () => {
			const result = utils.getBankName( {
				payment_method_details: { type: 'unknown' },
			} );
			expect( result ).toBe( null );
		} );

		test( 'should return the issuer for card type', () => {
			const result = utils.getBankName( {
				payment_method_details: {
					type: 'card',
					card: { issuer: 'Chase Bank' },
				},
			} );
			expect( result ).toBe( 'Chase Bank' );
		} );

		test( 'should return null for card type with no issuer', () => {
			const result = utils.getBankName( {
				payment_method_details: {
					type: 'card',
					card: { issuer: null },
				},
			} );
			expect( result ).toBe( null );
		} );
	} );
} );

// €26.00 authorized, €13.00 captured, settled in USD. Stripe releases the
// other €13.00 as a refund whose balance transaction is a
// `partial_capture_reversal`.
describe( 'Charge utilities / partial capture', () => {
	const AUTHORIZED_AMOUNT = 2600;
	const CAPTURED_AMOUNT = 1300;
	const RELEASED_TRANSACTION_ID = 'txn_released';
	const releasedRefund = {
		amount: 1300,
		status: 'succeeded',
		balance_transaction: {
			id: RELEASED_TRANSACTION_ID,
			amount: -1455,
			currency: 'usd',
			reporting_category: 'partial_capture_reversal',
		},
	};
	const merchantRefund = {
		amount: 500,
		status: 'succeeded',
		balance_transaction: {
			id: 'txn_refund',
			amount: -560,
			currency: 'usd',
			reporting_category: 'refund',
		},
	};
	const buildCharge = ( {
		refunds = [ releasedRefund ],
		...overrides
	} ) => ( {
		...paidCharge,
		amount: AUTHORIZED_AMOUNT,
		amount_captured: CAPTURED_AMOUNT,
		amount_refunded: AUTHORIZED_AMOUNT - CAPTURED_AMOUNT,
		refunded: false,
		currency: 'eur',
		application_fee_amount: 77,
		balance_transaction: {
			id: 'txn_charge',
			amount: 2910,
			currency: 'usd',
			fee: 86,
		},
		refunds: { data: refunds },
		...overrides,
	} );
	const withEnvelope = ( charge, { gross, net, fee = 86 } ) => ( {
		...charge,
		fee_breakdown_v1: {
			rows: [],
			totals: {
				fee: { amount: fee, currency: 'usd' },
				tax: { amount: 0, currency: 'usd' },
				fee_plus_tax: { amount: fee, currency: 'usd' },
				gross: { amount: gross, currency: 'usd' },
				net: { amount: net, currency: 'usd' },
			},
		},
	} );

	test( 'is paid, not partially refunded', () => {
		const charge = buildCharge( {} );

		expect( utils.getUncapturedAmount( charge ) ).toBe( 1300 );
		expect( utils.getChargeRefundedAmount( charge ) ).toBe( 0 );
		expect( utils.isChargeRefunded( charge ) ).toBe( false );
		expect( utils.isChargePartiallyRefunded( charge ) ).toBe( false );
		expect( utils.getChargeStatus( charge ) ).toBe( 'paid' );
	} );

	test( 'is paid when the refund list is missing', () => {
		const charge = { ...buildCharge( {} ), refunds: null };

		expect( utils.getChargeStatus( charge ) ).toBe( 'paid' );
	} );

	// Known limitation: without the reversal in `refunds`, the settlement
	// amount and net stay at the authorized values.
	test( 'reports the authorized settlement amounts when the reversal is not listed', () => {
		const charge = { ...buildCharge( {} ), refunds: null };

		expect( utils.getChargeAmounts( charge ) ).toEqual( {
			currency: 'usd',
			amount: 2910,
			fee: 86,
			refunded: 0,
			net: 2824,
		} );
	} );

	test.each( [
		[ 'nothing was refunded', AUTHORIZED_AMOUNT - CAPTURED_AMOUNT, 1300 ],
		[ 'part was refunded', AUTHORIZED_AMOUNT - CAPTURED_AMOUNT + 500, 800 ],
		[ 'everything was refunded', AUTHORIZED_AMOUNT, 0 ],
	] )(
		'leaves the captured amount less refunds to refund when %s',
		( _, amountRefunded, expected ) => {
			const charge = buildCharge( { amount_refunded: amountRefunded } );

			expect( utils.getRefundableAmount( charge ) ).toBe( expected );
		}
	);

	test( 'is paid when the released amount is not reported as refunded', () => {
		const charge = buildCharge( { amount_refunded: 0, refunds: [] } );

		expect( utils.getChargeRefundedAmount( charge ) ).toBe( 0 );
		expect( utils.getChargeStatus( charge ) ).toBe( 'paid' );
	} );

	test( 'is partially refunded after a merchant refund', () => {
		const charge = buildCharge( {
			amount_refunded: AUTHORIZED_AMOUNT - CAPTURED_AMOUNT + 500,
			refunds: [ merchantRefund, releasedRefund ],
		} );

		expect( utils.getChargeRefundedAmount( charge ) ).toBe( 500 );
		expect( utils.getChargeStatus( charge ) ).toBe( 'refunded_partial' );
	} );

	test( 'is fully refunded after the captured amount is refunded', () => {
		const charge = buildCharge( {
			amount_refunded: AUTHORIZED_AMOUNT,
			refunded: true,
			refunds: [
				{
					...merchantRefund,
					amount: 1300,
					balance_transaction: {
						...merchantRefund.balance_transaction,
						amount: -1455,
					},
				},
				releasedRefund,
			],
		} );

		expect( utils.getChargeRefundedAmount( charge ) ).toBe( 1300 );
		expect( utils.getChargeStatus( charge ) ).toBe( 'refunded_full' );
	} );

	test( 'reports a failed merchant refund as refund failed', () => {
		const charge = buildCharge( {
			refunds: [
				{ ...merchantRefund, status: 'failed' },
				releasedRefund,
			],
		} );

		expect( utils.isChargeRefundFailed( charge ) ).toBe( true );
		expect( utils.getChargeStatus( charge ) ).toBe( 'refund_failed' );
	} );

	test( 'does not treat an uncaptured authorization as partially captured', () => {
		const charge = buildCharge( {
			captured: false,
			amount_captured: 0,
			amount_refunded: 0,
			refunds: [],
		} );

		expect( utils.getUncapturedAmount( charge ) ).toBe( 0 );
	} );

	test( 'lists the reversal balance transactions', () => {
		const charge = buildCharge( {
			refunds: [ merchantRefund, releasedRefund ],
		} );

		expect(
			utils.getPartialCaptureReversalTransactionIds( charge )
		).toEqual( [ 'txn_released' ] );
	} );

	describe( 'getChargeAmounts', () => {
		test( 'reports the captured amount without a refund', () => {
			expect( utils.getChargeAmounts( buildCharge( {} ) ) ).toEqual( {
				currency: 'usd',
				amount: 1455,
				fee: 86,
				refunded: 0,
				net: 1369,
			} );
		} );

		test( 'keeps merchant refunds as refunded', () => {
			const charge = buildCharge( {
				amount_refunded: AUTHORIZED_AMOUNT - CAPTURED_AMOUNT + 500,
				refunds: [ merchantRefund, releasedRefund ],
			} );

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				currency: 'usd',
				amount: 1455,
				fee: 86,
				refunded: 560,
				net: 809,
			} );
		} );

		test( 'falls back to the charge amounts without a balance transaction', () => {
			const charge = buildCharge( {
				balance_transaction: null,
				refunds: [],
			} );

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				currency: 'eur',
				amount: 1300,
				fee: 77,
				refunded: 0,
				net: 1223,
			} );
		} );

		test( 'reports the captured amount from the fee breakdown', () => {
			const charge = withEnvelope( buildCharge( {} ), {
				gross: 2910,
				net: 1369,
			} );

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				currency: 'usd',
				amount: 1455,
				fee: 86,
				refunded: 0,
				net: 1369,
			} );
		} );

		test( 'keeps merchant refunds in the fee breakdown', () => {
			const charge = withEnvelope(
				buildCharge( {
					amount_refunded: AUTHORIZED_AMOUNT - CAPTURED_AMOUNT + 500,
					refunds: [ merchantRefund, releasedRefund ],
				} ),
				{ gross: 2910, net: 809 }
			);

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				currency: 'usd',
				amount: 1455,
				fee: 86,
				refunded: 560,
				net: 809,
			} );
		} );

		test( 'reports a full refund of the captured amount', () => {
			const charge = withEnvelope(
				buildCharge( {
					amount_refunded: AUTHORIZED_AMOUNT,
					refunded: true,
					refunds: [
						{
							...merchantRefund,
							amount: CAPTURED_AMOUNT,
							balance_transaction: {
								...merchantRefund.balance_transaction,
								amount: -1455,
							},
						},
						releasedRefund,
					],
				} ),
				{ gross: 2910, net: -86 }
			);

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				currency: 'usd',
				amount: 1455,
				fee: 86,
				refunded: 1455,
				net: -86,
			} );
		} );

		test( 'keeps refunds of a captured gross as refunds', () => {
			const charge = withEnvelope(
				buildCharge( {
					amount_refunded: AUTHORIZED_AMOUNT,
					refunded: true,
					refunds: [
						{
							...merchantRefund,
							amount: CAPTURED_AMOUNT,
							balance_transaction: {
								...merchantRefund.balance_transaction,
								amount: -1455,
							},
						},
						releasedRefund,
					],
				} ),
				{ gross: 1455, net: -86 }
			);

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				currency: 'usd',
				amount: 1455,
				fee: 86,
				refunded: 1455,
				net: -86,
			} );
		} );

		test( 'reports nothing refunded when withdrawals do not cover the reversal', () => {
			const charge = withEnvelope( buildCharge( {} ), {
				gross: 2910,
				net: 1500,
			} );

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				currency: 'usd',
				amount: 1455,
				fee: 86,
				refunded: 0,
				net: 1500,
			} );
		} );

		// Known limitation: without the reversal in `refunds`, its settlement
		// amount is unknown, so the gross and refunded amounts stay at the
		// authorized values. The server's net is still right.
		test( 'reports the authorized amounts when the reversal is not listed', () => {
			const charge = withEnvelope(
				{ ...buildCharge( {} ), refunds: null },
				{
					gross: 2910,
					net: 1369,
				}
			);

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				currency: 'usd',
				amount: 2910,
				fee: 86,
				refunded: 1455,
				net: 1369,
			} );
		} );

		test( 'does not deduct the released amount twice from a captured gross', () => {
			const charge = withEnvelope( buildCharge( {} ), {
				gross: 1455,
				net: 1369,
			} );

			expect( utils.getChargeAmounts( charge ) ).toEqual( {
				currency: 'usd',
				amount: 1455,
				fee: 86,
				refunded: 0,
				net: 1369,
			} );
		} );
	} );
} );
