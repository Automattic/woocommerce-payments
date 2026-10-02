/** @format */

/**
 * External dependencies
 */
import { render, screen, within } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import VatFormModal from '..';
import VatForm from '../../form';

jest.mock( '../../form', () => jest.fn() );

describe( 'Tax details modal', () => {
	beforeEach( () => {
		jest.mocked( VatForm ).mockReturnValue( <p>VAT Form</p> );
	} );

	it( 'should render when isModalOpen is true', () => {
		render(
			<VatFormModal
				isModalOpen={ true }
				setModalOpen={ () => ( {} ) }
				onCompleted={ () => ( {} ) }
			/>
		);
		expect(
			screen.getByRole( 'dialog', { name: 'Set your tax details' } )
		).toBeVisible();
	} );

	it( 'should not render when isModalOpen is false', () => {
		render(
			<VatFormModal
				isModalOpen={ false }
				setModalOpen={ () => ( {} ) }
				onCompleted={ () => ( {} ) }
			/>
		);
		expect(
			screen.queryByRole( 'dialog', { name: 'Set your tax details' } )
		).toBeNull();
	} );

	it( 'should render the VAT Form inside the dialog', () => {
		const onCompleted = jest.fn();
		render(
			<VatFormModal
				isModalOpen={ true }
				setModalOpen={ () => ( {} ) }
				onCompleted={ onCompleted }
			/>
		);
		expect(
			within(
				screen.getByRole( 'dialog', { name: 'Set your tax details' } )
			).getByText( 'VAT Form' )
		).toBeInTheDocument();
		expect( jest.mocked( VatForm ).mock.lastCall?.[ 0 ] ).toEqual( {
			onCompleted,
		} );
	} );
} );
