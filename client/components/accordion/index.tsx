/**
 * External dependencies
 */
import clsx from 'clsx';
import React, { forwardRef } from 'react';

/**
 * Internal dependencies
 */
import type { AccordionProps } from './types';
import './style.scss';

/**
 * `Accordion` expands and collapses multiple sections of content.
 *
 * ```jsx
 * import { Accordion, AccordionBody, AccordionRow } from '@wordpress/components';
 * import { more } from '@wordpress/icons';
 *
 * const MyAccordion = () => (
 * 	<Accordion header="My Accordion">
 * 		<AccordionBody title="My Block Settings" icon={ more } initialOpen={ true }>
 * 			<AccordionRow>My Accordion Inputs and Labels</AccordionRow>
 * 		</AccordionBody>
 * 	</Accordion>
 * );
 * ```
 */
const Accordion = forwardRef< HTMLDivElement, AccordionProps >(
	( { className, children }, ref ) => {
		const classNames = clsx( className, 'wcpay-accordion' );
		return (
			<div className={ classNames } ref={ ref }>
				{ children }
			</div>
		);
	}
);

Accordion.displayName = 'Accordion';

export default Accordion;
