/** @format */

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';

export const formatSsr = ( systemStatus, wcPayData ) => {
	const ssr = `### WordPress Environment ###

WordPress address (URL): ${ systemStatus.environment.site_url }
Site address (URL): ${ systemStatus.environment.home_url }
WC Version: ${ systemStatus.environment.version }
Log Directory Writable: ${
		systemStatus.environment.log_directory_writable
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
WP Version: ${ systemStatus.environment.wp_version }
WP Multisite: ${
		systemStatus.environment.wp_multisite
			? String.fromCharCode( 10004 )
			: '-'
	}
WP Memory Limit: ${ systemStatus.environment.wp_memory_limit }
WP Debug Mode: ${
		systemStatus.environment.wp_debug_mode
			? String.fromCharCode( 10004 )
			: '-'
	}
WP Cron: ${
		systemStatus.environment.wp_cron ? String.fromCharCode( 10004 ) : '-'
	}
Language: ${ systemStatus.environment.language }
External object cache: ${
		systemStatus.environment.external_object_cache
			? String.fromCharCode( 10004 )
			: '-'
	}

### Server Environment ###

Server Info: ${ systemStatus.environment.server_info }
PHP Version: ${ systemStatus.environment.php_version }
PHP Post Max Size: ${ systemStatus.environment.php_post_max_size }
PHP Time Limit: ${ systemStatus.environment.php_max_execution_time }
PHP Max Input Vars: ${ systemStatus.environment.php_max_input_vars }
cURL Version: ${ systemStatus.environment.curl_version }

SUHOSIN Installed: ${
		systemStatus.environment.suhosin_installed
			? String.fromCharCode( 10004 )
			: '-'
	}
MySQL Version: ${ systemStatus.environment.mysql_version_string }
Max Upload Size: ${ systemStatus.environment.max_upload_size }
Default Timezone is UTC: ${
		'UTC' !== systemStatus.environment.default_timezone
			? 'Show error'
			: String.fromCharCode( 10004 )
	}
fsockopen/cURL: ${
		systemStatus.environment.fsockopen_or_curl_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
SoapClient: ${
		systemStatus.environment.soapclient_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
DOMDocument: ${
		systemStatus.environment.domdocument_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
GZip: ${
		systemStatus.environment.gzip_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
Multibyte String: ${
		systemStatus.environment.mbstring_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
Remote Post: ${
		systemStatus.environment.remote_post_successful
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
Remote Get: ${
		systemStatus.environment.remote_get_successful
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}

### Database ###

WC Database Version: ${ systemStatus.database.wc_database_version }
WC Database Prefix: ${ systemStatus.database.database_prefix }
${ printDatabaseDetails( systemStatus.database ) }
${ printPostTypeCounts( systemStatus.post_type_counts ) }

### Security ###

Secure connection (HTTPS): ${
		systemStatus.security.secure_connection
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 ) + 'Your store is not using HTTPS.'
	}
Hide errors from visitors: ${
		systemStatus.security.hide_errors
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 ) +
			  'Error messages should not be shown to visitors.'
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

API Enabled: ${
		systemStatus.settings.api_enabled ? String.fromCharCode( 10004 ) : '-'
	}
Force SSL: ${
		systemStatus.settings.force_ssl ? String.fromCharCode( 10004 ) : '-'
	}
Currency: ${ systemStatus.settings.currency } (${
		systemStatus.settings.currency_symbol
	})
Currency Position: ${ systemStatus.settings.currency_position }
Thousand Separator: ${ systemStatus.settings.thousand_separator }
Decimal Separator:  ${ systemStatus.settings.decimal_separator }
Number of Decimals: ${ systemStatus.settings.number_of_decimals }
Taxonomies: Product Types: ${ printTerms( systemStatus.settings.taxonomies ) }
Taxonomies: Product Visibility: ${ printTerms(
		systemStatus.settings.product_visibility_terms
	) }
Connected to WooCommerce.com:  ${
		systemStatus.settings.woocommerce_com_connected
			? String.fromCharCode( 10004 )
			: '-'
	}

### WC Pages ###

${ printPages( systemStatus.pages ) }
### Theme ###

Name: ${ systemStatus.theme.name }
Version: ${ systemStatus.theme.version } ${
		systemStatus.theme.version_latest ??
		`(update to version ${ systemStatus.theme.version_latest } is available)`
	}
Author URL: ${ systemStatus.theme.author_url }
Child Theme: ${
		systemStatus.theme.is_child_theme
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 ) +
			  'If you are modifying WooCommerce on a parent theme that you did not build personally we recommend using a child theme.'
	} 
WooCommerce Support: ${
		! systemStatus.theme.has_woocommerce_support
			? String.fromCharCode( 10060 )
			: String.fromCharCode( 10004 )
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
			: '-'
	}
${
	systemStatus.theme.has_outdated_templates
		? 'Outdated Templates: ' + String.fromCharCode( 10060 )
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
			result += `\n${ postType.type } : ${ postType.count }`;
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
			result += String.fromCharCode( 10060 ) + ' Page not set';
			foundError = true;
		} else if ( ! page.page_exists ) {
			result +=
				String.fromCharCode( 10060 ) +
				' Page ID is set, but the page does not exist';
			foundError = true;
		} else if ( ! page.page_visible ) {
			result +=
				String.fromCharCode( 10060 ) +
				' Page visibility should be public';
			foundError = true;
		} else if ( page.shortcode_required || page.block_required ) {
			if ( ! page.shortcode_present && ! page.block_present ) {
				result +=
					String.fromCharCode( 10060 ) +
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
