/** @format */

/**
 * Internal dependencies
 */
import { getTimezoneOffsetString } from 'utils/date-time';

export function getUserTimeZone() {
	return getTimezoneOffsetString();
}
