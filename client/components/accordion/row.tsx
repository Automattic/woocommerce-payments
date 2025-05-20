/**
 * External dependencies
 */
import clsx from 'clsx';
import React, { forwardRef } from 'react';

/**
 * Internal dependencies
 */
import type { AccordionRowProps } from './types';
import './style.scss';
/**
 * `AccordionRow` is a generic container for rows within a `AccordionBody`.
 * It is a flex container with a top margin for spacing.
 */
const AccordionRow = forwardRef< HTMLDivElement, AccordionRowProps >(
	( { className, children }, ref ) => {
		return (
			<div
				className={ clsx( 'wcpay-accordion__row', className ) }
				ref={ ref }
			>
				{ children }
			</div>
		);
	}
);

AccordionRow.displayName = 'AccordionRow';

export default AccordionRow;
