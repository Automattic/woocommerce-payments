/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { expressCheckoutLocation, getFieldParts } from '../field-parts';

describe( 'expressCheckoutLocation', () => {
	const parts = expressCheckoutLocation( 'woopay', 'cart' );

	it( 'is on when the method is in the location’s methods', () => {
		expect(
			parts.getValue?.( {
				item: { express_checkout_cart_methods: [ 'woopay' ] },
			} )
		).toBe( true );
		expect( parts.getValue?.( { item: {} } ) ).toBe( false );
	} );

	it( 'adds or removes the method and keeps the others', () => {
		const item = {
			express_checkout_cart_methods: [ 'payment_request', 'woopay' ],
		};

		expect( parts.setValue?.( { item, value: false } ) ).toEqual( {
			express_checkout_cart_methods: [ 'payment_request' ],
		} );
		expect(
			parts.setValue?.( {
				item: { express_checkout_cart_methods: [ 'payment_request' ] },
				value: true,
			} )
		).toEqual( {
			express_checkout_cart_methods: [ 'payment_request', 'woopay' ],
		} );
	} );
} );

describe( 'payout schedule notice', () => {
	const { payout_schedule_notice: notice } = getFieldParts();
	const Notice = notice.render as React.ComponentType< {
		item: Record< string, unknown >;
	} >;
	const unlocked = {
		deposit_status: 'enabled',
		deposit_restrictions: 'deposits_unrestricted',
		deposit_completed_waiting_period: true,
	};

	it( 'shows only while the schedule is locked', () => {
		expect( notice.isVisible?.( unlocked ) ).toBe( false );
		expect(
			notice.isVisible?.( {
				...unlocked,
				deposit_completed_waiting_period: false,
			} )
		).toBe( true );
	} );

	it( 'explains a restricted schedule', () => {
		render(
			<Notice
				item={ {
					...unlocked,
					deposit_restrictions: 'schedule_restricted',
				} }
			/>
		);
		expect(
			screen.getByText( /currently unavailable for your store/, {
				selector: '.wcpay-inline-notice__content',
			} )
		).toBeInTheDocument();
	} );

	it( 'explains the waiting period for new accounts', () => {
		render(
			<Notice
				item={ {
					...unlocked,
					deposit_completed_waiting_period: false,
				} }
			/>
		);
		expect(
			screen.getByText( /7-day waiting period/, {
				selector: '.wcpay-inline-notice__content',
			} )
		).toBeInTheDocument();
	} );
} );

it( 'shows the fraud protection error only when the rules failed to load', () => {
	const { fraud_protection_error: error } = getFieldParts();
	expect(
		error.isVisible?.( { advanced_fraud_protection_settings: 'error' } )
	).toBe( true );
	expect(
		error.isVisible?.( { advanced_fraud_protection_settings: [] } )
	).toBe( false );
} );
