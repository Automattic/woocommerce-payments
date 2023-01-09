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
	formatPillFeesDescription,
	getCurrentBaseFee,
	getTooltipAndPillBaseFee,
} from '../account-fees';
import { formatCurrency } from '../currency';
import { BaseFee, DiscountFee, FeeStructure } from 'wcpay/types/fees';

jest.mock( '../currency', () => ( {
	formatCurrency: jest.fn( ( amount: number ): string => {
		return sprintf( '$%.2f', amount / 100 );
	} ),
} ) );

const mockAccountFees = (
	base: BaseFee,
	discounts = [] as Array< any >
): FeeStructure => {
	const result = {
		base: base,
		discount: [],
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
	} as FeeStructure;

	for ( const index in discounts ) {
		const providedDiscount = discounts[ index ];
		const constructedDiscount = {
			discount: providedDiscount.discount || 0,
			fixed_rate: providedDiscount.fixed_rate || 0,
			percentage_rate: providedDiscount.percentage_rate || 0,
			end_time: providedDiscount.end_time || null,
			volume_allowance: providedDiscount.volume_allowance || null,
			volume_currency: providedDiscount.volume_currency || null,
			current_volume: providedDiscount.current_volume || null,
			currency: 'USD',
		} as DiscountFee;
		result.discount.push( constructedDiscount );
	}

	return result;
};

describe( 'Account fees utility functions', () => {
	describe( 'getCurrentBaseFee()', () => {
		it( 'returns first discount regardless of amount', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[ { discount: 0.1 }, { discount: 0.2 } ]
			);

			expect( getCurrentBaseFee( accountFees ) ).toEqual(
				accountFees.discount[ 0 ]
			);
		} );

		it( 'returns base if no discounts are present', () => {
			const accountFees = mockAccountFees( {
				percentage_rate: 0.123,
				fixed_rate: 456.78,
				currency: 'USD',
			} );
			expect( getCurrentBaseFee( accountFees ) ).toEqual(
				accountFees.base
			);
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
				[ { discount: 0.1 }, { discount: 0.2 } ]
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
				[ { percentage_rate: 12.3, fixed_rate: 4567.8 } ]
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
				[ { discount: 0.1 } ]
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
				[ { discount: 0.1 } ]
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

		it( 'only describes discount if it is different than base fee', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[ { percentage_rate: 0.123, fixed_rate: 456.78 } ]
			);

			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				'12.3% + $4.57 per transaction'
			);
		} );

		it( 'uses custom formats', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[ { discount: 0.1 }, { discount: 0.2 } ]
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
				[ { discount: 0.1 } ]
			);

			formatAccountFeesDescription( accountFees );

			// Current fee description
			expect( formatCurrency ).toHaveBeenCalledWith(
				accountFees.base.fixed_rate * 0.9,
				accountFees.base.currency
			);
		} );
	} );

	describe( 'formatMethodFeesDescription()', () => {
		it( 'returns fees in short format', () => {
			const methodFees = mockAccountFees( {
				percentage_rate: 0.123,
				fixed_rate: 456.78,
				currency: 'USD',
			} );

			expect( formatMethodFeesDescription( methodFees ) ).toEqual(
				'From 12.3% + $4.57'
			);
		} );

		it( 'returns discounted fees in short format (no discount % or base fee)', () => {
			const methodFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[ { discount: 0.1 }, { discount: 0.2 } ]
			);

			expect( formatMethodFeesDescription( methodFees ) ).toEqual(
				<>From 11.07% + $4.11</>
			);
		} );

		it( 'returns "missing fees" if no fees are supplied', () => {
			expect( formatMethodFeesDescription( undefined ) ).toEqual(
				'missing fees'
			);
		} );
	} );

	describe( 'getTooltipAndPillBaseFee()', () => {
		it( 'returns base fee if there are no discounts', () => {
			const accountFees = mockAccountFees( {
				percentage_rate: 0.123,
				fixed_rate: 456.78,
				currency: 'USD',
			} );

			expect( getTooltipAndPillBaseFee( accountFees ) ).toEqual(
				accountFees.base
			);
		} );

		it( 'returns base fee percentage rate and base fixed rate if there is a promo discount ', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[ { discount: 0.1 } ]
			);

			expect(
				getTooltipAndPillBaseFee( accountFees ).percentage_rate
			).toEqual( 0.123 );
			expect(
				getTooltipAndPillBaseFee( accountFees ).fixed_rate
			).toEqual( 456.78 );
		} );

		it( 'returns custom fee percentage rate if there is a custom rate present ', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[ { percentage_rate: 0.11 } ]
			);

			expect(
				getTooltipAndPillBaseFee( accountFees ).percentage_rate
			).toEqual( 0.11 );
		} );
	} );

	describe( 'formatPillFeesDescription()', () => {
		it( 'returns pill description with base rates if there are no discounts', () => {
			const accountFees = mockAccountFees( {
				percentage_rate: 0.123,
				fixed_rate: 456.78,
				currency: 'USD',
			} );

			expect( formatPillFeesDescription( accountFees ) ).toEqual(
				'From 12.3% + $4.57'
			);
		} );

		it( 'returns pill description with base rates if there is a promo discount', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[ { discount: 0.1 } ]
			);

			expect( formatPillFeesDescription( accountFees ) ).toEqual(
				'From 12.3% + $4.57'
			);
		} );

		it( 'returns pill description with custom rates if there is a custom fee present', () => {
			const accountFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[ { percentage_rate: 0.11, fixed_rate: 375.77 } ]
			);

			expect( formatPillFeesDescription( accountFees ) ).toEqual(
				'From 11% + $3.76'
			);
		} );
	} );
} );
