/** @format */

/**
 * External dependencies
 */
import {
	Flex,
	FlexBlock,
	FlexItem,
} from 'wcpay/components/wp-components-wrapped';

/**
 * Internal dependencies
 */

const AccountStatusItem = ( { label, align, value, children } ) => {
	return (
		<Flex
			direction={ 'row' }
			align={ align || 'center' }
			justify={ 'left' }
			gap={ 3 }
			className={ 'woocommerce-account-status-item' }
		>
			<FlexItem className={ 'item-label' }>{ label }</FlexItem>
			<FlexBlock className={ 'item-value' }>
				{ children || value || null }
			</FlexBlock>
		</Flex>
	);
};

export default AccountStatusItem;
