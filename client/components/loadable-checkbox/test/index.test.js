/** @format */
/**
 * External dependencies
 */
import { act, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import LoadableCheckbox from '../index';

describe( 'Loadable checkbox', () => {
	test( 'renders correctly', () => {
		const container = render( <LoadableCheckbox label="Foo" id="foo" /> );
		expect( container ).toMatchSnapshot();
		expect( container.queryByText( 'Foo' ) ).toBeInTheDocument();
		expect( container.queryByLabelText( 'Foo' ) ).toBeInTheDocument();
	} );

	test( 'starts as unchecked', () => {
		const container = render( <LoadableCheckbox label="Foo" id="foo" /> );
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
	} );
	test( 'starts as checked', () => {
		const container = render(
			<LoadableCheckbox label="Foo" id="foo" checked={ true } />
		);
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();
	} );
	test( 'clicking an unchecked checkbox changes the checkbox state to checked', () => {
		const mockOnChangeEvent = jest.fn();
		const container = render(
			<LoadableCheckbox
				label="Foo"
				id="foo"
				onChange={ mockOnChangeEvent }
			/>
		);
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );
		} );
		expect( mockOnChangeEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockOnChangeEvent ).toHaveBeenCalledWith( true );
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();
	} );
	test( 'clicking an checked checkbox changes the checkbox state to unchecked', () => {
		const mockOnChangeEvent = jest.fn();
		const container = render(
			<LoadableCheckbox
				label="Foo"
				id="foo"
				checked={ true }
				onChange={ mockOnChangeEvent }
			/>
		);
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );
		} );
		expect( mockOnChangeEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockOnChangeEvent ).toHaveBeenCalledWith( false );
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
	} );
	test( 'clicking an disabled checkbox doesnt change the state', () => {
		const mockOnChangeEvent = jest.fn();
		const container = render(
			<LoadableCheckbox
				label="Foo"
				id="foo"
				checked={ false }
				disabled={ true }
				onChange={ mockOnChangeEvent }
			/>
		);
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );
		} );
		expect( mockOnChangeEvent ).not.toHaveBeenCalled();
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
	} );
	test( 'clicking a checkbox with delay waits before checking the input', () => {
		const mockOnChangeEvent = jest.fn();
		const container = render(
			<LoadableCheckbox
				label="Foo"
				id="foo"
				delayMsOnCheck={ 1000 }
				onChange={ mockOnChangeEvent }
			/>
		);
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();

		jest.useFakeTimers();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );
			jest.runTimersToTime( 400 );
		} );
		expect( mockOnChangeEvent ).not.toHaveBeenCalled();

		act( () => {
			jest.runTimersToTime( 1200 );
		} );
		expect( mockOnChangeEvent ).toHaveBeenCalled();
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();

		jest.useRealTimers();
	} );
	test( 'clicking a checkbox with delay waits before unchecking the input', () => {
		const mockOnChangeEvent = jest.fn();
		const container = render(
			<LoadableCheckbox
				label="Foo"
				id="foo"
				checked={ true }
				delayMsOnUncheck={ 1000 }
				onChange={ mockOnChangeEvent }
			/>
		);
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();

		jest.useFakeTimers();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );

			jest.runTimersToTime( 400 );
		} );
		expect( mockOnChangeEvent ).not.toHaveBeenCalled();
		act( () => {
			jest.runTimersToTime( 1200 );
		} );
		expect( mockOnChangeEvent ).toHaveBeenCalled();

		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();

		jest.useRealTimers();
	} );
} );
