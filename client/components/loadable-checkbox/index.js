/** @format */

/**
 * External Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import React, { useEffect, useState } from 'react';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import './style.scss';
import classNames from 'classnames';
import { useManualCapture } from 'wcpay/data';
import { Icon, warning } from '@wordpress/icons';
import Tooltip from '../tooltip';

const LoadableCheckboxControl = ( {
	label,
	checked = false,
	disabled = false,
	onChange,
	hideLabel = false,
	isAllowingManualCapture = false,
	delayMsOnCheck = 0,
	delayMsOnUncheck = 0,
} ) => {
	const [ isLoading, setLoading ] = useState( false );
	const [ checkedState, setCheckedState ] = useState( checked );
	const [ isManualCaptureEnabled ] = useManualCapture();

	const handleOnChange = ( status ) => {
		const timeout = status ? delayMsOnCheck : delayMsOnUncheck;
		if ( 0 < timeout ) {
			setLoading( true );
			setTimeout( () => {
				onChange( status );
				setLoading( false );
			}, timeout );
		} else {
			// Don't show the loading indicator if there's no delay.
			onChange( status );
		}
	};

	useEffect( () => {
		setCheckedState( checked );
	}, [ setCheckedState, checked ] );

	return (
		<div
			className={ classNames(
				'loadable-checkbox',
				hideLabel ? 'label-hidden' : ''
			) }
		>
			{ isLoading && (
				<div className={ 'loadable-checkbox__spinner' }>
					<svg
						width="131px"
						height="131px"
						viewBox="0 0 100 100"
						preserveAspectRatio="xMidYMid"
					>
						<circle
							cx="50"
							cy="50"
							fill="none"
							stroke="#ffffff"
							strokeWidth="12"
							r="32"
							strokeDasharray="150.79644737231007 52.26548245743669"
						>
							<animateTransform
								attributeName="transform"
								type="rotate"
								repeatCount="indefinite"
								dur="1.4492753623188404s"
								values="0 50 50;360 50 50"
								keyTimes="0;1"
							></animateTransform>
						</circle>
					</svg>
				</div>
			) }
			{ isManualCaptureEnabled && ! isAllowingManualCapture ? (
				<Tooltip
					content={ sprintf(
						/* translators: %s: a payment method name. */
						__(
							'%s is not available to your customers when the "manual capture" setting is enabled.',
							'woocommerce-payments'
						),
						label
					) }
				>
					<div className="loadable-checkbox__icon">
						<Icon icon={ warning } fill={ '#ffc83f' } />
						<div className="loadable-checkbox__icon-info">
							<VisuallyHidden>
								{ sprintf(
									/* translators: %s: a payment method name. */
									__(
										'%s cannot be enabled at checkout. Click to expand.',
										'woocommerce-payments'
									),
									label
								) }
							</VisuallyHidden>
						</div>
					</div>
				</Tooltip>
			) : (
				<CheckboxControl
					label={ label }
					checked={ checkedState }
					disabled={ disabled }
					onChange={ ( status ) => handleOnChange( status ) }
				/>
			) }
		</div>
	);
};

export default LoadableCheckboxControl;
