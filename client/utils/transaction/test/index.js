/** @format */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import * as utils from '../';

const paidTransaction = { type: 'charge', source: { status: 'succeeded', paid: true, captured: true } };
const failedTransaction = { type: 'charge', source: { status: 'failed', paid: false, captured: false } };
const authorizedTransaction = { type: 'charge', source: { status: 'succeeded', paid: true, captured: false } };
const disputedTransaction = { type: 'charge', source: { disputed: true } };
// eslint-disable-next-line camelcase
const fullyRefundedTransaction = { amount: 1500, source: { refunded: true, amount_refunded: 1500 } };
// eslint-disable-next-line camelcase
const partiallyRefundedTransaction = { amount: 1500, source: { refunded: false, amount_refunded: 1200 } };

describe( 'Transaction utilities', () => {
	test( 'should get the charge object for charge transactions', () => {
		const charge = { id: 'ch_281' };
		const transaction = { type: 'charge', source: charge };
		expect( utils.getCharge( transaction ) ).toStrictEqual( charge );
	} );

	test( 'should get the charge object for refund transactions', () => {
		const charge = { id: 'ch_281' };
		const transaction = { type: 'refund', source: { charge } };
		expect( utils.getCharge( transaction ) ).toStrictEqual( charge );
	} );

	test( 'should identify a captured successful transaction as successful', () => {
		expect( utils.isTransactionSuccessful( paidTransaction ) ).toEqual( true );
	} );

	test( 'should identify a not captured successful transaction as successful', () => {
		expect( utils.isTransactionSuccessful( authorizedTransaction ) ).toEqual( true );
	} );

	test( 'should identify a captured successful transaction as captured', () => {
		expect( utils.isTransactionCaptured( paidTransaction ) ).toEqual( true );
	} );

	test( 'should not identify a not captured successful transaction as captured', () => {
		expect( utils.isTransactionCaptured( authorizedTransaction ) ).toEqual( false );
	} );

	test( 'should not identify a failed transaction as successful', () => {
		expect( utils.isTransactionSuccessful( failedTransaction ) ).toEqual( false );
	} );

	test( 'should identify a failed transaction as failed', () => {
		expect( utils.isTransactionFailed( failedTransaction ) ).toEqual( true );
	} );

	test( 'should not identify a successful transaction as failed', () => {
		expect( utils.isTransactionFailed( paidTransaction ) ).toEqual( false );
	} );

	test( 'should identify a disputed transaction as disputed', () => {
		expect( utils.isTransactionDisputed( disputedTransaction ) ).toEqual( true );
	} );

	test( 'should identify a fully refunded transaction as fully refunded', () => {
		expect( utils.isTransactionFullyRefunded( fullyRefundedTransaction ) ).toEqual( true );
	} );

	test( 'should not identify a partially refunded transaction as fully refunded', () => {
		expect( utils.isTransactionFullyRefunded( partiallyRefundedTransaction ) ).toEqual( false );
	} );

	test( 'should not identify a successful transaction as fully refunded', () => {
		expect( utils.isTransactionFullyRefunded( paidTransaction ) ).toEqual( false );
	} );

	test( 'should identify a partially refunded transaction as partially refunded', () => {
		expect( utils.isTransactionPartiallyRefunded( partiallyRefundedTransaction ) ).toEqual( true );
	} );

	test( 'should not identify a fully refunded transaction as partilly refunded', () => {
		expect( utils.isTransactionPartiallyRefunded( fullyRefundedTransaction ) ).toEqual( false );
	} );

	test( 'should not identify a successful transaction as partilly refunded', () => {
		expect( utils.isTransactionPartiallyRefunded( paidTransaction ) ).toEqual( false );
	} );

	test( 'should return status paid for captured successful transactions', () => {
		expect( utils.getTransactionStatus( paidTransaction ) ).toEqual( 'paid' );
	} );

	test( 'should return status authorized for not captured successful transactions', () => {
		expect( utils.getTransactionStatus( authorizedTransaction ) ).toEqual( 'authorized' );
	} );

	test( 'should return status failed for failed transactions', () => {
		expect( utils.getTransactionStatus( failedTransaction ) ).toEqual( 'failed' );
	} );

	test( 'should return status disputed for disputed transactions', () => {
		expect( utils.getTransactionStatus( disputedTransaction ) ).toEqual( 'disputed' );
	} );

	test( 'should return status fully-refunded for fully refunded transactions', () => {
		expect( utils.getTransactionStatus( fullyRefundedTransaction ) ).toEqual( 'fully-refunded' );
	} );

	test( 'should return status partially-refunded for partially refunded transactions', () => {
		expect( utils.getTransactionStatus( partiallyRefundedTransaction ) ).toEqual( 'partially-refunded' );
	} );
} );
