/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';
import { mocked } from 'ts-jest/utils';

/**
 * Internal dependencies
 */
import VatFormModal from '..';
import VatForm from '../../form';

jest.mock( '../../form', () => jest.fn() );

describe( 'VAT form modal', () => {
	beforeEach( () => {
		mocked( VatForm ).mockReturnValue( <p>VAT Form</p> );
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
			screen.getByRole( 'dialog', { name: 'VAT details' } )
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
			screen.queryByRole( 'dialog', { name: 'VAT details' } )
		).toBeNull();
	} );

	it( 'should render the VAT Form', () => {
		render(
			<VatFormModal
				isModalOpen={ true }
				setModalOpen={ () => ( {} ) }
				onCompleted={ () => ( {} ) }
			/>
		);
		expect(
			screen.getByRole( 'dialog', { name: 'VAT details' } )
		).toMatchSnapshot();
	} );
} );
