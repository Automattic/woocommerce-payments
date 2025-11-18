/**
 * External dependencies
 */
import React from 'react';
import { Pill } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { BadgeProps } from './types';
import './style.scss';

/**
 * A generic badge component for displaying status, labels, or promotional information.
 *
 * Based on WooCommerce's StatusBadge but simplified for general use without popover functionality.
 *
 * @param {BadgeProps} props - Component props
 * @return {JSX.Element} The Badge component
 */
const Badge: React.FC< BadgeProps > = ( {
	variant = 'info',
	children,
	className = '',
} ) => {
	const getVariantClass = () => {
		switch ( variant ) {
			case 'success':
				return 'wcpay-badge--success';
			case 'warning':
				return 'wcpay-badge--warning';
			case 'error':
				return 'wcpay-badge--error';
			case 'info':
			default:
				return 'wcpay-badge--info';
		}
	};

	return (
		<Pill
			className={ `wcpay-badge ${ getVariantClass() } ${ className }`.trim() }
		>
			{ children }
		</Pill>
	);
};

export default Badge;
