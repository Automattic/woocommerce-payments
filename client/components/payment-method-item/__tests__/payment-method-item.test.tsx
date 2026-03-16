/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethodItem from '..';

const MockIcon = () => <img src="mock-icon.svg" alt="Mock" />;

describe( 'PaymentMethodItem', () => {
	it( 'renders with basic structure', () => {
		const { container } = render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test Method"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup
						Icon={ MockIcon }
						label="Test Method"
					>
						A test description
					</PaymentMethodItem.Subgroup>
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		expect(
			container.querySelector( '.payment-method-item' )
		).toBeInTheDocument();
		expect(
			container.querySelector( '.payment-method-item__row' )
		).toBeInTheDocument();
		expect(
			container.querySelector( '.payment-method-item__checkbox' )
		).toBeInTheDocument();
		expect(
			container.querySelector( '.payment-method-item__text-container' )
		).toBeInTheDocument();
		expect(
			container.querySelector( '.payment-method-item__subgroup' )
		).toBeInTheDocument();
	} );

	it( 'renders checkbox with visually hidden label', () => {
		render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test Method"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup label="Test Method" />
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		expect( screen.getByLabelText( 'Test Method' ) ).toBeInTheDocument();
	} );

	it( 'calls onChange when checkbox is clicked', async () => {
		const onChange = jest.fn();

		render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test Method"
					checked={ false }
					onChange={ onChange }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup label="Test Method" />
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		await userEvent.click( screen.getByLabelText( 'Test Method' ) );
		expect( onChange ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'renders disabled checkbox', () => {
		render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test Method"
					checked={ false }
					disabled={ true }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup label="Test Method" />
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		expect( screen.getByLabelText( 'Test Method' ) ).toBeDisabled();
	} );

	it( 'renders icon in subgroup', () => {
		render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup
						Icon={ MockIcon }
						label="Test"
					/>
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		expect( screen.getByAltText( 'Mock' ) ).toBeInTheDocument();
	} );

	it( 'renders subgroup without icon', () => {
		const { container } = render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup label="Test" />
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		expect(
			container.querySelector( '.payment-method-item__icon' )
		).not.toBeInTheDocument();
	} );

	it( 'renders multiple subgroups', () => {
		const { container } = render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<div>
						<PaymentMethodItem.Subgroup label="First" />
						<PaymentMethodItem.Subgroup label="Second" />
					</div>
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		const subgroups = container.querySelectorAll(
			'.payment-method-item__subgroup'
		);
		expect( subgroups ).toHaveLength( 2 );
	} );

	it( 'renders action slot', () => {
		render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup label="Test" />
					<PaymentMethodItem.Action>
						<button>Click me</button>
					</PaymentMethodItem.Action>
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		expect(
			screen.getByRole( 'button', { name: 'Click me' } )
		).toBeInTheDocument();
	} );

	it( 'separates trailing content from row content', () => {
		const { container } = render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup label="Test" />
				</PaymentMethodItem.Body>
				<div data-testid="trailing-notice">Warning notice</div>
			</PaymentMethodItem>
		);

		const trailingNotice = screen.getByTestId( 'trailing-notice' );
		const row = container.querySelector( '.payment-method-item__row' );

		// Trailing content should NOT be inside the row
		expect( row ).not.toContainElement( trailingNotice );
		// But should be inside the root element
		expect(
			container.querySelector( '.payment-method-item' )
		).toContainElement( trailingNotice );
	} );

	it( 'passes through id and className props', () => {
		const { container } = render(
			<PaymentMethodItem id="test-id" className="custom-class">
				<PaymentMethodItem.Checkbox
					label="Test"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup label="Test" />
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		const root = container.querySelector( '.payment-method-item' );
		expect( root ).toHaveAttribute( 'id', 'test-id' );
		expect( root ).toHaveClass( 'custom-class' );
	} );

	it( 'renders ReactNode as label in subgroup', () => {
		const ComplexLabel = () => (
			<>
				Klarna <span>( Required )</span>
			</>
		);

		render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup label={ <ComplexLabel /> } />
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		// Both desktop and mobile labels should render the complex content
		expect( screen.getAllByText( '( Required )' ) ).toHaveLength( 2 );
	} );

	it( 'does not render description div when subgroup has no children', () => {
		const { container } = render(
			<PaymentMethodItem>
				<PaymentMethodItem.Checkbox
					label="Test"
					checked={ false }
					onChange={ jest.fn() }
				/>
				<PaymentMethodItem.Body>
					<PaymentMethodItem.Subgroup label="Test" />
				</PaymentMethodItem.Body>
			</PaymentMethodItem>
		);

		expect(
			container.querySelector( '.payment-method-item__description' )
		).not.toBeInTheDocument();
	} );
} );
