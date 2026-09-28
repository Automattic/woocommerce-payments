/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import SettingsScreenBody, { getTabs } from '../body';

const form = {
	layout: { type: 'regular' },
	fields: getTabs()
		.flatMap( ( tab ) => tab.cards )
		.map( ( id ) => ( { id } ) ),
};

// TabPanel updates its state after mounting, so rendering waits for that.
const renderBody = async ( view?: string ) => {
	const onChangeView = jest.fn();
	const renderForm = jest.fn( ( tabForm ) => (
		<p>
			{ tabForm.fields
				.map( ( card: { id: string } ) => card.id )
				.join( ',' ) }
		</p>
	) );
	await act( async () => {
		render(
			<SettingsScreenBody
				definition={ { form } }
				view={ view }
				onChangeView={ onChangeView }
				renderForm={ renderForm }
			/>
		);
	} );
	return { onChangeView, renderForm };
};

describe( 'SettingsScreenBody', () => {
	it( 'shows the general cards when no tab is selected', async () => {
		await renderBody();

		expect(
			screen.getByText( 'general,fraud-protection,account-notifications' )
		).toBeInTheDocument();
	} );

	it( 'shows the cards of the tab in the view parameter', async () => {
		await renderBody( 'payouts' );

		expect( screen.getByText( 'payouts' ) ).toBeInTheDocument();
	} );

	it( 'keeps the form layout for each tab', async () => {
		const { renderForm } = await renderBody( 'advanced' );

		expect( renderForm ).toHaveBeenLastCalledWith( {
			layout: form.layout,
			fields: [ { id: 'advanced' } ],
		} );
	} );

	it( 'changes the view when another tab is selected', async () => {
		const { onChangeView } = await renderBody();

		await act( async () => {
			userEvent.click(
				screen.getByRole( 'tab', { name: 'Transactions' } )
			);
		} );

		expect( onChangeView ).toHaveBeenCalledWith( 'transactions' );
		expect(
			screen.getByText( 'transactions,bank-statement,customer-support' )
		).toBeInTheDocument();
	} );
} );
