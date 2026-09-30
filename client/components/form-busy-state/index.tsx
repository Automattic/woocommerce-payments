/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import './style.scss';

interface FormBusyStateProps {
	isBusy: boolean;
	className?: string;
	children: React.ReactNode;
}

/**
 * Signals that a save is in progress, accessibly.
 *
 * Avoids `disabled`/`inert` on purpose: both drop inputs out of the tab order,
 * which is what #4740 wants to keep. Completion isn't announced here — the save
 * already fires a "saved" success notice, which screen readers pick up.
 */
const FormBusyState: React.FC< FormBusyStateProps > = ( {
	isBusy,
	className,
	children,
} ) => (
	<div
		className={ clsx( 'wcpay-form-busy-state', className, {
			'is-busy': isBusy,
		} ) }
		aria-busy={ isBusy }
	>
		{ /* first child so it never becomes `:last-child` and shifts the section margins */ }
		<div
			className="wcpay-form-busy-state__status screen-reader-text"
			role="status"
			aria-live="polite"
		>
			{ isBusy ? __( 'Saving…', 'woocommerce-payments' ) : '' }
		</div>
		{ children }
	</div>
);

export default FormBusyState;
