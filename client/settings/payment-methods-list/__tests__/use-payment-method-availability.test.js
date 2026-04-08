/**
 * External dependencies
 */

import { renderHook } from '@testing-library/react-hooks';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import usePaymentMethodAvailability from '../use-payment-method-availability';
import {
	useEnabledPaymentMethodIds,
	useGetPaymentMethodStatuses,
	useManualCapture,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetPaymentMethodStatuses: jest.fn(),
	useManualCapture: jest.fn(),
} ) );

describe( 'usePaymentMethodAvailability', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			isMultiCurrencyEnabled: true,
			storeCurrency: 'USD',
			accountEmail: 'test@woo.com',
		};

		useGetPaymentMethodStatuses.mockReturnValue( {
			card_payments: { status: 'active', requirements: [] },
			bancontact_payments: { status: 'unrequested', requirements: [] },
			eps_payments: {
				status: 'pending',
				requirements: [],
			},
			ideal_payments: {
				status: 'inactive',
				requirements: [],
			},
			p24_payments: {
				status: 'unrequested',
				requirements: [],
			},
			sepa_debit_payments: {
				status: 'pending_verification',
				requirements: [ 'individual.identification_number' ],
			},
			klarna_payments: {
				status: 'pending',
				requirements: [],
			},
			affirm_payments: {
				status: 'rejected',
				requirements: [],
			},
			alipay_payments: {
				status: 'pending',
				requirements: [],
			},
		} );
		useManualCapture.mockReturnValue( [ false ] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[
				'card',
				'bancontact',
				'eps',
				'ideal',
				'p24',
				'sepa_debit',
				'klarna',
				'affirm',
				'alipay',
			],
		] );
	} );

	it( 'returns "More information needed" for the "inactive" status', () => {
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'ideal' )
		);

		expect( result.current.isActionable ).toBe( false );
		expect( result.current.chip ).toBe( 'More information needed' );
		// Since in this case we use `interpolateComponents`, I'm using `render` to render the notice, and asserting on its text content.
		expect( render( result.current.notice ).container.textContent ).toMatch(
			/More information is needed to finish setting up this payment method./
		);
	} );

	it( 'returns "Approval pending" for the "pending" status and Alipay with Learn more link', () => {
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'alipay' )
		);

		expect( result.current.isActionable ).toBe( false );
		expect( result.current.chip ).toBe( 'Approval pending' );
		// Since in this case we use `interpolateComponents`, I'm using `render` to render the notice, and asserting on its text content.
		const { container } = render( result.current.notice );
		expect( container.textContent ).toMatch(
			/Your store must be live and fully functional before this payment method can be offered./
		);
		expect( container.textContent ).toMatch(
			/Approval typically takes 2–3 days./
		);
		expect( container.querySelector( 'a' ) ).not.toBeNull();
	} );

	it( 'returns "Approval pending" message for the "pending" status', () => {
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'klarna' )
		);
		expect( result.current.isActionable ).toBe( false );
		expect( result.current.chip ).toBe( 'Approval pending' );
		expect( result.current.notice ).toBe(
			"This payment method is pending approval. It won't be available at checkout until it's approved."
		);
	} );

	it( 'returns "Pending verification" for the "pending_verification" status with Payments overview link', () => {
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'sepa_debit' )
		);
		expect( result.current.isActionable ).toBe( false );
		expect( result.current.chip ).toBe( 'Pending verification' );
		const { container } = render( result.current.notice );
		expect( container.textContent ).toMatch(
			/SEPA Direct Debit won't be available at checkout yet/
		);
		expect( container.textContent ).toMatch( /Payments overview/ );
		expect( container.querySelector( 'a' ) ).not.toBeNull();
		expect( container.querySelector( 'a' ).href ).toMatch(
			/payments(%2F|\/)overview/
		);
	} );

	it( 'returns "Rejected" for the "rejected" status', () => {
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'affirm' )
		);
		expect( result.current.isActionable ).toBe( false );
		expect( result.current.chip ).toBe( 'Rejected' );
		expect( result.current.chipType ).toBe( 'alert' );
		// Since in this case we use `interpolateComponents`, I'm using `render` to render the notice, and asserting on its text content.
		const { container } = render( result.current.notice );
		expect( container.textContent ).toMatch(
			/Your application to use Affirm has been rejected\. Need help\?/
		);
		expect( container.textContent ).not.toMatch(
			/please check your email/
		);
		expect( result.current.noticeType ).toBe( 'error' );
	} );

	it( 'returns non-actionable for manual capture without individual notice', () => {
		useManualCapture.mockReturnValue( [ true ] );
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'bancontact' )
		);
		expect( result.current.isActionable ).toBe( false );
		expect( result.current.notice ).toBeUndefined();
	} );

	it( 'returns missing currencies notice when multi-currency disabled', () => {
		global.wcpaySettings.isMultiCurrencyEnabled = false;
		global.wcpaySettings.storeCurrency = 'USD';
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'bancontact' )
		);
		expect( result.current.isActionable ).toBe( true );
		expect( result.current.notice ).toMatch(
			/Bancontact requires the EUR currency./
		);
	} );

	it( 'does not return missing currencies notice when multi-currency disabled and the payment method is not active', () => {
		global.wcpaySettings.isMultiCurrencyEnabled = false;
		global.wcpaySettings.storeCurrency = 'USD';
		useEnabledPaymentMethodIds.mockReturnValue( [ 'card' ] );
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'bancontact' )
		);
		expect( result.current.isActionable ).toBe( true );
		expect( result.current.notice ).toBeUndefined();
	} );

	it( 'returns "isActionable: true" if all is good', () => {
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'bancontact' )
		);
		expect( result.current ).toEqual( { isActionable: true } );
	} );
} );
