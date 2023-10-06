/** @format */

/**
 * External Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import React, { useEffect, useState } from 'react';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import { useManualCapture } from 'wcpay/data';
import { HoverTooltip } from 'components/tooltip';
import './style.scss';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

const LoadableCheckboxControl = ( {
	label,
	checked = false,
	disabled = false,
	onChange,
	hideLabel = false,
	isAllowingManualCapture = false,
	isSetupRequired = false,
	setupTooltip = '',
	delayMsOnCheck = 0,
	delayMsOnUncheck = 0,
	needsAttention = false,
} ) => {
	const [ isLoading, setLoading ] = useState( false );
	const [ checkedState, setCheckedState ] = useState( checked );
	const [ isManualCaptureEnabled ] = useManualCapture();

	const handleOnChange = ( status ) => {
		const timeout = status ? delayMsOnCheck : delayMsOnUncheck;
		if ( timeout > 0 ) {
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
			{ ( isManualCaptureEnabled && ! isAllowingManualCapture ) ||
			isSetupRequired ||
			needsAttention ? (
				<div
					className="loadable-checkbox__icon"
					style={ { marginRight: '16px' } }
				>
					<HoverTooltip
						content={ setupTooltip }
						className="wcpay-tooltip__tooltip--dark"
					>
						<div>
							<NoticeOutlineIcon
								style={ {
									color: '#F0B849',
									fill: 'currentColor',
									marginBottom: '-5px',
								} }
								size={ 20 }
							/>
							<div
								className="loadable-checkbox__icon-warning"
								data-testid="loadable-checkbox-icon-warning"
							>
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
					</HoverTooltip>
				</div>
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
