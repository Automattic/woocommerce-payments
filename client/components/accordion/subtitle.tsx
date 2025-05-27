/**
 * External dependencies
 */
import React, { forwardRef } from 'react';

/**
 * Internal dependencies
 */
import type { AccordionSubtitleProps } from './types';
import './style.scss';

const AccordionSubtitle = forwardRef< HTMLDivElement, AccordionSubtitleProps >(
	( { children }, ref ) => {
		if ( ! children ) {
			return null;
		}

		return (
			<div className="wcpay-accordion__subtitle" ref={ ref }>
				{ children }
			</div>
		);
	}
);

AccordionSubtitle.displayName = 'AccordionSubtitle';

export default AccordionSubtitle;
