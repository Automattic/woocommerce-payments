/**
 * External dependencies
 */
import React from 'react';
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import BannerNotice from '../';

describe( 'Info BannerNotices renders', () => {
	test( 'with dismiss', () => {
		const { container } = render(
			<BannerNotice
				status="info"
				className="wcpaytest-notice"
				children={ 'Test notice content' }
				isDismissible={ true }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'with dismiss and icon', () => {
		const { container } = render(
			<BannerNotice
				status="info"
				icon
				children={ 'Test notice content' }
				isDismissible={ true }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'with dismiss and icon and actions', () => {
		const { container } = render(
			<BannerNotice
				status="info"
				children={ 'Test notice content' }
				isDismissible={ true }
				actions={ [
					{
						label: 'Button',
						onClick: jest.fn(),
					},
					{
						label: 'URL',
						url: 'https://wordpress.com',
					},
				] }
			/>
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'without dismiss and icon', () => {
		const { container } = render(
			<BannerNotice
				status="info"
				children={ 'Test notice content' }
				isDismissible={ false }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );
} );

describe( 'Action click triggers callback', () => {
	test( 'with dismiss and icon and actions', () => {
		const onClickMock = jest.fn();
		const { getByText } = render(
			<BannerNotice
				status="warning"
				children={ 'Test notice content' }
				isDismissible={ true }
				actions={ [
					{
						label: 'Button',
						onClick: onClickMock,
					},
					{
						label: 'URL',
						url: 'https://wordpress.com',
					},
				] }
			/>
		);

		fireEvent.click( getByText( 'Button' ) );
		expect( onClickMock ).toHaveBeenCalled();
	} );

	test( 'With icon and multiple button actions', () => {
		const onButtonClickOne = jest.fn();
		const onButtonClickTwo = jest.fn();
		const { getByText } = render(
			<BannerNotice
				status="warning"
				children={ 'Test notice content' }
				isDismissible={ true }
				actions={ [
					{
						label: 'Button one',
						onClick: onButtonClickOne,
					},
					{
						label: 'Button two',
						onClick: onButtonClickTwo,
					},
				] }
			/>
		);

		expect( onButtonClickOne ).not.toHaveBeenCalled();
		expect( onButtonClickTwo ).not.toHaveBeenCalled();

		// Click Button 1
		fireEvent.click( getByText( 'Button one' ) );
		expect( onButtonClickOne ).toHaveBeenCalled();
		expect( onButtonClickTwo ).not.toHaveBeenCalled();

		// Click Button 1
		fireEvent.click( getByText( 'Button two' ) );
		expect( onButtonClickTwo ).toHaveBeenCalled();
	} );
} );

describe( 'Dismiss click triggers callback', () => {
	test( 'with dismiss and icon and actions', () => {
		const onDismissMock = jest.fn();
		const { getByLabelText } = render(
			<BannerNotice
				status="error"
				children={ 'Test notice content' }
				isDismissible={ true }
				onRemove={ onDismissMock }
			/>
		);

		fireEvent.click( getByLabelText( 'Dismiss this notice' ) );
		expect( onDismissMock ).toHaveBeenCalled();
	} );
} );
