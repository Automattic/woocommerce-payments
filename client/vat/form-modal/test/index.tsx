/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import VatFormModal from '..';

describe( 'VAT form modal', () => {
	it( 'should render when isModalOpen is true', () => {
		render(
			<VatFormModal isModalOpen={ true } setModalOpen={ () => ( {} ) } />
		);
		expect(
			screen.getByRole( 'dialog', { name: 'VAT details' } )
		).toBeVisible();
	} );

	it( 'should not render when isModalOpen is false', () => {
		render(
			<VatFormModal isModalOpen={ false } setModalOpen={ () => ( {} ) } />
		);
		expect(
			screen.queryByRole( 'dialog', { name: 'VAT details' } )
		).toBeNull();
	} );

	it( 'should render the VAT Form', () => {
		render(
			<VatFormModal isModalOpen={ true } setModalOpen={ () => ( {} ) } />
		);
		expect(
			screen.getByRole( 'dialog', { name: 'VAT details' } )
		).toMatchSnapshot();
	} );
} );
