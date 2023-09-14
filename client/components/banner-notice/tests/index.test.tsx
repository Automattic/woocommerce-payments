/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { mocked } from 'ts-jest/utils';
import { speak } from '@wordpress/a11y';

/**
 * Internal dependencies
 */
import BannerNotice from '../';

jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ) );

describe( 'BannerNotice', () => {
	beforeEach( () => {
		mocked( speak ).mockClear();
	} );

	it( 'should match snapshot', () => {
		const onClick = jest.fn();
		const { container } = render(
			<BannerNotice
				status="success"
				icon={ <span>Custom Icon</span> }
				actions={ [
					{ label: 'More information', url: 'https://example.com' },
					{ label: 'Cancel', onClick },
					{ label: 'Submit', onClick, variant: 'primary' },
				] }
			>
				Example
			</BannerNotice>
		);

		expect( container ).toMatchSnapshot();
	} );

	it( 'should default to info status', () => {
		const {
			container: { firstChild },
		} = render( <BannerNotice>FYI</BannerNotice> );

		expect( firstChild ).toHaveClass( 'is-info' );
	} );

	/*****************	 */

	it( 'calls action onClick when clicked', () => {
		const onClick = jest.fn();
		render(
			<BannerNotice actions={ [ { label: 'Action', onClick } ] }>
				Notice with Action
			</BannerNotice>
		);

		user.click( screen.getByText( 'Action' ) );

		expect( onClick ).toHaveBeenCalled();
	} );

	it( 'calls onRemove when dismiss button is clicked', () => {
		const onRemove = jest.fn();
		render(
			<BannerNotice onRemove={ onRemove }>
				Dismissible Notice
			</BannerNotice>
		);

		user.click( screen.getByLabelText( 'Dismiss this notice' ) );

		expect( onRemove ).toHaveBeenCalled();
	} );

	describe( 'useSpokenMessage', () => {
		it( 'should speak the given message', () => {
			render( <BannerNotice>FYI</BannerNotice> );

			expect( speak ).toHaveBeenCalledWith( 'FYI', 'polite' );
		} );

		it( 'should speak the given message by implicit politeness by status', () => {
			render( <BannerNotice status="error">Uh oh!</BannerNotice> );

			expect( speak ).toHaveBeenCalledWith( 'Uh oh!', 'assertive' );
		} );

		it( 'should coerce a message to a string', () => {
			render(
				<BannerNotice>
					With <em>emphasis</em> this time.
				</BannerNotice>
			);

			expect( speak ).toHaveBeenCalledWith(
				'With <em>emphasis</em> this time.',
				'polite'
			);
		} );

		it( 'should not re-speak an effectively equivalent element message', () => {
			const { rerender } = render(
				<BannerNotice>Duplicated notice message.</BannerNotice>
			);
			rerender( <BannerNotice>Duplicated notice message.</BannerNotice> );

			expect( speak ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
