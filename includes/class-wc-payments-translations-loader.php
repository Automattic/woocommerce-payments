<?php
/**
 * Class WC_Payments_Translations_Loader
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for WC Payments Translations Loading.
 *
 * Its responsibility is to combine translation chunks when translation files are updated,
 * and load it for all the plugin script handles.
 */
class WC_Payments_Translations_Loader {

	/**
	 * Plugin domain.
	 *
	 * Should not be used as the 'domain' in the translations functions.
	 * Once support for PHP 7.0 is dropped, change this to 'private const'
	 */
	const PLUGIN_DOMAIN = 'woocommerce-payments';

	/**
	 * Entry point to the initialization logic.
	 */
	public static function init() {
		// Combine JSON translation files (from chunks) when language packs are updated.
		add_action( 'upgrader_process_complete', [ __CLASS__, 'combine_translation_chunk_files' ], 10, 2 );

		// Grab translation strings from Webpack-generated chunks.
		add_filter( 'load_script_translation_file', [ __CLASS__, 'load_script_translation_file' ], 10, 3 );
	}

	/**
	 * Generate a filename to cache translations from JS chunks.
	 *
	 * @param string $locale Locale being retrieved.
	 * @return string Filename.
	 */
	private static function get_combined_translation_filename( $locale ) {
		return implode( '-', [ self::PLUGIN_DOMAIN, $locale ] ) . '.json';
	}

	/**
	 * Combine translation chunks when files are updated.
	 *
	 * This function combines JSON translation data auto-extracted by GlotPress
	 * from Webpack-generated JS chunks into a single file that can be used in
	 * subsequent requests. This is necessary since the JS chunks are not known
	 * to WordPress via wp_register_script() and wp_set_script_translations().
	 *
	 * @param Language_Pack_Upgrader $instance Upgrader instance.
	 * @param array                  $hook_extra Info about the upgraded language packs.
	 */
	public static function combine_translation_chunk_files( $instance, $hook_extra ) {
		if (
			! is_a( $instance, 'Language_Pack_Upgrader' ) ||
			! isset( $hook_extra['translations'] ) ||
			! is_array( $hook_extra['translations'] )
		) {
			return;
		}

		// Gather the locales that were updated in this operation.
		$locales = [];
		foreach ( $hook_extra['translations'] as $translation ) {
			if (
				'plugin' === $translation['type'] &&
				self::PLUGIN_DOMAIN === $translation['slug']
			) {
				$locales[] = $translation['language'];
			}
		}

		// Build combined translation files for all updated locales.
		foreach ( $locales as $locale ) {
			// So long as this function is hooked to the 'upgrader_process_complete' action,
			// WP_Filesystem should be hooked up to be able to call build_and_save_translations.
			self::build_and_save_translations( $locale );
		}
	}

	/**
	 * Combine and save translations for a specific locale.
	 *
	 * Note that this assumes \WP_Filesystem is already initialized with write access.
	 * So long as this function is called during the 'upgrader_process_complete' action,
	 * the filesystem object should be hooked up.
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param string $locale Locale being retrieved.
	 */
	private static function build_and_save_translations( $locale ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem || ! is_object( $wp_filesystem ) ) {
			return new WP_Error( 'fs_unavailable', __( 'Could not access filesystem.', 'woocommerce-payments' ) );
		}

		$translations_from_chunks = self::get_translation_chunk_data( $locale );

		if ( empty( $translations_from_chunks ) ) {
			return;
		}

		$cache_filename          = self::get_combined_translation_filename( $locale );
		$chunk_translations_json = wp_json_encode( $translations_from_chunks );

		// Cache combined translations strings to a file.
		$wp_filesystem->put_contents( WP_LANG_DIR . '/plugins/' . $cache_filename, $chunk_translations_json );
	}

	/**
	 * Find and combine translation chunk files.
	 *
	 * Only targets files that aren't represented by a registered script (e.g. not passed to wp_register_script()).
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem subclass.
	 *
	 * @param string $locale Locale being retrieved.
	 *
	 * @return array|WP_Error Combined translation chunk data.
	 */
	private static function get_translation_chunk_data( $locale ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem || ! is_object( $wp_filesystem ) ) {
			return new WP_Error( 'fs_unavailable', __( 'Could not access filesystem.', 'woocommerce-payments' ) );
		}

		// Grab all JSON files in the current language pack.
		$json_i18n_filenames       = glob( WP_LANG_DIR . '/plugins/' . self::PLUGIN_DOMAIN . '-' . $locale . '-*.json' );
		$combined_translation_data = [];

		if ( false === $json_i18n_filenames ) {
			return $combined_translation_data;
		}

		foreach ( $json_i18n_filenames as $json_filename ) {
			if ( ! $wp_filesystem->is_readable( $json_filename ) ) {
				continue;
			}

			$file_contents = $wp_filesystem->get_contents( $json_filename );
			$chunk_data    = \json_decode( $file_contents, true );

			if ( empty( $chunk_data ) ) {
				continue;
			}

			if ( empty( $combined_translation_data ) ) {
				// Use the first translation file as the base structure.
				$combined_translation_data = $chunk_data;
			} else {
				// Combine all messages from all chunk files.
				$combined_translation_data['locale_data']['messages'] = array_merge(
					$combined_translation_data['locale_data']['messages'],
					$chunk_data['locale_data']['messages']
				);
			}
		}

		return $combined_translation_data;
	}

	/**
	 * Load translation strings from language packs for dynamic imports.
	 *
	 * @param string $file File location for the script being translated.
	 * @param string $handle Script handle.
	 * @param string $domain Text domain.
	 *
	 * @return string New file location for the script being translated.
	 */
	public static function load_script_translation_file( $file, $handle, $domain ) {
		// Make sure the main app script is being loaded.
		if ( 0 !== strpos( $handle, 'WCPAY_' ) ) {
			return $file;
		}

		// Make sure we're handing the correct domain (woocommerce-payments).
		if ( self::PLUGIN_DOMAIN !== $domain ) {
			return $file;
		}

		$locale         = determine_locale();
		$cache_filename = self::get_combined_translation_filename( $locale );

		return WP_LANG_DIR . '/plugins/' . $cache_filename;
	}
}
