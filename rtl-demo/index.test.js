/**
 * External dependencies
 */
import React from 'react';
import { render, fireEvent, wait } from '@testing-library/react';
import user from '@testing-library/user-event';
import mockApiFetch from '@wordpress/api-fetch';

import { Form, FormContainer, ConnectedTransactions } from './components';
import { initStore } from '../client/data/store';

jest.mock( '@wordpress/api-fetch' );

describe( 'React testing library usage examples', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );
	test( 'basic snapshot test', () => {
		const { container } = render( <Form /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'fire event', () => {
		const onSave = jest.fn();
		const { getByText } = render( <Form onSave={ onSave } /> );
		const submitButton = getByText( /submit/i );
		fireEvent.click( submitButton, { preventDefault: () => {} } );

		expect( onSave ).toHaveBeenCalled();
	} );

	test( 'typing', () => {
		const { getByLabelText } = render( <Form /> );
		const input = getByLabelText( /text input/i );

		// Option 1. Fire only this event.
		fireEvent.change( input, { target: { value: 'hello' } } );

		// Optoin 2. Fire lifecycle events like focus.
		user.type( input, ' world' );

		expect( input ).toHaveValue( 'hello world' );
	} );

	test( 'rerender with new props', () => {
		const { rerender, getByTestId } = render( <Form isSaving={ false } /> );
		const form = getByTestId( 'demo-form' );
		expect( form ).not.toHaveClass( 'disalbed' );

		rerender( <Form isSaving={ true } /> );
		expect( form ).toHaveClass( 'disabled' );
	} );

	test( 'container with mocked api', async () => {
		const testText = 'hello';
		const testNumber = '10';

		const { getByText, getByLabelText, getByTestId, queryByRole } = render( <FormContainer queryId="123" /> );

		// Fill in the form.
		fireEvent.change( getByLabelText( /text input/i ), {
			target: { value: testText },
		} );
		fireEvent.change( getByLabelText( /number input/i ), {
			target: { value: testNumber },
		} );

		const form = getByTestId( 'demo-form' );
		const submit = getByText( /submit/i );

		// Check initial state.
		expect( form ).not.toHaveClass( 'disabled' );
		expect( queryByRole( 'alert' ) ).not.toBeInTheDocument();

		mockApiFetch.mockResolvedValueOnce();
		fireEvent.click( submit );

		expect( form ).toHaveClass( 'disabled' );
		expect( queryByRole( 'alert' ) ).not.toBeInTheDocument();

		// Check that mocked api was called properly.
		expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
		expect( mockApiFetch ).toHaveBeenCalledWith( {
			path: '/some/update/query/123',
			method: 'post',
			body: {
				text: testText,
				number: testNumber,
			},
		} );

		// Wait for component updates to finish.
		await wait( () => expect( form ).not.toHaveClass( 'disabled' ) );
		expect( queryByRole( 'alert' ) ).not.toBeInTheDocument();
	} );

	test( 'api error', async () => {
		const { getByText, getByTestId, getByRole, queryByRole } = render( <FormContainer queryId="123" /> );

		const form = getByTestId( 'demo-form' );
		const submit = getByText( /submit/i );

		// Check initial state.
		expect( form ).not.toHaveClass( 'disabled' );
		expect( queryByRole( 'alert' ) ).not.toBeInTheDocument();

		const testError = 'TEST_ERROR';
		mockApiFetch.mockRejectedValueOnce( new Error( testError ) );
		fireEvent.click( submit );

		expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
		await wait( () => expect( form ).not.toHaveClass( 'disabled' ) );
		expect( getByRole( 'alert' ) ).toHaveTextContent( testError );
	} );

	test.skip( 'debug test', () => {
		const { debug, getByText } = render( <Form /> );
		// log full container html
		debug();

		const submitButton = getByText( /submit/i );
		// log only button
		debug( submitButton );
	} );

	describe( 'when component is connected to the store', () => {
		beforeAll( () => {
			initStore();
		} );
		test( 'test data loaded successfully', async () => {
			/* eslint-disable camelcase */
			const fakeTransactions = [
				{
					transaction_id: 'txn_1GIYexFL7IKwCJxfEBcdeCG6',
					type: 'dispute',
					date: '2020-03-03 11:11:35',
					source: 'visa',
					customer_name: 'Test User',
					customer_email: 'vasily.belolapotkov@automattic.com',
					customer_country: 'US',
					amount: -2800,
					net: -4300,
					fees: 1500,
					currency: 'usd',
					risk_level: 0,
					charge_id: 'ch_1GIYexFL7IKwCJxfBgxPK28l',
					deposit_id: 'po_1GIqY3FL7IKwCJxfDn3E1DLO',
					order: {
						number: '213',
						url:
							'http://localhost:8082/wp-admin/post.php?post=213&action=edit',
					},
				},
				{
					transaction_id: 'txn_1GIYexFL7IKwCJxfHzH4MDCg',
					type: 'charge',
					date: '2020-03-03 11:11:35',
					source: 'visa',
					customer_name: 'Test User',
					customer_email: 'vasily.belolapotkov@automattic.com',
					customer_country: 'US',
					amount: 2800,
					net: 2689,
					fees: 111,
					currency: 'usd',
					risk_level: 0,
					charge_id: 'ch_1GIYexFL7IKwCJxfBgxPK28l',
					deposit_id: 'po_1GIqY3FL7IKwCJxfDn3E1DLO',
					order: {
						number: '213',
						url:
							'http://localhost:8082/wp-admin/post.php?post=213&action=edit',
					},
				},
			];
			/* eslint-enable camelcase */

			/*
				Note: in our resolvers we rely on a control `apiFetch` provided by `@wordpress/data-controls` for fetching data.
				@wordpress/data-controls has its own dependency on @wordoress/api-fetch that is why
				mocking @wordoress/api-fetch does not work here, hence we have to mock `window.fetch`.
			*/
			window.fetch = jest.fn( async () => fakeSuccessfullResponse( fakeTransactions ) );
			const { queryByText, queryByRole, container } = render( <ConnectedTransactions /> );

			// Check loading state.
			expect( queryByText( /loading/i ) ).toBeInTheDocument();

			// Wait untill loading is finished. It will throw after timeout if not finished.
			await wait( () =>
				expect( queryByText( /loading/i ) ).not.toBeInTheDocument()
			);

			// Make sure our API was called without error.
			expect( window.fetch ).toHaveBeenCalledTimes( 1 );
			expect( queryByRole( 'alert' ) ).not.toBeInTheDocument();
			expect( container ).toMatchSnapshot();
		} );
	} );
} );

function fakeSuccessfullResponse( data ) {
	return {
		status: 200,
		json: () => Promise.resolve( { data } ),
	};
}
