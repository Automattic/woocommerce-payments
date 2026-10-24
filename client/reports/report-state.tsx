/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { Icon } from '@wordpress/components';

interface ReportStateProps {
	title: string;
	description: React.ReactNode;
	icon: React.ComponentProps< typeof Icon >[ 'icon' ];
	action?: React.ReactNode;
	className?: string;
	descriptionId?: string;
	headingId?: string;
	headingRef?: React.Ref< HTMLHeadingElement >;
	headingTabIndex?: number;
	role?: string;
}

export const ReportState = ( {
	title,
	description,
	icon,
	action,
	className,
	descriptionId,
	headingId,
	headingRef,
	headingTabIndex,
	role,
}: ReportStateProps ): JSX.Element => (
	<div
		className={ [
			'wcpay-reports-state',
			'wcpay-reports-state--illustrated',
			className,
		]
			.filter( Boolean )
			.join( ' ' ) }
		role={ role }
		aria-labelledby={ headingId }
		aria-describedby={ descriptionId }
	>
		<span className="wcpay-reports-state__icon" aria-hidden="true">
			<Icon icon={ icon } size={ 48 } />
		</span>
		<div className="wcpay-reports-state__copy">
			<h2
				id={ headingId }
				ref={ headingRef }
				tabIndex={ headingTabIndex }
			>
				{ title }
			</h2>
			<p id={ descriptionId }>{ description }</p>
		</div>
		{ action }
	</div>
);
