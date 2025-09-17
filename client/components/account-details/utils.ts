/** @format **/

/**
 * External dependencies
 */
import { published, caution, error, info } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { ChipType } from 'wcpay/components/chip';

export const getChipTypeFromColor = (
	color: 'green' | 'yellow' | 'red'
): ChipType => {
	switch ( color ) {
		case 'green':
			return 'success';
		case 'yellow':
			return 'warning';
		case 'red':
			return 'alert';
		default:
			return 'primary';
	}
};

export const getIconByName = (
	iconName?: 'published' | 'caution' | 'error'
) => {
	const iconMap = {
		published: published,
		caution: caution,
		error: error,
		info: info,
	};

	return iconMap[ iconName ?? 'info' ];
};
