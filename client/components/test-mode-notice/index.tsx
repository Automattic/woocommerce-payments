/**
 * External dependencies
 */
import React from 'react';
import { _n, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { isInTestMode } from 'utils';
import BannerNotice from '../banner-notice';

type Section = 'documents' | 'deposits' | 'disputes' | 'payments';

interface Props {
	children: React.ReactNode;
	section?: Section;
	// TODO: Simplify what we pass in here
	actions?: ReadonlyArray< {
		label: string;
		className?: string;
		variant?: Button.Props[ 'variant' ];
		url?: string;
		onClick?: React.MouseEventHandler< HTMLAnchorElement >;
	} >;
}

/**
 * Returns notice details depending on the section provided.
 *
 * @param {string} section The notice message section.
 *
 * @return {string} The specific details the notice is supposed to contain.
 */
export const getDetailsString = ( section: string ): string => {
	return sprintf(
		/* translators: %s: WooPayments */
		_n(
			'%s was in test mode when this order was placed.',
			'%s was in test mode when these orders were placed.',
			'deposits' === section ? 2 : 1,
			'woocommerce-payments'
		),
		'WooPayments'
	);
};

export const TestModeNotice: React.FC< Props > = ( {
	children,
	section,
	actions,
} ) => {
	if ( ! isInTestMode() ) return null;

	// TODO: If this is a details, we should serve an inline banner instead. We should also simplify and tidy up the logic.
	return (
		<BannerNotice
			status="warning"
			icon={ false }
			isDismissible={ false }
			actions={ actions }
		>
			{ children }
		</BannerNotice>
	);
};
