/**
 * External dependencies
 */
import React from 'react';
/**
 * WordPress dependencies
 */
import { chevronUp, chevronDown } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import type { AccordionBodyTitleProps } from './types';
import type { WordPressComponentProps } from '@wordpress/components/ui/context/wordpress-component';
import { Button, Icon } from '@wordpress/components';
import './style.scss';

const AccordionBodyTitle = ( {
	isOpened,
	icon,
	title,
	ref,
	...props
}: WordPressComponentProps< AccordionBodyTitleProps, 'button' > ) => {
	if ( ! title ) {
		return null;
	}

	return (
		<h2 className="wcpay-accordion__body-title">
			<Button
				// @ts-expect-error: Suppressing Module '"@wordpress/components"' has no exported member '__next40pxDefaultSize'.
				__next40pxDefaultSize
				className="wcpay-accordion__body-toggle"
				aria-expanded={ isOpened }
				ref={ ref }
				{ ...props }
			>
				{ /*
				Firefox + NVDA don't announce aria-expanded because the browser
				repaints the whole element, so this wrapping span hides that.
			*/ }
				<span aria-hidden="true">
					<Icon
						className="wcpay-accordion__arrow"
						icon={ isOpened ? chevronUp : chevronDown }
					/>
				</span>
				{ title }
				{ icon && (
					<Icon
						icon={ icon }
						className="wcpay-accordion__icon"
						size={ 20 }
					/>
				) }
			</Button>
		</h2>
	);
};

export default AccordionBodyTitle;
