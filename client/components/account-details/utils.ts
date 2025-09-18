/** @format **/

/**
 * External dependencies
 */
import { published, caution, error, info } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { ChipType } from 'wcpay/components/chip';
import {
	StatusBackgroundColor,
	IconName,
} from 'wcpay/types/account/account-details';

export const getChipTypeFromColor = (
	color: StatusBackgroundColor
): ChipType => {
	switch ( color ) {
		case 'green':
			return 'success';
		case 'yellow':
			return 'warning';
		case 'red':
			return 'alert';
		case 'blue':
			return 'primary';
		case 'gray':
			return 'light';
		default:
			return 'primary';
	}
};

export const getIconByName = ( iconName?: IconName ) => {
	const iconMap = {
		published: published,
		caution: caution,
		error: error,
		info: info,
	};

	return iconMap[ iconName ?? 'info' ];
};
