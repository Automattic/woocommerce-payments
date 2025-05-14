/** @format */

/**
 * External dependencies
 */
import React, { useState, useRef } from 'react';
/**
 * WordPress dependencies
 */
import { chevronUp, chevronDown, Icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import './style.scss';

interface AccordionProps {
	title: string;
	children: React.ReactNode;
}

// Simple unique ID generator for SSR-safe IDs
let accordionIdCounter = 0;
function useUniqueId( prefix = 'accordion-content-' ) {
	const idRef = useRef< string | null >( null );
	if ( idRef.current === null ) {
		accordionIdCounter += 1;
		idRef.current = `${ prefix }${ accordionIdCounter }`;
	}
	return idRef.current;
}

const Accordion: React.FC< AccordionProps > = ( { title, children } ) => {
	const [ isExpanded, setIsExpanded ] = useState( false );
	const contentId = useUniqueId();

	const toggleExpand = () => {
		setIsExpanded( ! isExpanded );
	};

	const handleKeyDown = ( e: React.KeyboardEvent< HTMLDivElement > ) => {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			e.preventDefault();
			toggleExpand();
		}
	};

	return (
		<div className={ `accordion-item${ isExpanded ? ' active' : '' }` }>
			<div
				className="accordion-header"
				onClick={ toggleExpand }
				role="button"
				tabIndex={ 0 }
				aria-expanded={ isExpanded }
				aria-controls={ contentId }
				onKeyDown={ handleKeyDown }
				style={ {
					cursor: 'pointer',
					display: 'flex',
					alignItems: 'center',
					width: '100%',
				} }
			>
				<div className="accordion-title" style={ { flex: 1 } }>
					{ title }
				</div>
				<div className="arrow-container">
					<Icon
						icon={ isExpanded ? chevronUp : chevronDown }
						size={ 24 }
					/>
				</div>
			</div>
			<div
				id={ contentId }
				className="accordion-content"
				aria-hidden={ ! isExpanded }
				style={ { display: isExpanded ? 'block' : 'none' } }
			>
				{ children }
			</div>
		</div>
	);
};

export default Accordion;
