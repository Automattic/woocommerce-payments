/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import RefundStatus from '../refund-status';

describe( 'RefundStatus', () => {
	const baseProps = {
		refundStatus: 'refund_has_been_issued',
		onRefundStatusChange: jest.fn(),
		readOnly: false,
	};

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders refund status heading', () => {
		render( <RefundStatus { ...baseProps } /> );
		expect( screen.getByText( 'Refund status' ) ).toBeInTheDocument();
	} );

	it( 'renders both radio options', () => {
		render( <RefundStatus { ...baseProps } /> );
		expect(
			screen.getByLabelText( 'Refund has been issued' )
		).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Refund was not owed' )
		).toBeInTheDocument();
	} );

	it( 'selects the correct radio option based on refundStatus prop', () => {
		render( <RefundStatus { ...baseProps } /> );
		const refundIssuedRadio = screen.getByLabelText(
			'Refund has been issued'
		);
		const refundNotOwedRadio = screen.getByLabelText(
			'Refund was not owed'
		);

		expect( refundIssuedRadio ).toBeChecked();
		expect( refundNotOwedRadio ).not.toBeChecked();
	} );

	it( 'selects refund was not owed when that status is provided', () => {
		render(
			<RefundStatus { ...baseProps } refundStatus="refund_was_not_owed" />
		);
		const refundIssuedRadio = screen.getByLabelText(
			'Refund has been issued'
		);
		const refundNotOwedRadio = screen.getByLabelText(
			'Refund was not owed'
		);

		expect( refundIssuedRadio ).not.toBeChecked();
		expect( refundNotOwedRadio ).toBeChecked();
	} );

	it( 'calls onRefundStatusChange when radio options are clicked', () => {
		render( <RefundStatus { ...baseProps } /> );

		const refundNotOwedRadio = screen.getByLabelText(
			'Refund was not owed'
		);
		fireEvent.click( refundNotOwedRadio );

		expect( baseProps.onRefundStatusChange ).toHaveBeenCalledWith(
			'refund_was_not_owed'
		);
	} );

	it( 'calls onRefundStatusChange when refund has been issued is clicked', () => {
		render(
			<RefundStatus { ...baseProps } refundStatus="refund_was_not_owed" />
		);

		const refundIssuedRadio = screen.getByLabelText(
			'Refund has been issued'
		);
		fireEvent.click( refundIssuedRadio );

		expect( baseProps.onRefundStatusChange ).toHaveBeenCalledWith(
			'refund_has_been_issued'
		);
	} );

	it( 'disables radio options when readOnly is true', () => {
		render( <RefundStatus { ...baseProps } readOnly={ true } /> );

		const refundIssuedRadio = screen.getByLabelText(
			'Refund has been issued'
		);
		const refundNotOwedRadio = screen.getByLabelText(
			'Refund was not owed'
		);

		expect( refundIssuedRadio ).toBeDisabled();
		expect( refundNotOwedRadio ).toBeDisabled();
	} );

	it( 'enables radio options when readOnly is false', () => {
		render( <RefundStatus { ...baseProps } readOnly={ false } /> );

		const refundIssuedRadio = screen.getByLabelText(
			'Refund has been issued'
		);
		const refundNotOwedRadio = screen.getByLabelText(
			'Refund was not owed'
		);

		expect( refundIssuedRadio ).not.toBeDisabled();
		expect( refundNotOwedRadio ).not.toBeDisabled();
	} );

	it( 'enables radio options when readOnly is not provided', () => {
		render( <RefundStatus { ...baseProps } /> );

		const refundIssuedRadio = screen.getByLabelText(
			'Refund has been issued'
		);
		const refundNotOwedRadio = screen.getByLabelText(
			'Refund was not owed'
		);

		expect( refundIssuedRadio ).not.toBeDisabled();
		expect( refundNotOwedRadio ).not.toBeDisabled();
	} );

	it( 'does not call onRefundStatusChange when readOnly is true', () => {
		render( <RefundStatus { ...baseProps } readOnly={ true } /> );

		const refundNotOwedRadio = screen.getByLabelText(
			'Refund was not owed'
		);
		fireEvent.click( refundNotOwedRadio );

		expect( baseProps.onRefundStatusChange ).not.toHaveBeenCalled();
	} );

	it( 'renders with correct CSS classes', () => {
		render( <RefundStatus { ...baseProps } /> );

		const section = screen
			.getByText( 'Refund status' )
			.closest( 'section' );
		expect( section ).toHaveClass( 'wcpay-dispute-evidence-refund-status' );

		const heading = screen.getByText( 'Refund status' );
		expect( heading ).toHaveClass(
			'wcpay-dispute-evidence-refund-status__heading'
		);

		const fieldGroup = heading.nextElementSibling;
		expect( fieldGroup ).toHaveClass(
			'wcpay-dispute-evidence-refund-status__field-group'
		);
	} );

	it( 'handles multiple rapid clicks correctly', () => {
		// Wrapper to manage refundStatus state
		const Wrapper: React.FC = () => {
			const [ refundStatus, setRefundStatus ] = React.useState(
				'refund_has_been_issued'
			);
			return (
				<RefundStatus
					refundStatus={ refundStatus }
					onRefundStatusChange={ ( value ) => {
						baseProps.onRefundStatusChange( value );
						setRefundStatus( value );
					} }
					readOnly={ false }
				/>
			);
		};

		render( <Wrapper /> );

		const refundNotOwedRadio = screen.getByLabelText(
			'Refund was not owed'
		);
		const refundIssuedRadio = screen.getByLabelText(
			'Refund has been issued'
		);

		// Click multiple times rapidly - only changes should trigger onChange
		fireEvent.click( refundNotOwedRadio ); // Should change from 'refund_has_been_issued' to 'refund_was_not_owed'
		fireEvent.click( refundIssuedRadio ); // Should change from 'refund_was_not_owed' to 'refund_has_been_issued'
		fireEvent.click( refundNotOwedRadio ); // Should change from 'refund_has_been_issued' to 'refund_was_not_owed'

		expect( baseProps.onRefundStatusChange ).toHaveBeenCalledTimes( 3 );
		expect( baseProps.onRefundStatusChange ).toHaveBeenNthCalledWith(
			1,
			'refund_was_not_owed'
		);
		expect( baseProps.onRefundStatusChange ).toHaveBeenNthCalledWith(
			2,
			'refund_has_been_issued'
		);
		expect( baseProps.onRefundStatusChange ).toHaveBeenNthCalledWith(
			3,
			'refund_was_not_owed'
		);
	} );

	it( 'maintains accessibility attributes', () => {
		render( <RefundStatus { ...baseProps } /> );

		const refundIssuedRadio = screen.getByLabelText(
			'Refund has been issued'
		);
		const refundNotOwedRadio = screen.getByLabelText(
			'Refund was not owed'
		);

		// Check that radio buttons have proper accessibility attributes
		expect( refundIssuedRadio ).toHaveAttribute( 'type', 'radio' );
		expect( refundNotOwedRadio ).toHaveAttribute( 'type', 'radio' );

		// Check that they are part of the same radio group
		expect( refundIssuedRadio ).toHaveAttribute( 'name' );
		expect( refundNotOwedRadio ).toHaveAttribute( 'name' );
		expect( refundIssuedRadio.getAttribute( 'name' ) ).toBe(
			refundNotOwedRadio.getAttribute( 'name' )
		);
	} );
} );
