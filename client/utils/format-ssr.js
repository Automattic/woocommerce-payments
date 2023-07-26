/** @format */

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import { decodeEntities } from '@wordpress/html-entities';

const CHECK_MARK = String.fromCharCode( 10004 ); // ✔
const CROSS_MARK = String.fromCharCode( 10060 ); // ❌
const DASH_MARK = '–';

export const formatSsr = ( systemStatus, wcPayData ) => {
	const ssr = `### WordPress Environment ###

WordPress address (URL): ${ systemStatus.environment.site_url }
Site address (URL): ${ systemStatus.environment.home_url }
WC Version: ${ systemStatus.environment.version }
Log Directory Writable: ${
		systemStatus.environment.log_directory_writable
			? CHECK_MARK
			: CROSS_MARK
	}
WP Version: ${ systemStatus.environment.wp_version }
WP Multisite: ${
		systemStatus.environment.wp_multisite ? CHECK_MARK : DASH_MARK
	}
WP Memory Limit: ${ formatSize( systemStatus.environment.wp_memory_limit ) }
WP Debug Mode: ${
		systemStatus.environment.wp_debug_mode ? CHECK_MARK : DASH_MARK
	}
WP Cron: ${ systemStatus.environment.wp_cron ? CHECK_MARK : DASH_MARK }
Language: ${ systemStatus.environment.language }
External object cache: ${
		systemStatus.environment.external_object_cache ? CHECK_MARK : DASH_MARK
	}

### Server Environment ###

Server Info: ${ systemStatus.environment.server_info }
PHP Version: ${ systemStatus.environment.php_version }
PHP Post Max Size: ${ formatSize( systemStatus.environment.php_post_max_size ) }
PHP Time Limit: ${ systemStatus.environment.php_max_execution_time }
PHP Max Input Vars: ${ systemStatus.environment.php_max_input_vars }
cURL Version: ${ systemStatus.environment.curl_version }

SUHOSIN Installed: ${
		systemStatus.environment.suhosin_installed ? CHECK_MARK : DASH_MARK
	}
MySQL Version: ${ systemStatus.environment.mysql_version_string }
Max Upload Size: ${ formatSize( systemStatus.environment.max_upload_size ) }
Default Timezone is UTC: ${
		'UTC' === systemStatus.environment.default_timezone
			? CHECK_MARK
			: CROSS_MARK
	}
fsockopen/cURL: ${
		systemStatus.environment.fsockopen_or_curl_enabled
			? CHECK_MARK
			: CROSS_MARK
	}
SoapClient: ${
		systemStatus.environment.soapclient_enabled ? CHECK_MARK : CROSS_MARK
	}
DOMDocument: ${
		systemStatus.environment.domdocument_enabled ? CHECK_MARK : CROSS_MARK
	}
GZip: ${ systemStatus.environment.gzip_enabled ? CHECK_MARK : CROSS_MARK }
Multibyte String: ${
		systemStatus.environment.mbstring_enabled ? CHECK_MARK : CROSS_MARK
	}
Remote Post: ${
		systemStatus.environment.remote_post_successful
			? CHECK_MARK
			: CROSS_MARK
	}
Remote Get: ${
		systemStatus.environment.remote_get_successful ? CHECK_MARK : CROSS_MARK
	}

### Database ###

WC Database Version: ${ systemStatus.database.wc_database_version }
WC Database Prefix: ${ systemStatus.database.database_prefix }
${ printDatabaseDetails( systemStatus.database ) }
${ printPostTypeCounts( systemStatus.post_type_counts ) }

### Security ###

Secure connection (HTTPS): ${
		systemStatus.security.secure_connection
			? CHECK_MARK
			: CROSS_MARK + '\nYour store is not using HTTPS.'
	}
Hide errors from visitors: ${
		systemStatus.security.hide_errors
			? CHECK_MARK
			: CROSS_MARK + 'Error messages should not be shown to visitors.'
	}

### Active Plugins (${ systemStatus.active_plugins.length }) ###

${ printPlugins( systemStatus.active_plugins, null ) }
### Inactive Plugins (${ systemStatus.inactive_plugins.length }) ###

${ printPlugins( systemStatus.inactive_plugins, null ) }${ printPlugins(
		systemStatus.dropins_mu_plugins.dropins,
		'Dropin Plugins'
	) }${ printPlugins(
		systemStatus.dropins_mu_plugins.mu_plugins,
		'Must Use Plugins'
	) }
### Settings ###

API Enabled: ${ systemStatus.settings.api_enabled ? CHECK_MARK : DASH_MARK }
Force SSL: ${ systemStatus.settings.force_ssl ? CHECK_MARK : DASH_MARK }
Currency: ${ systemStatus.settings.currency } (${ decodeEntities(
		systemStatus.settings.currency_symbol
	) })
Currency Position: ${ systemStatus.settings.currency_position }
Thousand Separator: ${ systemStatus.settings.thousand_separator }
Decimal Separator: ${ systemStatus.settings.decimal_separator }
Number of Decimals: ${ systemStatus.settings.number_of_decimals }
Taxonomies: Product Types: ${ printTerms( systemStatus.settings.taxonomies ) }
Taxonomies: Product Visibility: ${ printTerms(
		systemStatus.settings.product_visibility_terms
	) }
Connected to WooCommerce.com: ${
		'yes' === systemStatus.settings.woocommerce_com_connected
			? CHECK_MARK
			: DASH_MARK
	}

### WC Pages ###

${ printPages( systemStatus.pages ) }
### Theme ###

Name: ${ systemStatus.theme.name }
Version: ${
		systemStatus.theme.version_latest
			? `${ systemStatus.theme.version } (update to version ${ systemStatus.theme.version_latest } is available)`
			: systemStatus.theme.version
	}
Author URL: ${ systemStatus.theme.author_url }
Child Theme: ${
		systemStatus.theme.is_child_theme
			? CHECK_MARK
			: CROSS_MARK +
			  ' - If you are modifying WooCommerce on a parent theme that you did not build personally we recommend using a child theme.'
	}
WooCommerce Support: ${
		systemStatus.theme.has_woocommerce_support ? CHECK_MARK : CROSS_MARK
	}

### Templates ###

${
	systemStatus.theme.has_woocommerce_file
		? 'Archive Template: ' +
		  'Your theme has a woocommerce.php file, you will not be able to override the woocommerce/archive-product.php custom template.'
		: ''
}
Overrides: ${
		0 < systemStatus.theme.overrides.length
			? systemStatus.theme.overrides
					.map( ( override ) => {
						return override.file;
					} )
					.join( ', ' )
			: DASH_MARK
	}
${
	systemStatus.theme.has_outdated_templates
		? 'Outdated Templates: ' + CROSS_MARK
		: ''
}

### WooCommerce Payments ###

Connected to WPCOM: ${
		'NOACCOUNT' === wcPayData.status ||
		'ONBOARDING_DISABLED' === wcPayData.status
			? 'No'
			: 'Yes'
	}
Account ID: ${ wcPayData.account_id }

### Status report information ###

Generated at: ${ dateI18n( 'Y-m-d H:i:s P' ) }
`;
	return ssr;
};

function printDatabaseDetails( database ) {
	const dbSize = database.database_size;
	const dbTables = database.database_tables;
	let result = '';
	if ( dbSize && dbTables ) {
		result =
			'Total Database Size: ' +
			( dbSize.data + dbSize.index ).toFixed( 2 ) +
			'MB\n' +
			'Database Data Size: ' +
			dbSize.data.toFixed( 2 ) +
			'MB\n' +
			'Database Index Size: ' +
			dbSize.index.toFixed( 2 ) +
			'MB\n';
		result += printTables( dbTables.woocommerce );
		result += printTables( dbTables.other );
	} else {
		result = 'Unable to retrieve database information.';
	}
	return result;
}

function printTables( tables ) {
	let result = '';
	for ( const [ tableName, tableDetail ] of Object.entries( tables ) ) {
		result += tableName;
		if ( tableDetail ) {
			result += `: Data: ${ tableDetail.data }MB + Index: ${ tableDetail.index }MB + Engine ${ tableDetail.engine }`;
		}
		result += '\n';
	}
	return result;
}

function printPostTypeCounts( postTypeCounts ) {
	let result = '';
	if ( postTypeCounts ) {
		result = '### Post Type Counts ###\n';
		postTypeCounts.forEach( ( postType ) => {
			result += `\n${ postType.type }: ${ postType.count }`;
		} );
	}
	return result;
}

function printPlugins( plugins, header ) {
	let result = '';
	if ( header && 0 < plugins.length ) {
		result = '\n### ' + header + ' (' + plugins.length + ')\n\n';
	}
	plugins.forEach( ( plugin ) => {
		const currentVersion = plugin.version;
		result += `${ plugin.name }: by ${ plugin.author_name } - ${ currentVersion }`;
		const latestVersion = plugin.version_latest;
		if (
			currentVersion &&
			latestVersion &&
			currentVersion !== latestVersion
		) {
			result += ` (update to version ${ latestVersion } is available)`;
		}
		result += '\n';
	} );
	return result;
}

function printPages( pages ) {
	let result = '';
	pages.forEach( ( page ) => {
		result += page.page_name + ': ';
		let foundError = false;
		if ( ! page.page_set ) {
			result += CROSS_MARK + ' Page not set';
			foundError = true;
		} else if ( ! page.page_exists ) {
			result +=
				CROSS_MARK + ' Page ID is set, but the page does not exist';
			foundError = true;
		} else if ( ! page.page_visible ) {
			result += CROSS_MARK + ' Page visibility should be public';
			foundError = true;
		} else if ( page.shortcode_required || page.block_required ) {
			if ( ! page.shortcode_present && ! page.block_present ) {
				result +=
					CROSS_MARK +
					` Page does not contain the ${ page.shortcode } shortcode or the ${ page.block } block`;
				foundError = true;
			}
		}
		if ( ! foundError ) {
			result += 'Page ID #' + page.page_id;
		}
		result += '\n';
	} );
	return result;
}

function printTerms( arr ) {
	let result = '';
	Object.entries( arr ).forEach( ( [ key, value ] ) => {
		result += value.toLowerCase() + ' (' + key + ')\n';
	} );
	return result;
}

/**
 * Convert bytes into human-readable format. Resemble from PHP function size_format in WordPress core.
 *
 * @param {number} bytes Amount of bytes to be converted.
 * @param {number} decimals Number of digits after the decimal. Default 0.
 * @return {string} Human-readable string.
 */
function formatSize( bytes, decimals = 0 ) {
	if ( 0 === bytes ) {
		return '0 B';
	}

	const KB_IN_BYTES = 1024;
	const MB_IN_BYTES = KB_IN_BYTES * 1024;
	const GB_IN_BYTES = MB_IN_BYTES * 1024;
	const TB_IN_BYTES = GB_IN_BYTES * 1024;

	const unitPerBytesMapping = [
		[ 'TB', TB_IN_BYTES ],
		[ 'GB', GB_IN_BYTES ],
		[ 'MB', MB_IN_BYTES ],
		[ 'KB', KB_IN_BYTES ],
		[ 'B', 1 ],
	];

	for ( const [ unit, bytesPerUnit ] of unitPerBytesMapping ) {
		if ( bytes >= bytesPerUnit ) {
			return ( bytes / bytesPerUnit ).toFixed( decimals ) + ' ' + unit;
		}
	}

	return 'N/A';
}
