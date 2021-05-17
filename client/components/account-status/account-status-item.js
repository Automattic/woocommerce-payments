/** @format */

/**
 * External dependencies
 */
import { Flex, FlexBlock, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */

const AccountStatusItem = ( props ) => {
	const { label, value } = props;

	return (
		<Flex
			direction={ 'row' }
			align={ 'center' }
			justify={ 'left' }
			gap={ 3 }
			className={ 'woocommerce-account-status-item' }
		>
			<FlexItem className="woocommerce-account-status-item__label">
				{ label }
			</FlexItem>
			<FlexBlock className="woocommerce-account-status-item__value">
				{ value }
			</FlexBlock>
		</Flex>
	);
};

export default AccountStatusItem;
