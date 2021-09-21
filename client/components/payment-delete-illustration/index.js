/** @format */
/**
 * External dependencies
 */
import Gridicon from 'gridicons';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './styles.scss';

const PaymentDeleteIllustration = ( { hasBorder, Icon = () => null } ) => {
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
				<Gridicon
					icon="cross-circle"
					className="payment-delete-illustration__payment-cross-icon"
				/>
			</div>
		</div>
	);
};

export default PaymentDeleteIllustration;
