/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DuplicateStatus from '../duplicate-status';

describe( 'DuplicateStatus', () => {
	const baseProps = {
		duplicateStatus: 'is_duplicate',
		onDuplicateStatusChange: jest.fn(),
		readOnly: false,
	};

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders duplicate status heading', () => {
		render( <DuplicateStatus { ...baseProps } /> );
		expect(
			screen.getByText( 'Was this charge a duplicate?' )
		).toBeInTheDocument();
	} );

	it( 'renders both radio options', () => {
		render( <DuplicateStatus { ...baseProps } /> );
		expect(
			screen.getByLabelText( 'It was a duplicate' )
		).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'It was not a duplicate' )
		).toBeInTheDocument();
	} );

	it( 'selects the correct radio option based on duplicateStatus prop', () => {
		render( <DuplicateStatus { ...baseProps } /> );
		const isDuplicateRadio = screen.getByLabelText( 'It was a duplicate' );
		const isNotDuplicateRadio = screen.getByLabelText(
			'It was not a duplicate'
		);

		expect( isDuplicateRadio ).toBeChecked();
		expect( isNotDuplicateRadio ).not.toBeChecked();
	} );

	it( 'selects is not duplicate when that status is provided', () => {
		render(
			<DuplicateStatus
				{ ...baseProps }
				duplicateStatus="is_not_duplicate"
			/>
		);
		const isNotDuplicateRadio = screen.getByLabelText(
			'It was not a duplicate'
		);
		const isDuplicateRadio = screen.getByLabelText( 'It was a duplicate' );

		expect( isNotDuplicateRadio ).toBeChecked();
		expect( isDuplicateRadio ).not.toBeChecked();
	} );

	it( 'calls onDuplicateStatusChange when radio options are clicked', () => {
		render( <DuplicateStatus { ...baseProps } /> );

		const isNotDuplicateRadio = screen.getByLabelText(
			'It was not a duplicate'
		);
		fireEvent.click( isNotDuplicateRadio );

		expect( baseProps.onDuplicateStatusChange ).toHaveBeenCalledWith(
			'is_not_duplicate'
		);
	} );

	it( 'calls onDuplicateStatusChange when is duplicate is clicked', () => {
		render(
			<DuplicateStatus
				{ ...baseProps }
				duplicateStatus="is_not_duplicate"
			/>
		);

		const isDuplicateRadio = screen.getByLabelText( 'It was a duplicate' );
		fireEvent.click( isDuplicateRadio );

		expect( baseProps.onDuplicateStatusChange ).toHaveBeenCalledWith(
			'is_duplicate'
		);
	} );

	it( 'disables radio options when readOnly is true', () => {
		render( <DuplicateStatus { ...baseProps } readOnly={ true } /> );

		const isNotDuplicateRadio = screen.getByLabelText(
			'It was not a duplicate'
		);
		const isDuplicateRadio = screen.getByLabelText( 'It was a duplicate' );

		expect( isNotDuplicateRadio ).toBeDisabled();
		expect( isDuplicateRadio ).toBeDisabled();
	} );

	it( 'enables radio options when readOnly is false', () => {
		render( <DuplicateStatus { ...baseProps } readOnly={ false } /> );

		const isNotDuplicateRadio = screen.getByLabelText(
			'It was not a duplicate'
		);
		const isDuplicateRadio = screen.getByLabelText( 'It was a duplicate' );

		expect( isNotDuplicateRadio ).not.toBeDisabled();
		expect( isDuplicateRadio ).not.toBeDisabled();
	} );

	it( 'enables radio options when readOnly is not provided', () => {
		render( <DuplicateStatus { ...baseProps } /> );

		const isNotDuplicateRadio = screen.getByLabelText(
			'It was not a duplicate'
		);
		const isDuplicateRadio = screen.getByLabelText( 'It was a duplicate' );

		expect( isNotDuplicateRadio ).not.toBeDisabled();
		expect( isDuplicateRadio ).not.toBeDisabled();
	} );

	it( 'does not call onDuplicateStatusChange when readOnly is true', () => {
		render( <DuplicateStatus { ...baseProps } readOnly={ true } /> );

		const isNotDuplicateRadio = screen.getByLabelText(
			'It was not a duplicate'
		);
		fireEvent.click( isNotDuplicateRadio );

		expect( baseProps.onDuplicateStatusChange ).not.toHaveBeenCalled();
	} );

	it( 'renders with correct CSS classes', () => {
		render( <DuplicateStatus { ...baseProps } /> );

		const section = screen
			.getByText( 'Was this charge a duplicate?' )
			.closest( 'section' );
		expect( section ).toHaveClass(
			'wcpay-dispute-evidence-duplicate-status'
		);

		const heading = screen.getByText( 'Was this charge a duplicate?' );
		expect( heading ).toHaveClass(
			'wcpay-dispute-evidence-duplicate-status__heading'
		);

		const fieldGroup = heading.nextElementSibling;
		expect( fieldGroup ).toHaveClass(
			'wcpay-dispute-evidence-duplicate-status__field-group'
		);
	} );

	it( 'handles multiple rapid clicks correctly', () => {
		// Wrapper to manage duplicateStatus state
		const Wrapper: React.FC = () => {
			const [ duplicateStatus, setDuplicateStatus ] = React.useState(
				'is_duplicate'
			);
			return (
				<DuplicateStatus
					duplicateStatus={ duplicateStatus }
					onDuplicateStatusChange={ ( value ) => {
						baseProps.onDuplicateStatusChange( value );
						setDuplicateStatus( value );
					} }
					readOnly={ false }
				/>
			);
		};

		render( <Wrapper /> );

		const isDuplicateRadio = screen.getByLabelText( 'It was a duplicate' );
		const isNotDuplicateRadio = screen.getByLabelText(
			'It was not a duplicate'
		);

		// Click multiple times rapidly - only changes should trigger onChange
		fireEvent.click( isDuplicateRadio ); // Should not change since already selected
		fireEvent.click( isNotDuplicateRadio ); // Should change from 'is_duplicate' to 'is_not_duplicate'

		expect( baseProps.onDuplicateStatusChange ).toHaveBeenCalledTimes( 1 );
		expect( baseProps.onDuplicateStatusChange ).toHaveBeenCalledWith(
			'is_not_duplicate'
		);
	} );

	it( 'maintains accessibility attributes', () => {
		render( <DuplicateStatus { ...baseProps } /> );

		const isDuplicateRadio = screen.getByLabelText( 'It was a duplicate' );
		const isNotDuplicateRadio = screen.getByLabelText(
			'It was not a duplicate'
		);

		// Check that radio buttons have proper accessibility attributes
		expect( isDuplicateRadio ).toHaveAttribute( 'type', 'radio' );
		expect( isNotDuplicateRadio ).toHaveAttribute( 'type', 'radio' );

		// Check that they are part of the same radio group
		expect( isDuplicateRadio ).toHaveAttribute( 'name' );
		expect( isNotDuplicateRadio ).toHaveAttribute( 'name' );
		expect( isDuplicateRadio.getAttribute( 'name' ) ).toBe(
			isNotDuplicateRadio.getAttribute( 'name' )
		);
	} );
} );
