/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import CardNotice from '../';
import { Button } from '@wordpress/components';

describe( 'CardNotice component', () => {
	test( 'should render child', () => {
		const noticeText = 'Notice text';
		const { container, getByText } = render(
			<CardNotice>{ noticeText }</CardNotice>
		);

		expect( getByText( noticeText ) ).toBeInTheDocument();
		expect( container ).toMatchSnapshot();
	} );

	test( 'should render a button when actions prop is provided', () => {
		const buttonText = 'Notice text';

		const actions = <Button>{ buttonText }</Button>;
		const { container, getByRole } = render(
			<CardNotice actions={ actions }>{ 'Notice text' }</CardNotice>
		);

		expect(
			getByRole( 'button', {
				name: buttonText,
			} )
		).toBeInTheDocument();
		expect( container ).toMatchSnapshot();
	} );
} );
