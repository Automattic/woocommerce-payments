/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

type WPUser = {
	id: number;
	username: string;
	name: string;
	first_name: string;
	nickname: string;
	last_name: string;
	email: string;
	locale: string;
};

export const useCurrentWpUser = (): {
	user?: WPUser;
	displayName?: string;
	isLoading: boolean;
} => {
	const { user, isLoading } = useSelect( ( select ) => {
		const { getCurrentUser, isResolving } = select( 'core' );
		return {
			user: getCurrentUser() as WPUser | undefined,
			isLoading: !! isResolving( 'getCurrentUser' ),
		};
	} );

	const displayName = user?.first_name || user?.nickname || user?.name;

	return { user, displayName, isLoading };
};
