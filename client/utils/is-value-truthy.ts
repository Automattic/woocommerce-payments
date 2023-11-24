/**
 * Sourced from "@automattic/wpcom-checkout".
 */

export default function isValueTruthy< T >(
	value: T
): value is Exclude< T, null | undefined | false | 0 | '' > {
	return !! value;
}
