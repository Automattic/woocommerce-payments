/**
 * External dependencies
 */
import * as React from 'react';
import classNames from 'classnames';
import type { ImgHTMLAttributes, FunctionComponent } from 'react';

/**
 * Internal dependencies
 */
import type { PaymentMethodDefinition } from '../types';

type ReactImgFuncComponent = FunctionComponent<
	ImgHTMLAttributes< HTMLImageElement >
>;

interface IconComponentOptions {
	hasBorder?: boolean;
}

/**
 * Creates an icon component from a given path and label
 *
 * @param iconPath - Path to the icon asset
 * @param label   - Pre-translated text to use as alt text for the icon.
 *                 This should be already translated when passed to this function.
 * @param options - Additional options for the icon component
 */
export const createIconComponent = (
	iconPath: string,
	label: string,
	options: IconComponentOptions = { hasBorder: true }
): ReactImgFuncComponent => {
	return ( { className, ...props } ): JSX.Element => (
		<img
			className={ classNames(
				'payment-method__icon',
				options.hasBorder ? '' : 'no-border',
				className
			) }
			src={ iconPath }
			alt={ label }
			{ ...props }
		/>
	);
};

/**
 * Creates an icon component from a payment method definition.
 * Uses the pre-translated title from the definition as the alt text.
 * Note: Payment method titles are translated in their PHP definition files.
 *
 * @param def     - Payment method definition containing the icon path and pre-translated title
 * @param options - Additional options for the icon component
 */
export const createPaymentMethodIconComponent = (
	def: PaymentMethodDefinition,
	options?: IconComponentOptions
): ReactImgFuncComponent => {
	return createIconComponent( def.settingsIcon, def.title, options );
};
