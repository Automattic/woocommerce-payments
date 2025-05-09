/** @format */
/**
 * External dependencies
 */
import React from 'react';
import HelpIcon from 'gridicons/dist/help';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import './styles.scss';

const PaymentConfirmIllustration: React.FunctionComponent< {
	hasBorder?: boolean;
	icon?: ReactImgFuncComponent;
} > = ( { hasBorder, icon: Icon = () => null } ): JSX.Element => {
	return (
		<div className="payment-confirm-illustration__wrapper">
			<div className="payment-confirm-illustration__illustrations">
				<Icon
					className={ clsx(
						'payment-confirm-illustration__payment-icon',
						{
							'has-border': hasBorder,
						}
					) }
				/>
				<HelpIcon className="payment-confirm-illustration__payment-question-mark-icon" />
			</div>
		</div>
	);
};

export default PaymentConfirmIllustration;
