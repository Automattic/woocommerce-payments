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
import { sanitizeHTML } from 'utils/sanitize';
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

	// Build tooltip content with optional T&C link.
	// Only show the link if both tcUrl and tcLabel are provided.
	// An empty tcLabel signals that the link is already in the description.
	const tooltipContent =
		tcUrl && tcLabel ? (
			<>
				<span
					// eslint-disable-next-line react/no-danger
					dangerouslySetInnerHTML={ sanitizeHTML( tooltip ) }
				/>{ ' ' }
				<a href={ tcUrl } target="_blank" rel="noopener noreferrer">
					{ tcLabel }
				</a>
			</>
		) : (
			<span
				// eslint-disable-next-line react/no-danger
				dangerouslySetInnerHTML={ sanitizeHTML( tooltip ) }
			/>
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
