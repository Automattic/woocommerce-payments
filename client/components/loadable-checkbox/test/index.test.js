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
	let checked = false;
	const mockOnChangeEvent = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		mockOnChangeEvent.mockImplementation( ( status ) => {
			checked = status;
		} );
		checked = false;
	} );

	const getLoadableCheckbox = (
		disabled = false,
		delayMsOnCheck = 0,
		delayMsOnUncheck = 0
	) => {
		return (
			<LoadableCheckbox
				label="Foo"
				id="foo"
				checked={ checked }
				onChange={ mockOnChangeEvent }
				disabled={ disabled }
				delayMsOnCheck={ delayMsOnCheck }
				delayMsOnUncheck={ delayMsOnUncheck }
			/>
		);
	};

	test( 'renders correctly', () => {
		const container = render( getLoadableCheckbox() );
		expect( container ).toMatchSnapshot();
		expect( container.queryByText( 'Foo' ) ).toBeInTheDocument();
		expect( container.queryByLabelText( 'Foo' ) ).toBeInTheDocument();
	} );
	test( 'starts as checked', () => {
		checked = true;
		const container = render( getLoadableCheckbox() );
		expect( mockOnChangeEvent ).not.toBeCalled();
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();
	} );
	test( 'starts as unchecked', () => {
		checked = false;
		const container = render( getLoadableCheckbox() );
		expect( mockOnChangeEvent ).not.toBeCalled();
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
	} );
	test( 'clicking an unchecked checkbox changes the checkbox state to checked', () => {
		const container = render( getLoadableCheckbox() );
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
		expect( mockOnChangeEvent ).not.toBeCalled();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );
			container.rerender( getLoadableCheckbox() );
		} );
		expect( mockOnChangeEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockOnChangeEvent ).toHaveBeenCalledWith( true );
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();
	} );
	test( 'clicking an checked checkbox changes the checkbox state to unchecked', () => {
		checked = true;
		const container = render( getLoadableCheckbox() );
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );
			container.rerender( getLoadableCheckbox() );
		} );
		expect( mockOnChangeEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockOnChangeEvent ).toHaveBeenCalledWith( false );
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
	} );
	test( 'clicking an disabled checkbox doesnt change the state', () => {
		checked = false;
		const container = render( getLoadableCheckbox( true ) );
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );
			container.rerender( getLoadableCheckbox( true ) );
		} );
		expect( mockOnChangeEvent ).not.toHaveBeenCalled();
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();
	} );
	test( 'clicking a checkbox with delay waits before checking the input', () => {
		const container = render( getLoadableCheckbox( false, 1500, 0 ) );
		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();

		jest.useFakeTimers();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );
			jest.runTimersToTime( 400 );
		} );
		container.rerender( getLoadableCheckbox( false, 1500, 0 ) );
		expect( mockOnChangeEvent ).not.toHaveBeenCalled();

		act( () => {
			jest.runTimersToTime( 1200 );
		} );
		container.rerender( getLoadableCheckbox( false, 1500, 0 ) );
		expect( mockOnChangeEvent ).toHaveBeenCalled();
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();

		jest.useRealTimers();
	} );
	test( 'clicking a checkbox with delay waits before unchecking the input', () => {
		checked = true;
		const container = render( getLoadableCheckbox( false, 0, 1500 ) );
		expect( container.queryByLabelText( 'Foo' ) ).toBeChecked();

		jest.useFakeTimers();
		act( () => {
			userEvent.click( container.queryByLabelText( 'Foo' ) );
			jest.runTimersToTime( 400 );
		} );
		container.rerender( getLoadableCheckbox( false, 0, 1500 ) );
		expect( mockOnChangeEvent ).not.toHaveBeenCalled();
		act( () => {
			jest.runTimersToTime( 1200 );
		} );
		container.rerender( getLoadableCheckbox( false, 0, 1500 ) );
		expect( mockOnChangeEvent ).toHaveBeenCalledWith( false );

		expect( container.queryByLabelText( 'Foo' ) ).not.toBeChecked();

		jest.useRealTimers();
	} );
} );
