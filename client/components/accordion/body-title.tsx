/**
 * External dependencies
 */
import React, { forwardRef } from 'react';
/**
 * WordPress dependencies
 */
import { chevronUp, chevronDown } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import type { AccordionBodyTitleProps } from './types';
import type { WordPressComponentProps } from '@wordpress/components/ui/context/wordpress-component';
import { Button, Icon } from 'wcpay/components/wp-components-wrapped';
import './style.scss';

const AccordionBodyTitle = forwardRef<
	HTMLButtonElement,
	WordPressComponentProps< AccordionBodyTitleProps, 'button' >
>( ( { isOpened, icon, title, ...props }, ref ) => {
	if ( ! title ) {
		return null;
	}

	return (
		<h2 className="wcpay-accordion__body-title">
			<Button
				className="wcpay-accordion__body-toggle"
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
} );

AccordionBodyTitle.displayName = 'AccordionBodyTitle';

export default AccordionBodyTitle;
