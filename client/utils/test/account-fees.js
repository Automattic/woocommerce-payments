/**
 * Internal dependencies
 */
import {
	formatAccountFeesDescription,
	formatMethodFeesDescription,
	getCurrentFee,
} from '../account-fees';

describe( 'Account fees utility functions', () => {
	window.wcpaySettings = {
		zeroDecimalCurrencies: [],
	};

	describe( 'getCurrentFee()', () => {
		it( 'returns first discount regardless of amount', () => {
			const accountFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [
					{
						discount: 0.1,
					},
					{
						discount: 0.2,
					},
				],
			};

			expect( getCurrentFee( accountFees ) ).toEqual(
				accountFees.discount[ 0 ]
			);
		} );

		it( 'returns base if no discounts are present', () => {
			const accountFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [],
			};

			expect( getCurrentFee( accountFees ) ).toEqual( accountFees.base );
		} );
	} );

	describe( 'formatAccountFeesDescription()', () => {
		it( 'uses default formats if none specified', () => {
			const accountFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [],
			};

			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				'12.3% + $4.57 per transaction'
			);
		} );

		it( 'uses first discount regardless of discounted amount', () => {
			const accountFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [
					{
						discount: 0.1,
					},
					{
						discount: 0.2,
					},
				],
			};

			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				<>
					<s>12.3% + $4.57 per transaction</s> 11.07% + $4.11 per
					transaction (10% discount)
				</>
			);
		} );

		it( 'uses percentage and fixed rate from discount object if no discount amount is available', () => {
			const accountFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [
					{
						percentage_rate: 12.3,
						fixed_rate: 4567.8,
					},
				],
			};

			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				<>
					<s>12.3% + $4.57 per transaction</s> 1230% + $45.68 per
					transaction
				</>
			);
		} );

		it( 'uses discount amount if both it and percentage and fixed rate are available', () => {
			const accountFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [
					{
						discount: 0.1,
						percentage_rate: 12.3,
						fixed_rate: 4567.8,
					},
				],
			};

			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				<>
					<s>12.3% + $4.57 per transaction</s> 11.07% + $4.11 per
					transaction (10% discount)
				</>
			);
		} );

		it( 'does not return discount percentage if discount format is empty', () => {
			const accountFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [
					{
						discount: 0.1,
					},
				],
			};

			expect(
				formatAccountFeesDescription( accountFees, undefined, '' )
			).toEqual(
				<>
					<s>12.3% + $4.57 per transaction</s> 11.07% + $4.11 per
					transaction
				</>
			);
		} );

		it( 'only describes discount if it is different than base fee', () => {
			const accountFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [
					{
						percentage_rate: 0.123,
						fixed_rate: 456.78,
						currency: 'USD',
					},
				],
			};

			expect( formatAccountFeesDescription( accountFees ) ).toEqual(
				'12.3% + $4.57 per transaction'
			);
		} );

		it( 'uses custom formats', () => {
			const accountFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [
					{
						discount: 0.1,
					},
				],
			};

			expect(
				formatAccountFeesDescription(
					accountFees,
					'perc: %1$f fixed: %2$s',
					'disc perc: %f'
				)
			).toEqual(
				<>
					<s>perc: 12.3 fixed: $4.57</s> perc: 11.07 fixed: $4.11 disc
					perc: 10
				</>
			);
		} );
	} );

	describe( 'formatMethodFeesDescription()', () => {
		it( 'returns fees in short format', () => {
			const methodFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [],
			};

			expect( formatMethodFeesDescription( methodFees ) ).toEqual(
				'12.3% + $4.57'
			);
		} );

		it( 'returns discounted fees in short format', () => {
			const methodFees = {
				base: {
					percentage_rate: 0.123,
					fixed_rate: 456.78,
					currency: 'USD',
				},
				discount: [
					{
						discount: 0.1,
					},
				],
			};

			expect( formatMethodFeesDescription( methodFees ) ).toEqual(
				<>
					<s>12.3% + $4.57</s> 11.07% + $4.11
				</>
			);
		} );

		it( 'returns "missing fees" if no fees are supplied', () => {
			expect( formatMethodFeesDescription( undefined ) ).toEqual(
				'missing fees'
			);
		} );
	} );
} );
