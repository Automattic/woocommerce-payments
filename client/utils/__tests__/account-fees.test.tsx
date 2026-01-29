/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import {
	formatAccountFeesDescription,
	formatMethodFeesDescription,
	formatMethodFeesTooltip,
	getCurrentBaseFee,
	getDiscountBadgeText,
	getDiscountTooltipText,
} from '../account-fees';
import { formatCurrency } from 'multi-currency/interface/functions';
import { BaseFee, DiscountFee, FeeStructure } from 'wcpay/types/fees';

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatCurrency: jest.fn( ( amount: number ): string => {
		return sprintf( '$%.2f', amount / 100 );
	} ),
} ) );

declare const global: {
	wcpaySettings: {
		connect: {
			country: string;
		};
		dateFormat?: string;
		timeFormat?: string;
	};
};

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

	describe( 'formatMethodFeesTooltip()', () => {
		beforeAll( () => {
			global.wcpaySettings = { connect: { country: 'US' } };
		} );
		afterAll( () => {
			global.wcpaySettings = { connect: { country: '' } };
		} );

		it( 'displays base percentage and fixed fee - no custom fee nor discount', () => {
			const methodFees = mockAccountFees( {
				percentage_rate: 0.123,
				fixed_rate: 456.78,
				currency: 'USD',
			} );

			methodFees.additional = {
				percentage_rate: 0.01,
				fixed_rate: 0,
				currency: 'USD',
			};

			methodFees.fx = {
				percentage_rate: 0.01,
				fixed_rate: 0,
				currency: 'USD',
			};

			const { container } = render(
				formatMethodFeesTooltip( methodFees )
			);

			expect( container ).toMatchSnapshot();
		} );

		it( 'displays base fee, when only promo discount without percentage or fixed', () => {
			const methodFees = mockAccountFees(
				{
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				[ { discount: 0.2 } ]
			);

			methodFees.additional = {
				percentage_rate: 0.01,
				fixed_rate: 0,
				currency: 'USD',
			};

			methodFees.fx = {
				percentage_rate: 0.01,
				fixed_rate: 0,
				currency: 'USD',
			};

			const { container } = render(
				formatMethodFeesTooltip( methodFees )
			);

			expect( container ).toMatchSnapshot();
		} );
	} );

	describe( 'getDiscountBadgeText()', () => {
		beforeAll( () => {
			global.wcpaySettings = {
				...global.wcpaySettings,
				dateFormat: 'M j, Y',
				timeFormat: 'g:i a',
			};
		} );

		it( 'returns discount percentage with end date when end_time is provided', () => {
			const discountFee: DiscountFee = {
				discount: 0.5,
				end_time: '2026-02-27 04:20:49',
				volume_allowance: null,
				volume_currency: null,
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			const result = getDiscountBadgeText( discountFee );

			expect( result ).toContain( '50%' );
			expect( result ).toContain( 'off fees through' );
		} );

		it( 'returns discount percentage without date when end_time is null', () => {
			const discountFee: DiscountFee = {
				discount: 0.25,
				end_time: null,
				volume_allowance: null,
				volume_currency: null,
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			const result = getDiscountBadgeText( discountFee );

			expect( result ).toBe( '25% off fees' );
		} );

		it( 'returns empty string for zero discount', () => {
			const discountFee: DiscountFee = {
				discount: 0,
				end_time: null,
				volume_allowance: null,
				volume_currency: null,
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			const result = getDiscountBadgeText( discountFee );

			expect( result ).toBe( '' );
		} );

		it( 'returns empty string for undefined discount', () => {
			const discountFee: DiscountFee = {
				end_time: null,
				volume_allowance: null,
				volume_currency: null,
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			const result = getDiscountBadgeText( discountFee );

			expect( result ).toBe( '' );
		} );
	} );

	describe( 'getDiscountTooltipText()', () => {
		beforeAll( () => {
			global.wcpaySettings = {
				...global.wcpaySettings,
				dateFormat: 'M j, Y',
				timeFormat: 'g:i a',
			};
		} );

		it( 'returns text with volume allowance and end time', () => {
			const discountFee: DiscountFee = {
				discount: 0.5,
				end_time: '2026-02-27 04:20:49',
				volume_allowance: 100000,
				volume_currency: 'usd',
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			const result = getDiscountTooltipText( discountFee );

			expect( result ).toContain( '50%' );
			expect( result ).toContain( 'first' );
			expect( result ).toContain( 'total payment volume' );
			expect( result ).toContain( 'or through' );
		} );

		it( 'returns text with only volume allowance when end_time is null', () => {
			const discountFee: DiscountFee = {
				discount: 0.3,
				end_time: null,
				volume_allowance: 50000,
				volume_currency: 'usd',
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			const result = getDiscountTooltipText( discountFee );

			expect( result ).toContain( '30%' );
			expect( result ).toContain( 'first' );
			expect( result ).toContain( 'total payment volume' );
			expect( result ).not.toContain( 'through' );
		} );

		it( 'returns text with only end time when volume_allowance is null', () => {
			const discountFee: DiscountFee = {
				discount: 0.2,
				end_time: '2026-02-27 04:20:49',
				volume_allowance: null,
				volume_currency: null,
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			const result = getDiscountTooltipText( discountFee );

			expect( result ).toContain( '20%' );
			expect( result ).toContain( 'through' );
			expect( result ).not.toContain( 'first' );
			expect( result ).not.toContain( 'total payment volume' );
		} );

		it( 'returns basic text when neither volume_allowance nor end_time are provided', () => {
			const discountFee: DiscountFee = {
				discount: 0.15,
				end_time: null,
				volume_allowance: null,
				volume_currency: null,
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			const result = getDiscountTooltipText( discountFee );

			expect( result ).toBe( 'You are saving 15% on processing fees.' );
		} );

		it( 'uses volume_currency when available, falls back to currency', () => {
			const discountFeeWithVolumeCurrency: DiscountFee = {
				discount: 0.1,
				end_time: null,
				volume_allowance: 10000,
				volume_currency: 'eur',
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			getDiscountTooltipText( discountFeeWithVolumeCurrency );

			expect( formatCurrency ).toHaveBeenCalledWith( 10000, 'eur' );

			const discountFeeWithoutVolumeCurrency: DiscountFee = {
				discount: 0.1,
				end_time: null,
				volume_allowance: 10000,
				volume_currency: null,
				current_volume: null,
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			};

			getDiscountTooltipText( discountFeeWithoutVolumeCurrency );

			expect( formatCurrency ).toHaveBeenCalledWith( 10000, 'USD' );
		} );
	} );
} );
