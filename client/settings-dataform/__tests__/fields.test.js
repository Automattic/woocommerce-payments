/**
 * External dependencies
 */
import React from 'react';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { act, render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { createSettingsRegistry } from '../settings-registry';
import { SETTINGS_STORE_NAME } from '../../data/store-names';
import AppleGooglePayExpressCheckoutItem from '../../settings/express-checkout/apple-google-pay-item';

const entity = [ 'woo_settings', 'woopayments', undefined ];
const saved = {
	enabled_payment_method_ids: [ 'card', 'ideal', 'klarna' ],
	is_payment_request_enabled: true,
	is_woopay_enabled: true,
};

const setup = () => {
	const parent = createRegistry();
	parent.register( coreStore );
	parent.dispatch( coreStore ).addEntities( [
		{
			kind: entity[ 0 ],
			name: entity[ 1 ],
			key: false,
			baseURL: '/test/settings',
		},
	] );
	parent
		.dispatch( coreStore )
		.receiveEntityRecords( entity[ 0 ], entity[ 1 ], saved );
	return { parent, registry: createSettingsRegistry( parent ) };
};

it( 'writes existing payment-method actions into the entity without changing the saved record', () => {
	const { parent, registry } = setup();
	registry
		.dispatch( SETTINGS_STORE_NAME )
		.updateUnselectedPaymentMethod( 'ideal' );
	expect(
		parent.select( coreStore ).getEntityRecordEdits( ...entity )
	).toEqual( { enabled_payment_method_ids: [ 'card', 'klarna' ] } );
	expect( parent.select( coreStore ).getEntityRecord( ...entity ) ).toEqual(
		saved
	);
	expect(
		registry.select( SETTINGS_STORE_NAME ).getEnabledPaymentMethodIds()
	).toEqual( [ 'card', 'klarna' ] );
	parent.dispatch( coreStore ).editEntityRecord( ...entity, saved );
	expect( registry.select( SETTINGS_STORE_NAME ).isDirty() ).toBe( false );
} );

it( 'reuses the real express component and reacts to the shared Discard operation', () => {
	const { parent, registry } = setup();
	render(
		<RegistryProvider value={ registry }>
			<ul>
				<AppleGooglePayExpressCheckoutItem />
			</ul>
		</RegistryProvider>
	);
	const checkbox = screen.getByRole( 'checkbox' );
	expect( checkbox ).toBeChecked();
	fireEvent.click( checkbox );
	expect(
		parent.select( coreStore ).getEntityRecordEdits( ...entity )
	).toEqual( { is_payment_request_enabled: false } );
	expect( checkbox ).not.toBeChecked();
	act( () => {
		parent.dispatch( coreStore ).editEntityRecord( ...entity, saved );
	} );
	expect( checkbox ).toBeChecked();
	expect(
		screen.getByRole( 'link', { name: /Customize/ } )
	).toBeInTheDocument();
} );
