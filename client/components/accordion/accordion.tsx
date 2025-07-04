/**
 * External dependencies
 */
import clsx from 'clsx';
import React, { forwardRef } from 'react';

/**
 * Internal dependencies
 */
import type { AccordionProps, AccordionBodyProps } from './types';
import AccordionBody from './body';
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
	(
		{ className, children, highDensity = false, defaultExpanded = false },
		ref
	) => {
		const classNames = clsx( className, 'wcpay-accordion', {
			'is-high-density': highDensity,
		} );

		const childrenWithProps = React.Children.map( children, ( child ) => {
			if (
				React.isValidElement< AccordionBodyProps >( child ) &&
				child.type === AccordionBody
			) {
				return React.cloneElement( child, {
					initialOpen: defaultExpanded,
				} );
			}
			return child;
		} );

		return (
			<div className={ classNames } ref={ ref }>
				{ childrenWithProps }
			</div>
		);
	}
);

Accordion.displayName = 'Accordion';

export default Accordion;
