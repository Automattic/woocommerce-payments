/**
 * External dependencies
 */
import qit from '/qitHelpers';

export const activateTheme = async ( slug: string ) => {
	try {
		await qit.wp( `theme is-installed ${ slug }`, true );
	} catch ( error ) {
		await qit.wp( `theme install ${ slug } --force`, true );
	}

	await qit.wp( `theme activate ${ slug }`, true );
};
