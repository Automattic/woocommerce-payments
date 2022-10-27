/** @format */
/**
 * External dependencies
 */
import React from 'react';
import CrossCircleIcon from 'gridicons/dist/cross-circle';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './styles.scss';

const PaymentDeleteIllustration: React.FunctionComponent< {
	hasBorder?: boolean;
	icon?: ( { className }: { className: string } ) => JSX.Element;
} > = ( { hasBorder, icon: Icon = () => null } ): JSX.Element => {
	return (
		<div className="payment-delete-illustration__wrapper">
			<div className="payment-delete-illustration__illustrations">
				<Icon
					className={ classNames(
						'payment-delete-illustration__payment-icon',
						{
							'has-border': hasBorder,
						}
					) }
				/>
				<CrossCircleIcon className="payment-delete-illustration__payment-cross-icon" />
			</div>
		</div>
	);
};

export default PaymentDeleteIllustration;
