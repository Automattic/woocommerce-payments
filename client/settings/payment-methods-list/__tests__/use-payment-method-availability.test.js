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
			progressiveOnboarding: { isEnabled: false, isComplete: false },
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
			/We need more information from you to enable this method./
		);
	} );

	it( 'returns "More information needed" for the "unrequested" status when PO in progress', () => {
		global.wcpaySettings.progressiveOnboarding = {
			isEnabled: true,
			isComplete: false,
		};

		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'p24' )
		);

		expect( result.current.isActionable ).toBe( false );
		expect( result.current.chip ).toBe( 'More information needed' );
		// Since in this case we use `interpolateComponents`, I'm using `render` to render the notice, and asserting on its text content.
		expect( render( result.current.notice ).container.textContent ).toMatch(
			/We need more information from you to enable this method./
		);
	} );

	it( 'returns "Approval pending" for the "pending" status and Alipay', () => {
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'alipay' )
		);

		expect( result.current.isActionable ).toBe( false );
		expect( result.current.chip ).toBe( 'Approval pending' );
		// Since in this case we use `interpolateComponents`, I'm using `render` to render the notice, and asserting on its text content.
		expect( render( result.current.notice ).container.textContent ).toMatch(
			/Alipay requires your store to be live and fully functional before it can be reviewed for use with their service./
		);
	} );

	it( 'returns "Approval pending" message for the "pending" status', () => {
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'klarna' )
		);
		expect( result.current.isActionable ).toBe( false );
		expect( result.current.chip ).toBe( 'Approval pending' );
		expect( result.current.notice ).toBe(
			'This payment method is pending approval. Once approved, you will be able to use it.'
		);
	} );

	it( 'returns "Pending verification" for the "pending_verification" status', () => {
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'sepa_debit' )
		);
		expect( result.current.isActionable ).toBe( false );
		expect( result.current.chip ).toBe( 'Pending verification' );
		expect( result.current.notice ).toBe(
			"SEPA Direct Debit won't be visible to your customers until you provide the required information." +
				' Follow the instructions sent by our partner Stripe to test@woo.com.'
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
		expect( render( result.current.notice ).container.textContent ).toMatch(
			/Your application to use Affirm has been rejected, please check your email for more information. Need help?/
		);
		expect( result.current.noticeType ).toBe( 'error' );
	} );

	it( 'returns "manual capture disabled" notice', () => {
		useManualCapture.mockReturnValue( [ true ] );
		const { result } = renderHook( () =>
			usePaymentMethodAvailability( 'bancontact' )
		);
		expect( result.current.isActionable ).toBe( false );
		expect( result.current.notice ).toBe(
			'Bancontact is not available to your customers when the "manual capture" setting is enabled.'
		);
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
