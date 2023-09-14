/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import BannerActions from '..';

const mockHandleDontShowAgainOnClick = jest.fn();

describe( 'BannerActions', () => {
	it( 'renders', () => {
		const { container: bannerActionsComponent } = render(
			<BannerActions
				handleDontShowAgainOnClick={ mockHandleDontShowAgainOnClick }
			/>
		);

		expect( bannerActionsComponent ).toMatchSnapshot();
	} );
} );
