/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies
 */
import {
	formatAccountFeesDescription,
	formatMethodFeesDescription,
	getCurrentFee,
} from '../account-fees';
import { formatCurrency } from '../currency';
import { BaseFee, DiscountFee } from 'wcpay/types/fees';

jest.mock( '../currency', () => ( {
	formatCurrency: jest.fn( ( amount: number ) => {
		return sprintf( '$%.2f', amount / 100 );
	} ),
} ) );

const mockAccountFees = (
	base: BaseFee,
	discount = [] as Array< DiscountFee >
) => ( {
	base,
	discount,
	additional: {
		percentage_rate: 0,
		fixed_rate: 0,
		currency: 'USD',
	},
	fx: {
		percentage_rate: 0,
		fixed_rate: 0,
		currency: 'USD',
	},
} );

describe( 'Account fees utility functions', () => {
	describe( 'getCurrentFee()', () => {
		it( 'returns first discount regardless of amount', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[
					{
						discount: 0.1,
						fixed_rate: 456.78,
						percentage_rate: 0.123,
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
						currency: 'USD',
					},
					{
						discount: 0.2,
						fixed_rate: 456.78,
						percentage_rate: 0.123,
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
						currency: 'USD',
					},
				]
			);

			expect( getCurrentFee( accountFees ) ).toEqual(
				accountFees.discount[ 0 ]
			);
		} );

		it( 'returns base if no discounts are present', () => {
			const accountFees = mockAccountFees( {
				percentage_rate: 0.123,
				fixed_rate: 456.78,
				currency: 'USD',
			} );
			expect( getCurrentFee( accountFees ) ).toEqual( accountFees.base );
		} );
	} );

	describe( 'formatAccountFeesDescription()', () => {
		it( 'uses default formats if none specified', () => {
			const accountFees = mockAccountFees( {
				percentage_rate: 0.123,
				fixed_rate: 456.78,
				currency: 'USD',
			} );
			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				'12.3% + $4.57 per transaction'
			);
		} );

		it( 'uses first discount regardless of discounted amount', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[
					{
						discount: 0.1,
						fixed_rate: 456.78,
						percentage_rate: 0.123,
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
						currency: 'USD',
					},
					{
						discount: 0.2,
						fixed_rate: 456.78,
						percentage_rate: 0.123,
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
						currency: 'USD',
					},
				]
			);

			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				<>
					<s>12.3% + $4.57 per transaction</s> 11.07% + $4.11 per
					transaction (10% discount)
				</>
			);
		} );

		it( 'uses percentage and fixed rate from discount object if no discount amount is available', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[
					{
						percentage_rate: 12.3,
						fixed_rate: 4567.8,
						currency: 'USD',
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
					},
				]
			);

			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				<>
					<s>12.3% + $4.57 per transaction</s> 1230% + $45.68 per
					transaction
				</>
			);
		} );

		it( 'uses discount amount if both it and percentage and fixed rate are available', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[
					{
						discount: 0.1,
						percentage_rate: 12.3,
						fixed_rate: 4567.8,
						currency: 'USD',
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
					},
				]
			);

			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				<>
					<s>12.3% + $4.57 per transaction</s> 11.07% + $4.11 per
					transaction (10% discount)
				</>
			);
		} );

		it( 'does not return discount percentage if discount format is empty', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[
					{
						discount: 0.1,
						percentage_rate: 12.3,
						fixed_rate: 4567.8,
						currency: 'USD',
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
					},
				]
			);

			expect(
				formatAccountFeesDescription( accountFees, { discount: '' } )
			).toEqual(
				<>
					<s>12.3% + $4.57 per transaction</s> 11.07% + $4.11 per
					transaction
				</>
			);
		} );

		it( 'uses custom formats', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[
					{
						discount: 0.1,
						fixed_rate: 456.78,
						percentage_rate: 0.123,
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
						currency: 'USD',
					},
					{
						discount: 0.2,
						fixed_rate: 456.78,
						percentage_rate: 0.123,
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
						currency: 'USD',
					},
				]
			);

			expect(
				formatAccountFeesDescription( accountFees, {
					fee: 'perc: %1$f fixed: %2$s',
					discount: 'disc perc: %f',
				} )
			).toEqual(
				<>
					<s>perc: 12.3 fixed: $4.57</s> perc: 11.07 fixed: $4.11 disc
					perc: 10
				</>
			);
		} );

		it( 'formats currencies using formatCurrency()', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[
					{
						discount: 0.1,
						percentage_rate: 0.123,
						fixed_rate: 456.78,
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
						currency: 'USD',
					},
				]
			);

			formatAccountFeesDescription( accountFees );

			// Base fee description
			expect( formatCurrency ).toHaveBeenCalledWith(
				accountFees.base.fixed_rate,
				accountFees.base.currency
			);

			// Current fee description
			expect( formatCurrency ).toHaveBeenCalledWith(
				accountFees.base.fixed_rate * 0.9,
				accountFees.base.currency
			);
		} );
	} );

	describe( 'formatMethodFeesDescription()', () => {
		it( 'returns fees in short format', () => {
			const accountFees = mockAccountFees( {
				percentage_rate: 0.123,
				fixed_rate: 456.78,
				currency: 'USD',
			} );

			expect( formatMethodFeesDescription( accountFees ) ).toEqual(
				'12.3% + $4.57'
			);
		} );

		it( 'returns discounted fees in short format (no discount % or base fee)', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[
					{
						discount: 0.1,
						fixed_rate: 456.78,
						percentage_rate: 0.123,
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
						currency: 'USD',
					},
					{
						discount: 0.2,
						fixed_rate: 456.78,
						percentage_rate: 0.123,
						end_time: null,
						volume_allowance: null,
						volume_currency: null,
						current_volume: null,
						currency: 'USD',
					},
				]
			);

			expect( formatMethodFeesDescription( accountFees ) ).toEqual(
				<>11.07% + $4.11</>
			);
		} );

		it( 'returns "missing fees" if no fees are supplied', () => {
			expect( formatMethodFeesDescription( undefined ) ).toEqual(
				'missing fees'
			);
		} );
	} );
} );
