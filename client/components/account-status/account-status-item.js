/** @format */

/**
 * Internal dependencies
 */
import { Flex } from 'wcpay/components/wp-components-wrapped/components/flex';
import { FlexBlock } from 'wcpay/components/wp-components-wrapped/components/flex-block';
import { FlexItem } from 'wcpay/components/wp-components-wrapped/components/flex-item';

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
