/**
 * External dependencies
 */
import React from 'react';
import { render, fireEvent, wait } from '@testing-library/react';
import user from '@testing-library/user-event';
import mockApiFetch from '@wordpress/api-fetch';

import { Form, FormContainer } from './components';

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

	test.todo( 'component connected to the store' );
} );
