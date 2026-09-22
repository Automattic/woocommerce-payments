/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { getFields, EntityField } from '../fields';

beforeEach( () => {
	Object.assign( global, {
		wcpaySettingsDataform: {
			methodLabels: { card: 'Card', ideal: 'iDEAL', klarna: 'Klarna' },
			classicUrl: '/settings',
			woopayEligible: true,
		},
	} );
} );
afterEach( () => {
	delete ( global as unknown as Record< string, unknown > )
		.wcpaySettingsDataform;
} );

const renderField = ( id: string, data: Record< string, unknown > ) => {
	const field = getFields().find( ( item ) => item.id === id );
	if ( ! field || typeof field.Edit === 'string' )
		throw new Error( 'Custom control is missing' );
	const Edit = field.Edit as React.ComponentType< {
		data: Record< string, unknown >;
		field: EntityField;
		onChange: jest.Mock;
	} >;
	const onChange = jest.fn();
	render( <Edit data={ data } field={ field } onChange={ onChange } /> );
	return onChange;
};

it( 'shows available methods, locks Card and preserves other selections when toggling a method', () => {
	const onChange = renderField( 'enabled_payment_method_ids', {
		enabled_payment_method_ids: [ 'card', 'ideal', 'klarna' ],
		available_payment_method_ids: [ 'card', 'ideal' ],
	} );
	expect( screen.getByRole( 'checkbox', { name: 'Card' } ) ).toBeDisabled();
	expect(
		screen.queryByRole( 'checkbox', { name: 'Klarna' } )
	).not.toBeInTheDocument();
	fireEvent.click( screen.getByRole( 'checkbox', { name: 'iDEAL' } ) );
	expect( onChange ).toHaveBeenCalledWith( {
		enabled_payment_method_ids: [ 'card', 'klarna' ],
	} );
} );

it( 'edits express checkout and links to its existing customisation screen', () => {
	const onChange = renderField( 'is_payment_request_enabled', {
		is_payment_request_enabled: true,
	} );
	fireEvent.click(
		screen.getByRole( 'checkbox', { name: 'Apple Pay / Google Pay' } )
	);
	expect( onChange ).toHaveBeenCalledWith( {
		is_payment_request_enabled: false,
	} );
	expect( screen.getByRole( 'link', { name: 'Customize' } ) ).toHaveAttribute(
		'href',
		'/settings&method=payment_request'
	);
} );
