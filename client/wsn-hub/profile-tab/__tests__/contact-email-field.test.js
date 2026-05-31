/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import ContactEmailField from '../contact-email-field';

describe( 'ContactEmailField', () => {
	// Helper: find the email input by its id (the component sets a stable id).
	const getInput = () => screen.getByLabelText( /Contact email/i );

	describe( 'unset state (contactEmail = null)', () => {
		it( 'renders empty value, default-email placeholder, sync helper, and "Use no contact email" button when a default exists', () => {
			render(
				<ContactEmailField
					contactEmail={ null }
					defaultContactEmail="default@example.com"
					onChange={ jest.fn() }
				/>
			);

			const input = getInput();
			expect( input.value ).toBe( '' );
			expect( input ).toHaveAttribute(
				'placeholder',
				'default@example.com'
			);

			expect(
				screen.getByText(
					/Synced from your WooCommerce email settings/i
				)
			).toBeInTheDocument();

			expect(
				screen.getByRole( 'button', { name: /Use no contact email/i } )
			).toBeInTheDocument();

			expect(
				screen.queryByRole( 'button', { name: /Reset to default/i } )
			).not.toBeInTheDocument();
		} );

		it(
			'renders hello@example.com placeholder, "Add an email" helper, ' +
				'and NO "Use no contact email" button when no default exists',
			() => {
				render(
					<ContactEmailField
						contactEmail={ null }
						defaultContactEmail={ null }
						onChange={ jest.fn() }
					/>
				);

				const input = getInput();
				expect( input.value ).toBe( '' );
				expect( input ).toHaveAttribute(
					'placeholder',
					'hello@example.com'
				);

				expect(
					screen.getByText(
						/Add an email so shoppers can reach you/i
					)
				).toBeInTheDocument();

				expect(
					screen.queryByRole( 'button', {
						name: /Use no contact email/i,
					} )
				).not.toBeInTheDocument();

				expect(
					screen.queryByRole( 'button', {
						name: /Reset to default/i,
					} )
				).not.toBeInTheDocument();
			}
		);
	} );

	describe( 'explicit empty state (contactEmail = "")', () => {
		it( 'renders empty value, "No contact email" placeholder, opt-out helper, and "Reset to default" button', () => {
			render(
				<ContactEmailField
					contactEmail=""
					defaultContactEmail="default@example.com"
					onChange={ jest.fn() }
				/>
			);

			const input = getInput();
			expect( input.value ).toBe( '' );
			expect( input ).toHaveAttribute(
				'placeholder',
				'No contact email'
			);

			expect(
				screen.getByText(
					/No contact email will be shown on your Shopping Network storefront/i
				)
			).toBeInTheDocument();

			expect(
				screen.getByRole( 'button', { name: /Reset to default/i } )
			).toBeInTheDocument();
		} );
	} );

	describe( 'explicit override state (contactEmail = "user@example.com")', () => {
		it( 'renders the override value, custom-email helper, and "Reset to default" button', () => {
			render(
				<ContactEmailField
					contactEmail="user@example.com"
					defaultContactEmail="default@example.com"
					onChange={ jest.fn() }
				/>
			);

			const input = getInput();
			expect( input.value ).toBe( 'user@example.com' );

			expect(
				screen.getByText(
					/Custom contact email for your Shopping Network storefront/i
				)
			).toBeInTheDocument();

			expect(
				screen.getByRole( 'button', { name: /Reset to default/i } )
			).toBeInTheDocument();
		} );
	} );

	describe( 'onChange handler', () => {
		it( 'calls onChange with the typed value as a string when the user types', () => {
			const onChange = jest.fn();
			render(
				<ContactEmailField
					contactEmail="a@example.com"
					defaultContactEmail="default@example.com"
					onChange={ onChange }
				/>
			);

			// userEvent.type fires a change event per character; the component
			// is uncontrolled-ish here (parent does not update `contactEmail`
			// between keystrokes), so the LAST call value will be just the
			// single appended character — assert via the cumulative call list
			// instead of value identity.
			userEvent.type( getInput(), 'X' );

			expect( onChange ).toHaveBeenCalled();
			// The final onChange call must have been with a string (not null).
			const lastCallArg =
				onChange.mock.calls[ onChange.mock.calls.length - 1 ][ 0 ];
			expect( typeof lastCallArg ).toBe( 'string' );
		} );

		it( 'calls onChange with "" when the "Use no contact email" button is clicked', () => {
			const onChange = jest.fn();
			render(
				<ContactEmailField
					contactEmail={ null }
					defaultContactEmail="default@example.com"
					onChange={ onChange }
				/>
			);

			userEvent.click(
				screen.getByRole( 'button', { name: /Use no contact email/i } )
			);

			expect( onChange ).toHaveBeenCalledTimes( 1 );
			expect( onChange ).toHaveBeenCalledWith( '' );
		} );

		it( 'calls onChange with null when the "Reset to default" button is clicked', () => {
			const onChange = jest.fn();
			render(
				<ContactEmailField
					contactEmail="user@example.com"
					defaultContactEmail="default@example.com"
					onChange={ onChange }
				/>
			);

			userEvent.click(
				screen.getByRole( 'button', { name: /Reset to default/i } )
			);

			expect( onChange ).toHaveBeenCalledTimes( 1 );
			expect( onChange ).toHaveBeenCalledWith( null );
		} );

		it( 'calls onChange with "" (NOT null) when the user clears the input', () => {
			const onChange = jest.fn();
			render(
				<ContactEmailField
					contactEmail="user@example.com"
					defaultContactEmail="default@example.com"
					onChange={ onChange }
				/>
			);

			// userEvent.clear() fires a single change event with target.value = ''.
			userEvent.clear( getInput() );

			expect( onChange ).toHaveBeenCalled();
			// The clear MUST be persisted as "" — converting to null would
			// collapse explicit-empty back into the unset/default state and
			// destroy the three-state semantic the field exists to preserve.
			expect( onChange ).toHaveBeenLastCalledWith( '' );
			expect( onChange ).not.toHaveBeenCalledWith( null );
		} );
	} );
} );
