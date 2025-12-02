/** @format */

/**
 * External dependencies
 */
import React from 'react';
import clsx from 'clsx';
import InfoOutlineIcon from 'gridicons/dist/info-outline';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ChipType } from 'wcpay/components/chip';
import { ClickTooltip } from 'wcpay/components/tooltip';
import './style.scss';

interface PromotionalBadgeProps {
	/** The badge text displayed in the chip */
	message: string;
	/** The tooltip content shown when clicking the info icon */
	tooltip: string;
	/** The chip type/color (defaults to "success") */
	type?: ChipType;
	/** Accessible label for the tooltip button */
	tooltipLabel?: string;
	/** Optional terms & conditions URL - when provided, a link is appended to the tooltip */
	tcUrl?: string;
	/** Optional terms & conditions link label */
	tcLabel?: string;
}

const PromotionalBadge: React.FC< PromotionalBadgeProps > = ( {
	message,
	tooltip,
	type = 'success',
	tooltipLabel = __( 'More information', 'woocommerce-payments' ),
	tcUrl,
	tcLabel,
} ) => {
	const classNames = clsx(
		'chip',
		`chip-${ type }`,
		'wcpay-promotional-badge'
	);

	// Use backend-provided tc_label when available, otherwise fall back to default.
	const tcLinkLabel = tcLabel || __( 'See terms', 'woocommerce-payments' );

	// Build tooltip content with optional T&C link.
	const tooltipContent = tcUrl ? (
		<>
			{ tooltip }{ ' ' }
			<a href={ tcUrl } target="_blank" rel="noopener noreferrer">
				{ tcLinkLabel }
			</a>
		</>
	) : (
		tooltip
	);

	return (
		<span className={ classNames }>
			{ message }
			<ClickTooltip
				buttonIcon={ <InfoOutlineIcon /> }
				buttonLabel={ tooltipLabel }
				content={ tooltipContent }
			/>
		</span>
	);
};

export default PromotionalBadge;
