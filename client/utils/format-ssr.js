/** @format */

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';

export const formatSsr = ( json ) => {
	const ssr = `### WordPress Environment ###

WordPress address (URL): ${ json.environment.site_url }
Site address (URL): ${ json.environment.home_url }
WC Version: ${ json.environment.version }
Log Directory Writable: ${
		json.environment.log_directory_writable
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
WP Version: ${ json.environment.wp_version }
WP Multisite: ${
		json.environment.wp_multisite ? String.fromCharCode( 10004 ) : '-'
	}
WP Memory Limit: ${ json.environment.wp_memory_limit }
WP Debug Mode: ${
		json.environment.wp_debug_mode ? String.fromCharCode( 10004 ) : '-'
	}
WP Cron: ${ json.environment.wp_cron ? String.fromCharCode( 10004 ) : '-' }
Language: ${ json.environment.language }
External object cache: ${
		json.environment.external_object_cache
			? String.fromCharCode( 10004 )
			: '-'
	}

### Server Environment ###

Server Info: ${ json.environment.server_info }
PHP Version: ${ json.environment.php_version }
PHP Post Max Size: ${ json.environment.php_post_max_size }
PHP Time Limit: ${ json.environment.php_max_execution_time }
PHP Max Input Vars: ${ json.environment.php_max_input_vars }
cURL Version: ${ json.environment.curl_version }

SUHOSIN Installed: ${
		json.environment.suhosin_installed ? String.fromCharCode( 10004 ) : '-'
	}
MySQL Version: 8.0.28
Max Upload Size: ${ json.environment.max_upload_size }
Default Timezone is UTC: ${
		'UTC' !== json.environment.default_timezone
			? 'Show error'
			: String.fromCharCode( 10004 )
	}
fsockopen/cURL: ${
		json.environment.fsockopen_or_curl_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
SoapClient: ${
		json.environment.soapclient_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
DOMDocument: ${
		json.environment.domdocument_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
GZip: ${
		json.environment.gzip_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
Multibyte String: ${
		json.environment.mbstring_enabled
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
Remote Post: ${
		json.environment.remote_post_successful
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}
Remote Get: ${
		json.environment.remote_get_successful
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 )
	}

### Database ###

WC Database Version: ${ json.database.wc_database_version }
WC Database Prefix: ${ json.database.database_prefix }
${ printDatabaseDetails( json.database ) }
${ printPostTypeCounts( json.post_type_counts ) }

### Security ###

Secure connection (HTTPS): ${
		json.security.secure_connection
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 ) + 'Your store is not using HTTPS.'
	}
Hide errors from visitors: ${
		json.security.hide_errors
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 ) +
			  'Error messages should not be shown to visitors.'
	}

### Active Plugins (${ json.active_plugins.length }) ###

${ printPlugins( json.active_plugins, null ) }
### Inactive Plugins (${ json.inactive_plugins.length }) ###

${ printPlugins( json.inactive_plugins, null ) }${ printPlugins(
		json.dropins_mu_plugins.dropins,
		'Dropin Plugins'
	) }${ printPlugins(
		json.dropins_mu_plugins.mu_plugins,
		'Must Use Plugins'
	) }
### Settings ###

API Enabled: ${ json.settings.api_enabled ? String.fromCharCode( 10004 ) : '-' }
Force SSL: ${ json.settings.force_ssl ? String.fromCharCode( 10004 ) : '-' }
Currency: ${ json.settings.currency } (${ json.settings.currency_symbol })
Currency Position: ${ json.settings.currency_position }
Thousand Separator: ${ json.settings.thousand_separator }
Decimal Separator:  ${ json.settings.decimal_separator }
Number of Decimals: ${ json.settings.number_of_decimals }
Taxonomies: Product Types: ${ printTerms( json.settings.taxonomies ) }
Taxonomies: Product Visibility: ${ printTerms(
		json.settings.product_visibility_terms
	) }
Connected to WooCommerce.com:  ${
		json.settings.woocommerce_com_connected
			? String.fromCharCode( 10004 )
			: '-'
	}

### WC Pages ###

${ printPages( json.pages ) }
### Theme ###

Name: ${ json.theme.name }
Version: ${ json.theme.version } ${
		json.theme.version_latest ??
		`(update to version ${ json.theme.version_latest } is available)`
	}
Author URL: ${ json.theme.author_url }
Child Theme: ${
		json.theme.is_child_theme
			? String.fromCharCode( 10004 )
			: String.fromCharCode( 10060 ) +
			  'If you are modifying WooCommerce on a parent theme that you did not build personally we recommend using a child theme.'
	} 
WooCommerce Support: ${
		! json.theme.has_woocommerce_support
			? String.fromCharCode( 10060 )
			: String.fromCharCode( 10004 )
	}

### Templates ###

${
	json.theme.has_woocommerce_file
		? 'Archive Template: ' +
		  'Your theme has a woocommerce.php file, you will not be able to override the woocommerce/archive-product.php custom template.'
		: ''
}
Overrides: ${
		0 < json.theme.overrides.length
			? json.theme.overrides
					.map( ( override ) => {
						return override.file;
					} )
					.join( ', ' )
			: '-'
	}
${
	json.theme.has_outdated_templates
		? 'Outdated Templates: ' + String.fromCharCode( 10060 )
		: ''
}

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
