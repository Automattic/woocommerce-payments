/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import CardNotice from '../';
import { Button } from '@wordpress/components';

describe( 'CardNotice component', () => {
	test( 'should render child', () => {
		const noticeText = 'Notice text';
		const container = render( <CardNotice>{ noticeText }</CardNotice> );

		expect( screen.getByText( noticeText ) ).toBeInTheDocument();
		expect( container ).toMatchSnapshot();
	} );

	test( 'should render actions', () => {
		const buttonText = 'Notice text';

		const actions = <Button>{ buttonText }</Button>;
		const container = render(
			<CardNotice actions={ actions }>{ 'Notice text' }</CardNotice>
		);

		expect(
			screen.getByRole( 'button', {
				name: buttonText,
			} )
		).toBeInTheDocument();
		expect( container ).toMatchSnapshot();
	} );
} );
