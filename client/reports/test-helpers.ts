/** @format */

import { within } from '@testing-library/react';

export const expectPresetButtonBefore = (
	container: HTMLElement,
	firstButtonName: string,
	secondButtonName: string
) => {
	const firstButton = within( container ).getByRole( 'button', {
		name: firstButtonName,
	} );
	const secondButton = within( container ).getByRole( 'button', {
		name: secondButtonName,
	} );
	const buttons = within( container ).getAllByRole( 'button' );

	expect( buttons.indexOf( firstButton ) ).toBeLessThan(
		buttons.indexOf( secondButton )
	);
};
