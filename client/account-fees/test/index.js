/* eslint-disable camelcase */
/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountFees from '../';

describe( 'AccountFees', () => {
	const renderAccountFees = ( accountFees ) => {
		return render( <AccountFees accountFees={ accountFees } /> );
	};

	test( 'renders normal base fee', () => {
		const { container: accountFees } = renderAccountFees( {
			base: {
				percentage_rate: 0.029,
				fixed_rate: 30,
			},
			discount: [],
		} );
		expect( accountFees ).toMatchSnapshot();
	} );

	test( 'renders discounted base fee', () => {
		const { container: accountFees } = renderAccountFees( {
			base: {
				percentage_rate: 0.029,
				fixed_rate: 30,
			},
			discount: [
				{
					percentage_rate: 0.02,
					fixed_rate: 20,
					volume_allowance: 100000000,
					current_volume: 1234556,
				},
			],
		} );
		expect( accountFees ).toMatchSnapshot();
	} );

	test( 'renders discounted fee without volume limit', () => {
		const { container: accountFees } = renderAccountFees( {
			base: {
				percentage_rate: 0.029,
				fixed_rate: 30,
			},
			discount: [
				{
					discount: 0.3,
				},
			],
		} );
		expect( accountFees ).toMatchSnapshot();
	} );

	test( 'renders discounted fee with volume limit', () => {
		const { container: accountFees } = renderAccountFees( {
			base: {
				percentage_rate: 0.029,
				fixed_rate: 30,
			},
			discount: [
				{
					discount: 0.3,
					volume_allowance: 2500000,
					current_volume: 1234556,
				},
			],
		} );
		expect( accountFees ).toMatchSnapshot();
	} );

	test( 'renders discounted fee with end date', () => {
		const { container: accountFees } = renderAccountFees( {
			base: {
				percentage_rate: 0.029,
				fixed_rate: 30,
			},
			discount: [
				{
					discount: 0.3,
					end_time: '2025-03-31 12:00:00',
				},
			],
		} );
		expect( accountFees ).toMatchSnapshot();
	} );

	test( 'renders discounted fee with volume limit and end date', () => {
		const { container: accountFees } = renderAccountFees( {
			base: {
				percentage_rate: 0.029,
				fixed_rate: 30,
			},
			discount: [
				{
					discount: 0.3,
					volume_allowance: 2500000,
					current_volume: 1234556,
					end_time: '2025-03-31 12:00:00',
				},
			],
		} );
		expect( accountFees ).toMatchSnapshot();
	} );

	test( 'renders first discounted fee ignoring the rest', () => {
		// This is a limitation of the current UI, not necessarily a desired behavior.
		const { container: accountFees } = renderAccountFees( {
			base: {
				percentage_rate: 0.029,
				fixed_rate: 30,
			},
			discount: [
				{
					discount: 0.2,
				},
				{
					discount: 0.3,
					volume_allowance: 2500000,
					current_volume: 1234556,
				},
			],
		} );
		expect( accountFees ).toMatchSnapshot();
	} );
} );
