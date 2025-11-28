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
}

const PromotionalBadge: React.FC< PromotionalBadgeProps > = ( {
	message,
	tooltip,
	type = 'success',
	tooltipLabel = __( 'More information', 'woocommerce-payments' ),
} ) => {
	const classNames = clsx(
		'chip',
		`chip-${ type }`,
		'wcpay-promotional-badge'
	);

	return (
		<span className={ classNames }>
			{ message }
			<ClickTooltip
				buttonIcon={ <InfoOutlineIcon /> }
				buttonLabel={ tooltipLabel }
				content={ tooltip }
			/>
		</span>
	);
};

export default PromotionalBadge;
