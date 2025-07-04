/**
 * External dependencies
 */
import React, { forwardRef } from 'react';
import clsx from 'clsx';
import { chevronUp, chevronDown } from '@wordpress/icons';
import { Icon } from 'wcpay/components/wp-components-wrapped/components/icon';
import { Button } from 'wcpay/components/wp-components-wrapped/components/button';

/**
 * Internal dependencies
 */
import type { AccordionTitleProps } from './types';
import type { WordPressComponentProps } from '@wordpress/components/ui/context/wordpress-component';
import AccordionSubtitle from './subtitle';
import './style.scss';

const AccordionTitle = forwardRef<
	HTMLButtonElement,
	WordPressComponentProps< AccordionTitleProps, 'button', false >
>(
	(
		{ isOpened, icon, title, subtitle, md = true, lg = false, ...props },
		ref
	) => {
		if ( ! title ) {
			return null;
		}

		return (
			<h2 className="wcpay-accordion__body-title">
				<Button
					className={ clsx( 'wcpay-accordion__body-toggle', {
						'is-md': md,
						'is-lg': lg,
					} ) }
					// @ts-expect-error: there is a type discrepancy between the wrapped WP components and the bundled components.
					ref={ ref }
					{ ...props }
				>
					{ /*
				Firefox + NVDA don't announce aria-expanded because the browser
				repaints the whole element, so this wrapping span hides that.
			*/ }
					<span aria-hidden="true">
						<Icon
							// @ts-expect-error: className is not a prop defined in the WP Icon component.
							className="wcpay-accordion__arrow"
							icon={ isOpened ? chevronUp : chevronDown }
						/>
					</span>
					<div className="wcpay-accordion__title-content">
						{ title }
						{ subtitle && (
							<AccordionSubtitle>{ subtitle }</AccordionSubtitle>
						) }
					</div>
					{ icon && (
						<Icon
							icon={ icon }
							// @ts-expect-error: className is not a prop defined in the WP Icon component.
							className="wcpay-accordion__icon"
							size={ 20 }
						/>
					) }
				</Button>
			</h2>
		);
	}
);

AccordionTitle.displayName = 'AccordionTitle';

export default AccordionTitle;
