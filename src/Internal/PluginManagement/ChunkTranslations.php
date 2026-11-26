<?php
/**
 * Class ChunkTranslations
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\PluginManagement;

use WCPay\Internal\Proxy\HooksProxy;

/**
 * Adds the translations of the lazy-loaded admin chunks to the admin entries' script translations.
 *
 * WordPress only loads translation JSON for registered script handles, and translate.wordpress.org
 * puts a string that is used only in a chunk into that chunk's JSON file, which nothing else loads.
 */
class ChunkTranslations {
	private const DOMAIN = 'woocommerce-payments';

	/**
	 * Entry file (relative to `dist/`) for each script handle that lazy-loads chunks.
	 */
	private const HANDLE_ENTRIES = [
		'WCPAY_DASH_APP'       => 'index.js',
		'WCPAY_ADMIN_SETTINGS' => 'settings.js',
	];

	/**
	 * Chunks per entry, for builds without `dist/i18n-chunk-entries.json` (production builds write it).
	 */
	private const FALLBACK_CHUNKS = [
		'index.js'    => [
			'chunks/wcpay-capital.js',
			'chunks/wcpay-card-readers.js',
			'chunks/wcpay-documents.js',
			'chunks/wcpay-fraud-protection.js',
			'chunks/wcpay-money-movement.js',
			'chunks/wcpay-multi-currency-setup.js',
			'chunks/wcpay-onboarding.js',
			'chunks/wcpay-overview.js',
			'chunks/wcpay-payouts.js',
			'chunks/wcpay-reports.js',
			'chunks/reports-balance-actions.js',
			'chunks/reports-balance.js',
			'chunks/reports-fees.js',
		],
		'settings.js' => [
			'chunks/wcpay-phone-input.js',
		],
	];

	/**
	 * HooksProxy instance.
	 *
	 * @var HooksProxy
	 */
	private $hooks_proxy;

	/**
	 * Absolute path of the `dist/` folder, with a trailing slash.
	 *
	 * @var string
	 */
	private $dist_path;

	/**
	 * Constructor.
	 *
	 * @param HooksProxy $hooks_proxy Hooks proxy instance.
	 * @param string     $dist_path   Absolute path of the `dist/` folder.
	 */
	public function __construct( HooksProxy $hooks_proxy, string $dist_path = '' ) {
		$this->hooks_proxy = $hooks_proxy;
		$this->dist_path   = '' !== $dist_path ? trailingslashit( $dist_path ) : WCPAY_ABSPATH . 'dist/';
	}

	/**
	 * Hooks into the loading of script translations.
	 */
	public function init_hooks() {
		$this->hooks_proxy->add_filter( 'load_script_translations', [ $this, 'merge_chunk_translations' ], 10, 4 );
		$this->hooks_proxy->add_filter( 'pre_load_script_translations', [ $this, 'load_chunk_translations_only' ], 10, 4 );
	}

	/**
	 * Adds the chunks' strings to the translations WordPress found for an entry. The entry's own strings win.
	 *
	 * @param mixed $translations JSON-encoded translation data of the entry.
	 * @param mixed $file         Path of the entry's JSON file.
	 * @param mixed $handle       Script handle.
	 * @param mixed $domain       Text domain.
	 *
	 * @return mixed The merged JSON, or the original value if there is nothing to merge.
	 */
	public function merge_chunk_translations( $translations, $file, $handle, $domain ) {
		if ( ! $this->is_admin_entry( $handle, $domain ) ) {
			return $translations;
		}

		if ( ! is_string( $translations ) ) {
			return $translations;
		}

		if ( ! is_string( $file ) ) {
			return $translations;
		}

		$dirs = array_values( array_unique( array_merge( [ dirname( $file ) ], $this->get_languages_dirs() ) ) );

		return $this->merge( $translations, $dirs, self::HANDLE_ENTRIES[ $handle ] );
	}

	/**
	 * Provides the chunks' strings for an entry that has no translation JSON of its own.
	 *
	 * WordPress calls this with a `false` file after it found no JSON for the entry. Plugins like Loco Translate can
	 * still supply an entry file through `load_script_translation_file`, so it steps aside when one does.
	 *
	 * @param mixed $translations Pre-filtered translations, `null` unless another callback set them.
	 * @param mixed $file         Path of the JSON file, `false` when there is none.
	 * @param mixed $handle       Script handle.
	 * @param mixed $domain       Text domain.
	 *
	 * @return mixed The JSON built from the chunks, or the original value if no chunk has translations.
	 */
	public function load_chunk_translations_only( $translations, $file, $handle, $domain ) {
		if ( null !== $translations ) {
			return $translations;
		}

		if ( false !== $file ) {
			return $translations;
		}

		if ( ! $this->is_admin_entry( $handle, $domain ) ) {
			return $translations;
		}

		$entry_file = $this->hooks_proxy->apply_filters( 'load_script_translation_file', false, $handle, $domain ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WordPress core hook.
		if ( is_string( $entry_file ) && is_readable( $entry_file ) ) {
			return $translations;
		}

		return $this->merge( null, $this->get_languages_dirs(), self::HANDLE_ENTRIES[ $handle ] );
	}

	/**
	 * Whether the handle is an admin entry of this plugin's text domain.
	 *
	 * @param mixed $handle Script handle.
	 * @param mixed $domain Text domain.
	 *
	 * @return bool
	 */
	private function is_admin_entry( $handle, $domain ): bool {
		return self::DOMAIN === $domain && is_string( $handle ) && isset( self::HANDLE_ENTRIES[ $handle ] );
	}

	/**
	 * Merges the chunks' messages into the entry's JSON.
	 *
	 * @param string|null $entry_json  The entry's JSON, `null` when it has none.
	 * @param string[]    $dirs        Directories to look for the chunks' JSON files in.
	 * @param string      $entry_file  Entry file relative to `dist/`.
	 *
	 * @return string|null The merged JSON, or `$entry_json` as it was when no chunk adds anything.
	 */
	private function merge( ?string $entry_json, array $dirs, string $entry_file ): ?string {
		$entry     = null;
		$entry_key = null;
		if ( null !== $entry_json ) {
			$entry = json_decode( $entry_json, true );
			if ( ! is_array( $entry ) ) {
				return $entry_json;
			}

			$entry_key = $this->get_messages_key( $entry );
			if ( null === $entry_key ) {
				return $entry_json;
			}
		}

		$merged     = $entry;
		$merged_key = $entry_key;
		foreach ( $this->get_chunks( $entry_file ) as $chunk ) {
			$chunk_data = $this->read_chunk( $dirs, $chunk );
			if ( null === $chunk_data ) {
				continue;
			}

			$chunk_key      = $this->get_messages_key( $chunk_data );
			$chunk_messages = $chunk_data['locale_data'][ $chunk_key ];

			if ( null === $merged ) {
				// The base catalog needs the header WordPress reads the domain from.
				if ( isset( $chunk_messages[''] ) && is_array( $chunk_messages[''] ) ) {
					$merged     = $chunk_data;
					$merged_key = $chunk_key;
				}
				continue;
			}

			$merged['locale_data'][ $merged_key ] += $chunk_messages;
		}

		if ( $merged === $entry ) {
			return $entry_json;
		}

		// HEX_TAG keeps a `</script>` inside a translated label from ending the inline script.
		$encoded = wp_json_encode( $merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG );

		return false === $encoded ? $entry_json : $encoded;
	}

	/**
	 * Reads the first valid JSON file of a chunk found in the given directories.
	 *
	 * @param string[] $dirs  Directories to look in.
	 * @param string   $chunk Chunk file relative to `dist/`, for example `chunks/wcpay-payouts.js`.
	 *
	 * @return array|null The decoded JSON, which has a message catalog, or `null` if there is none.
	 */
	private function read_chunk( array $dirs, string $chunk ): ?array {
		$name = sprintf( '%s-%s-%s.json', self::DOMAIN, determine_locale(), md5( 'dist/' . $chunk ) );

		foreach ( $dirs as $dir ) {
			$path = trailingslashit( $dir ) . $name;
			if ( ! is_readable( $path ) ) {
				continue;
			}

			$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local translation file, as WordPress reads it.
			if ( is_array( $data ) && null !== $this->get_messages_key( $data ) ) {
				return $data;
			}
		}

		return null;
	}

	/**
	 * Gets the key WordPress reads the message catalog from: the text domain first, then `messages`.
	 *
	 * @param array $data Decoded translation JSON.
	 *
	 * @return string|null The key, or `null` if there is no catalog under either.
	 */
	private function get_messages_key( array $data ): ?string {
		foreach ( [ self::DOMAIN, 'messages' ] as $key ) {
			if ( isset( $data['locale_data'][ $key ] ) && is_array( $data['locale_data'][ $key ] ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Gets the chunks an entry can lazy-load.
	 *
	 * @param string $entry_file Entry file relative to `dist/`.
	 *
	 * @return string[] Chunk files relative to `dist/`.
	 */
	private function get_chunks( string $entry_file ): array {
		$map_path = $this->dist_path . 'i18n-chunk-entries.json';
		if ( ! is_readable( $map_path ) ) {
			return self::FALLBACK_CHUNKS[ $entry_file ] ?? [];
		}

		$map = json_decode( (string) file_get_contents( $map_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local build file.
		if ( ! is_array( $map ) ) {
			return self::FALLBACK_CHUNKS[ $entry_file ] ?? [];
		}

		$chunks = [];
		foreach ( $map as $chunk => $entries ) {
			if ( is_array( $entries ) && in_array( $entry_file, $entries, true ) ) {
				$chunks[] = (string) $chunk;
			}
		}

		return $chunks;
	}

	/**
	 * Gets the directories WordPress can keep this plugin's translation files in.
	 *
	 * @return string[]
	 */
	private function get_languages_dirs(): array {
		global $wp_textdomain_registry;

		$dirs = [];
		if ( isset( $wp_textdomain_registry ) ) {
			$dirs[] = $wp_textdomain_registry->get( self::DOMAIN, determine_locale() );
		}
		$dirs[] = WP_LANG_DIR . '/plugins';

		return array_values( array_unique( array_filter( $dirs ) ) );
	}
}
