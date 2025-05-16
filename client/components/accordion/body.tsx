/**
 * External dependencies
 */
import clsx from 'clsx';
import React, { useEffect, useRef, useState, forwardRef } from 'react';
/**
 * WordPress dependencies
 */
import { useMergeRefs } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import type { AccordionBodyProps } from './types';
import './style.scss';
import AccordionTitle from './title';

const useUpdateEffect = (
	effect: () => void | ( () => void ),
	deps: React.DependencyList
) => {
	const isInitialMount = useRef( true );
	useEffect( () => {
		if ( isInitialMount.current ) {
			isInitialMount.current = false;
		} else {
			return effect();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, deps );
};

const AccordionBody = forwardRef< HTMLDivElement, AccordionBodyProps >(
	(
		{
			buttonProps = {},
			children,
			className,
			icon,
			initialOpen,
			// eslint-disable-next-line @typescript-eslint/no-empty-function
			onToggle = () => {},
			opened,
			title,
			subtitle,
			md = true,
			lg = false,
			scrollAfterOpen = true,
		},
		ref
	) => {
		const defaultOpenState = initialOpen !== undefined ? initialOpen : true;
		const [ internalOpened, setInternalOpened ] = useState(
			opened !== undefined ? opened : defaultOpenState
		);
		const isControlled = opened !== undefined;
		const isOpened = isControlled ? opened : internalOpened;
		const setIsOpened = isControlled ? onToggle : setInternalOpened;
		const nodeRef = useRef< HTMLElement >( null );

		const handleOnToggle = ( event: React.MouseEvent ) => {
			event.preventDefault();
			const next = ! isOpened;
			setIsOpened( next );
		};

		// Ref is used so that the effect does not re-run upon scrollAfterOpen changing value.
		const scrollAfterOpenRef = useRef< boolean | undefined >();
		scrollAfterOpenRef.current = scrollAfterOpen;
		// Runs after initial render.
		useUpdateEffect( () => {
			if (
				isOpened &&
				scrollAfterOpenRef.current &&
				nodeRef.current?.scrollIntoView
			) {
				/*
				 * Scrolls the content into view when visible.
				 * This improves the UX when there are multiple stacking <AccordionBody />
				 * components in a scrollable container.
				 */
				nodeRef.current.scrollIntoView( {
					inline: 'nearest',
					block: 'nearest',
					behavior: 'smooth',
				} );
			}
		}, [ isOpened, 'smooth' ] );

		const classes = clsx( 'wcpay-accordion__body', className, {
			'is-opened': isOpened,
		} );

		return (
			<div className={ classes } ref={ useMergeRefs( [ nodeRef, ref ] ) }>
				<AccordionTitle
					icon={ icon }
					isOpened={ Boolean( isOpened ) }
					onClick={ handleOnToggle }
					title={ title }
					subtitle={ subtitle }
					md={ md }
					lg={ lg }
					{ ...( buttonProps && { ...buttonProps, ref: undefined } ) }
				/>
				{ typeof children === 'function'
					? children( { opened: Boolean( isOpened ) } )
					: isOpened && children }
			</div>
		);
	}
);

AccordionBody.displayName = 'AccordionBody';

export default AccordionBody;
