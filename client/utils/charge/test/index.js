/** @format */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import * as utils from '../';

const paidCharge = { status: 'succeeded', paid: true, captured: true };
const failedCharge = { status: 'failed', paid: false, captured: false };
const authorizedCharge = { status: 'succeeded', paid: true, captured: false };
const disputedCharge = { disputed: true };
// eslint-disable-next-line camelcase
const fullyRefundedCharge = { amount: 1500, refunded: true, amount_refunded: 1500 };
// eslint-disable-next-line camelcase
const partiallyRefundedCharge = { amount: 1500, refunded: false, amount_refunded: 1200 };

describe( 'Charge utilities', () => {
	test( 'should identify a captured successful charge as successful', () => {
		expect( utils.isChargeSuccessful( paidCharge ) ).toEqual( true );
	} );

	test( 'should identify a not captured successful charge as successful', () => {
		expect( utils.isChargeSuccessful( authorizedCharge ) ).toEqual( true );
	} );

	test( 'should identify a captured successful charge as captured', () => {
		expect( utils.isChargeCaptured( paidCharge ) ).toEqual( true );
	} );

	test( 'should not identify a not captured successful charge as captured', () => {
		expect( utils.isChargeCaptured( authorizedCharge ) ).toEqual( false );
	} );

	test( 'should not identify a failed charge as successful', () => {
		expect( utils.isChargeSuccessful( failedCharge ) ).toEqual( false );
	} );

	test( 'should identify a failed charge as failed', () => {
		expect( utils.isChargeFailed( failedCharge ) ).toEqual( true );
	} );

	test( 'should not identify a successful charge as failed', () => {
		expect( utils.isChargeFailed( paidCharge ) ).toEqual( false );
	} );

	test( 'should identify a disputed charge as disputed', () => {
		expect( utils.isChargeDisputed( disputedCharge ) ).toEqual( true );
	} );

	test( 'should identify a fully refunded charge as fully refunded', () => {
		expect( utils.isChargeFullyRefunded( fullyRefundedCharge ) ).toEqual( true );
	} );

	test( 'should not identify a partially refunded charge as fully refunded', () => {
		expect( utils.isChargeFullyRefunded( partiallyRefundedCharge ) ).toEqual( false );
	} );

	test( 'should not identify a successful charge as fully refunded', () => {
		expect( utils.isChargeFullyRefunded( paidCharge ) ).toEqual( false );
	} );

	test( 'should identify a partially refunded charge as partially refunded', () => {
		expect( utils.isChargePartiallyRefunded( partiallyRefundedCharge ) ).toEqual( true );
	} );

	test( 'should not identify a fully refunded charge as partilly refunded', () => {
		expect( utils.isChargePartiallyRefunded( fullyRefundedCharge ) ).toEqual( false );
	} );

	test( 'should not identify a successful charge as partilly refunded', () => {
		expect( utils.isChargePartiallyRefunded( paidCharge ) ).toEqual( false );
	} );

	test( 'should return status paid for captured successful charges', () => {
		expect( utils.getChargeStatus( paidCharge ) ).toEqual( 'paid' );
	} );

	test( 'should return status authorized for not captured successful charges', () => {
		expect( utils.getChargeStatus( authorizedCharge ) ).toEqual( 'authorized' );
	} );

	test( 'should return status failed for failed charges', () => {
		expect( utils.getChargeStatus( failedCharge ) ).toEqual( 'failed' );
	} );

	test( 'should return status disputed for disputed charges', () => {
		expect( utils.getChargeStatus( disputedCharge ) ).toEqual( 'disputed' );
	} );

	test( 'should return status fully-refunded for fully refunded charges', () => {
		expect( utils.getChargeStatus( fullyRefundedCharge ) ).toEqual( 'fully-refunded' );
	} );

	test( 'should return status partially-refunded for partially refunded charges', () => {
		expect( utils.getChargeStatus( partiallyRefundedCharge ) ).toEqual( 'partially-refunded' );
	} );
} );
