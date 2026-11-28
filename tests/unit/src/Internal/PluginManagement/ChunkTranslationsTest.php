<?php
/**
 * Class ChunkTranslationsTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\PluginManagement;

use PHPUnit\Framework\MockObject\MockObject;
use WCPAY_UnitTestCase;
use WCPay\Internal\PluginManagement\ChunkTranslations;
use WCPay\Internal\Proxy\HooksProxy;

/**
 * ChunkTranslations unit tests.
 */
class ChunkTranslationsTest extends WCPAY_UnitTestCase {
	private const HANDLE = 'WCPAY_DASH_APP';
	private const DOMAIN = 'woocommerce-payments';

	/**
	 * Service under test.
	 *
	 * @var ChunkTranslations
	 */
	private $sut;

	/**
	 * @var HooksProxy|MockObject
	 */
	private $hooks_proxy;

	/**
	 * Folder that stands in for `dist/`.
	 *
	 * @var string
	 */
	private $dist_dir;

	/**
	 * Folder that stands in for the translation files folder.
	 *
	 * @var string
	 */
	private $languages_dir;

	/**
	 * Part of every chunk name, so the files of other runs and real packs never match.
	 *
	 * @var string
	 */
	private $run_id;

	/**
	 * Files this test created in the shared WordPress languages folder.
	 *
	 * @var string[]
	 */
	private $shared_files = [];

	/**
	 * Sets up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->run_id        = uniqid( 'run' );
		$base                = trailingslashit( get_temp_dir() ) . 'wcpay-chunk-translations-' . $this->run_id;
		$this->dist_dir      = $base . '/dist';
		$this->languages_dir = $base . '/languages';
		mkdir( $this->dist_dir, 0777, true );
		mkdir( $this->languages_dir, 0777, true );

		$this->hooks_proxy = $this->createMock( HooksProxy::class );
		$this->sut         = new ChunkTranslations( $this->hooks_proxy, $this->dist_dir );
	}

	/**
	 * Cleans up the files the test created.
	 */
	protected function tearDown(): void {
		foreach ( array_merge( glob( $this->dist_dir . '/*' ), glob( $this->languages_dir . '/*' ), $this->shared_files ) as $path ) {
			unlink( $path );
		}
		rmdir( $this->dist_dir );
		rmdir( $this->languages_dir );
		rmdir( dirname( $this->dist_dir ) );

		parent::tearDown();
	}

	public function test_init_hooks_registers_both_filters() {
		$registered = [];
		$this->hooks_proxy->method( 'add_filter' )
			->willReturnCallback(
				function ( $name, $callback, $priority, $args ) use ( &$registered ) {
					$registered[ $name ] = [ $callback, $priority, $args ];
				}
			);

		$this->sut->init_hooks();

		$this->assertSame(
			[
				'load_script_translations'     => [ [ $this->sut, 'merge_chunk_translations' ], 10, 4 ],
				'pre_load_script_translations' => [ [ $this->sut, 'load_chunk_translations_only' ], 10, 4 ],
			],
			$registered
		);
	}

	public function test_merge_adds_only_the_chunks_the_entry_loads() {
		$payouts  = $this->chunk( 'payouts' );
		$capital  = $this->chunk( 'capital' );
		$settings = $this->chunk( 'settings' );
		$this->write_chunk_map(
			[
				$payouts  => [ 'index.js' ],
				$capital  => [ 'index.js', 'settings.js' ],
				$settings => [ 'settings.js' ],
			]
		);
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ] );
		$this->write_chunk( $capital, [ 'Capital' => [ 'Capital es' ] ] );
		$this->write_chunk( $settings, [ 'Settings only' => [ 'Solo ajustes' ] ] );

		$result = $this->merge( $this->translation_json( [ 'Entry string' => [ 'Cadena' ] ] ) );

		$this->assertSame(
			[
				''               => $this->header(),
				'Entry string'   => [ 'Cadena' ],
				'Payout history' => [ 'Historial de pagos' ],
				'Capital'        => [ 'Capital es' ],
			],
			$this->messages( $result )
		);
	}

	public function test_merge_keeps_the_entry_json_shape_and_lets_the_entry_win() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk(
			$payouts,
			[
				'Shared' => [ 'From the chunk' ],
				'Chunk'  => [ 'Only chunk' ],
			]
		);

		$result = json_decode( $this->merge( $this->translation_json( [ 'Shared' => [ 'From the entry' ] ] ) ), true );

		$this->assertSame( 'messages', $result['domain'] );
		$this->assertSame( [ 'messages' ], array_keys( $result['locale_data'] ) );
		$this->assertSame( $this->header(), $result['locale_data']['messages'][''] );
		$this->assertSame( [ 'From the entry' ], $result['locale_data']['messages']['Shared'] );
		$this->assertSame( [ 'Only chunk' ], $result['locale_data']['messages']['Chunk'] );
	}

	public function test_merge_keeps_unicode_unescaped_and_script_tags_escaped() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk(
			$payouts,
			[
				'Bank'  => [ 'بانک / Banco' ],
				'Close' => [ 'Cerrar</script><script>alert(1)</script>' ],
			]
		);

		$result = $this->merge( $this->translation_json( [] ) );

		$this->assertStringContainsString( 'بانک / Banco', $result );
		$this->assertStringNotContainsString( '</script>', $result );
		$this->assertSame( [ 'Cerrar</script><script>alert(1)</script>' ], $this->messages( $result )['Close'] );
	}

	public function test_merge_writes_to_the_catalog_wordpress_reads_first() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ], null, self::DOMAIN );

		$result = json_decode( $this->merge( $this->translation_json( [ 'Entry string' => [ 'Cadena' ] ], self::DOMAIN ) ), true );

		$this->assertSame( [ self::DOMAIN ], array_keys( $result['locale_data'] ) );
		$this->assertSame( [ 'Historial de pagos' ], $result['locale_data'][ self::DOMAIN ]['Payout history'] );
		$this->assertSame( [ 'Cadena' ], $result['locale_data'][ self::DOMAIN ]['Entry string'] );
	}

	public function test_merge_reads_a_chunk_that_uses_the_domain_catalog_into_a_messages_entry() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ], null, self::DOMAIN );

		$result = $this->merge( $this->translation_json( [] ) );

		$this->assertSame( [ 'Historial de pagos' ], $this->messages( $result )['Payout history'] );
	}

	public function test_merge_uses_the_built_in_chunk_list_without_a_chunk_map() {
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ] );

		$result = $this->merge( $this->translation_json( [] ) );

		$this->assertSame( [ 'Historial de pagos' ], $this->messages( $result )['Payout history'] );
	}

	public function test_merge_uses_the_built_in_chunk_list_when_the_chunk_map_is_invalid() {
		file_put_contents( $this->dist_dir . '/i18n-chunk-entries.json', '{not json' );
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ] );

		$result = $this->merge( $this->translation_json( [] ) );

		$this->assertSame( [ 'Historial de pagos' ], $this->messages( $result )['Payout history'] );
	}

	public function test_merge_is_a_no_op_when_the_chunk_files_are_missing() {
		$this->write_chunk_map( [ $this->chunk( 'payouts' ) => [ 'index.js' ] ] );
		$entry_json = $this->translation_json( [ 'Entry string' => [ 'Cadena' ] ] );

		$this->assertSame( $entry_json, $this->merge( $entry_json ) );
	}

	public function test_merge_skips_invalid_chunk_files_and_merges_the_rest() {
		$payouts = $this->chunk( 'payouts' );
		$capital = $this->chunk( 'capital' );
		$reports = $this->chunk( 'reports' );
		$this->write_chunk_map(
			[
				$payouts => [ 'index.js' ],
				$capital => [ 'index.js' ],
				$reports => [ 'index.js' ],
			]
		);
		file_put_contents( $this->chunk_path( $payouts ), '{not json' );
		file_put_contents( $this->chunk_path( $reports ), '{"locale_data":{"messages":"nope"}}' );
		$this->write_chunk( $capital, [ 'Capital' => [ 'Capital es' ] ] );

		$result = $this->merge( $this->translation_json( [] ) );

		$this->assertSame( [ '', 'Capital' ], array_keys( $this->messages( $result ) ) );
	}

	public function test_merge_returns_an_invalid_entry_json_untouched() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ] );

		$this->assertSame( '{not json', $this->merge( '{not json' ) );
	}

	public function test_merge_returns_an_entry_without_a_message_catalog_untouched() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ] );

		$this->assertSame( '{"locale_data":{"messages":""}}', $this->merge( '{"locale_data":{"messages":""}}' ) );
		$this->assertSame( '{"domain":"messages"}', $this->merge( '{"domain":"messages"}' ) );
	}

	public function test_merge_twice_adds_nothing_more() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ] );

		$once = $this->merge( $this->translation_json( [] ) );

		$this->assertSame( $once, $this->merge( $once ) );
	}

	public function test_merge_finds_a_chunk_in_the_standard_languages_folder_when_the_entry_is_elsewhere() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ], WP_LANG_DIR . '/plugins' );

		$result = $this->merge( $this->translation_json( [ 'Entry string' => [ 'Cadena' ] ] ) );

		$this->assertSame( [ 'Historial de pagos' ], $this->messages( $result )['Payout history'] );
		$this->assertSame( [ 'Cadena' ], $this->messages( $result )['Entry string'] );
	}

	public function test_merge_prefers_the_chunk_beside_the_entry() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Beside the entry' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Standard folder' ] ], WP_LANG_DIR . '/plugins' );

		$result = $this->merge( $this->translation_json( [] ) );

		$this->assertSame( [ 'Beside the entry' ], $this->messages( $result )['Payout history'] );
	}

	public function test_merge_ignores_other_handles_and_domains() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ] );
		$entry_json = $this->translation_json( [] );
		$file       = $this->languages_dir . '/entry.json';

		$this->assertSame( $entry_json, $this->sut->merge_chunk_translations( $entry_json, $file, 'WCPAY_TOS', self::DOMAIN ) );
		$this->assertSame( $entry_json, $this->sut->merge_chunk_translations( $entry_json, $file, self::HANDLE, 'woocommerce' ) );
		$this->assertSame( $entry_json, $this->sut->merge_chunk_translations( $entry_json, $file, [ self::HANDLE ], self::DOMAIN ) );
		$this->assertFalse( $this->sut->merge_chunk_translations( false, $file, self::HANDLE, self::DOMAIN ) );
		$this->assertSame( $entry_json, $this->sut->merge_chunk_translations( $entry_json, false, self::HANDLE, self::DOMAIN ) );
	}

	public function test_merge_uses_the_settings_entry_chunks_for_the_settings_handle() {
		$payouts = $this->chunk( 'payouts' );
		$phones  = $this->chunk( 'phones' );
		$this->write_chunk_map(
			[
				$payouts => [ 'index.js' ],
				$phones  => [ 'settings.js' ],
			]
		);
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ] );
		$this->write_chunk( $phones, [ 'Phone' => [ 'Teléfono' ] ] );

		$result = $this->sut->merge_chunk_translations( $this->translation_json( [] ), $this->languages_dir . '/entry.json', 'WCPAY_ADMIN_SETTINGS', self::DOMAIN );

		$this->assertSame( [ '', 'Phone' ], array_keys( $this->messages( $result ) ) );
	}

	public function test_load_chunk_translations_only_builds_json_when_the_entry_has_none() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ], WP_LANG_DIR . '/plugins' );

		$result = $this->sut->load_chunk_translations_only( null, false, self::HANDLE, self::DOMAIN );

		$this->assertSame( [ 'Historial de pagos' ], $this->messages( $result )['Payout history'] );
		$this->assertSame( $this->header(), $this->messages( $result )[''] );
	}

	public function test_load_chunk_translations_only_skips_a_chunk_without_a_header() {
		$payouts = $this->chunk( 'payouts' );
		$capital = $this->chunk( 'capital' );
		$this->write_chunk_map(
			[
				$payouts => [ 'index.js' ],
				$capital => [ 'index.js' ],
			]
		);
		file_put_contents( $this->chunk_path( $payouts, WP_LANG_DIR . '/plugins' ), '{"locale_data":{"messages":{"Payout history":["Historial de pagos"]}}}' );
		$this->shared_files[] = $this->chunk_path( $payouts, WP_LANG_DIR . '/plugins' );
		$this->write_chunk( $capital, [ 'Capital' => [ 'Capital es' ] ], WP_LANG_DIR . '/plugins' );

		$result = $this->sut->load_chunk_translations_only( null, false, self::HANDLE, self::DOMAIN );

		$this->assertSame( [ '', 'Capital' ], array_keys( $this->messages( $result ) ) );
	}

	public function test_load_chunk_translations_only_returns_null_when_no_chunk_has_a_usable_catalog() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );

		$this->assertNull( $this->sut->load_chunk_translations_only( null, false, self::HANDLE, self::DOMAIN ) );

		file_put_contents( $this->chunk_path( $payouts, WP_LANG_DIR . '/plugins' ), '{"locale_data":{"messages":{"Payout history":["Historial de pagos"]}}}' );
		$this->shared_files[] = $this->chunk_path( $payouts, WP_LANG_DIR . '/plugins' );

		$this->assertNull( $this->sut->load_chunk_translations_only( null, false, self::HANDLE, self::DOMAIN ) );
	}

	public function test_load_chunk_translations_only_reads_an_entry_file_another_plugin_supplies_once() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ], WP_LANG_DIR . '/plugins' );
		$entry_file = $this->languages_dir . '/custom-entry.json';
		file_put_contents( $entry_file, $this->translation_json( [ 'Own' => [ 'Propio' ] ] ) );
		$asked_for_a_file = 0;
		$this->hooks_proxy->method( 'apply_filters' )
			->willReturnCallback(
				function ( $name, $value, ...$args ) use ( $entry_file, &$asked_for_a_file ) {
					if ( 'load_script_translation_file' === $name ) {
						// Like Loco Translate, only the first answer has the file.
						return 0 === $asked_for_a_file++ ? $entry_file : false;
					}

					return $this->sut->merge_chunk_translations( $value, ...$args );
				}
			);

		$result = $this->sut->load_chunk_translations_only( null, false, self::HANDLE, self::DOMAIN );

		$this->assertSame( [ '', 'Own', 'Payout history' ], array_keys( $this->messages( $result ) ) );
		$this->assertSame( 1, $asked_for_a_file );
	}

	public function test_built_in_chunk_list_matches_the_lazy_imports() {
		$imports  = function ( array $files ): array {
			$names = [];
			foreach ( $files as $file ) {
				preg_match_all( '/webpackChunkName:\s*"([^"]+)"/', (string) file_get_contents( WCPAY_ABSPATH . $file ), $matches );
				$names = array_merge( $names, $matches[1] );
			}
			$names = array_map(
				function ( $name ) {
					return 'chunks/' . $name . '.js';
				},
				array_unique( $names )
			);
			sort( $names );

			return $names;
		};
		$built_in = ( new \ReflectionClassConstant( ChunkTranslations::class, 'FALLBACK_CHUNKS' ) )->getValue();
		sort( $built_in['index.js'] );

		$this->assertSame(
			$imports(
				array_merge(
					[ 'client/index.js' ],
					array_map(
						function ( $path ) {
							return 'client/reports/' . basename( $path );
						},
						glob( WCPAY_ABSPATH . 'client/reports/lazy-*.jsx' )
					)
				)
			),
			$built_in['index.js']
		);
		$this->assertSame( $imports( [ 'client/settings/phone-input/lazy.jsx' ] ), $built_in['settings.js'] );
	}

	public function test_load_chunk_translations_only_leaves_other_calls_alone() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ], WP_LANG_DIR . '/plugins' );

		$this->assertNull( $this->sut->load_chunk_translations_only( null, '/some/missing.json', self::HANDLE, self::DOMAIN ) );
		$this->assertSame( '{"set":"by another callback"}', $this->sut->load_chunk_translations_only( '{"set":"by another callback"}', false, self::HANDLE, self::DOMAIN ) );
		$this->assertNull( $this->sut->load_chunk_translations_only( null, false, 'WCPAY_TOS', self::DOMAIN ) );
		$this->assertNull( $this->sut->load_chunk_translations_only( null, false, self::HANDLE, 'woocommerce' ) );
	}

	public function test_merge_reads_the_file_name_a_real_es_ES_pack_uses() {
		$to_es_es = function () {
			return 'es_ES';
		};
		add_filter( 'locale', $to_es_es );
		// The file name of the real es_ES pack for dist/chunks/wcpay-payouts.js.
		file_put_contents( $this->languages_dir . '/woocommerce-payments-es_ES-5c516589a5c8d922867c0498fc3a536d.json', $this->translation_json( [ 'Payout history' => [ 'Historial de pagos' ] ] ) );

		try {
			$result = $this->merge( $this->translation_json( [] ) );
		} finally {
			remove_filter( 'locale', $to_es_es );
			unlink( $this->languages_dir . '/woocommerce-payments-es_ES-5c516589a5c8d922867c0498fc3a536d.json' );
		}

		$this->assertSame( [ 'Historial de pagos' ], $this->messages( $result )['Payout history'] );
	}

	public function test_load_script_textdomain_adds_the_chunks_to_the_entry_translations() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ], WP_LANG_DIR . '/plugins' );
		$this->write_shared_file( 'dist/index.js', $this->translation_json( [ 'Entry string' => [ 'Cadena' ] ] ) );

		$json = $this->load_with_core();

		$this->assertSame( [ '', 'Entry string', 'Payout history' ], array_keys( $this->messages( $json ) ) );
	}

	public function test_load_script_textdomain_provides_the_chunks_when_the_entry_has_no_translations() {
		$payouts = $this->chunk( 'payouts' );
		$this->write_chunk_map( [ $payouts => [ 'index.js' ] ] );
		$this->write_chunk( $payouts, [ 'Payout history' => [ 'Historial de pagos' ] ], WP_LANG_DIR . '/plugins' );

		$json = $this->load_with_core();

		$this->assertSame( [ '', 'Payout history' ], array_keys( $this->messages( $json ) ) );
	}

	/**
	 * Calls the merge filter the way WordPress does once it has read the entry's file.
	 *
	 * @param string $entry_json The entry's JSON.
	 *
	 * @return string
	 */
	private function merge( string $entry_json ) {
		return $this->sut->merge_chunk_translations( $entry_json, $this->languages_dir . '/entry.json', self::HANDLE, self::DOMAIN );
	}

	/**
	 * Registers the filters for real and lets WordPress load the entry's translations.
	 *
	 * @return string|false
	 */
	private function load_with_core() {
		$sut = new ChunkTranslations( new HooksProxy(), $this->dist_dir );
		$sut->init_hooks();
		wp_register_script( self::HANDLE, plugins_url( 'dist/index.js', WCPAY_PLUGIN_FILE ), [], '1', true );
		// The test plugin path gives WordPress a different relative path than a real install does.
		$relative_path = function () {
			return 'dist/index.js';
		};
		add_filter( 'load_script_textdomain_relative_path', $relative_path );

		try {
			return load_script_textdomain( self::HANDLE, self::DOMAIN );
		} finally {
			wp_deregister_script( self::HANDLE );
			remove_filter( 'load_script_textdomain_relative_path', $relative_path );
			remove_filter( 'load_script_translations', [ $sut, 'merge_chunk_translations' ], 10 );
			remove_filter( 'pre_load_script_translations', [ $sut, 'load_chunk_translations_only' ], 10 );
		}
	}

	/**
	 * Writes the translation file WordPress looks for a script in the shared languages folder.
	 *
	 * @param string $script Script path relative to the plugin, for example `dist/index.js`.
	 * @param string $json   File contents.
	 */
	private function write_shared_file( string $script, string $json ) {
		$dir = WP_LANG_DIR . '/plugins';
		wp_mkdir_p( $dir );
		$path = $dir . '/woocommerce-payments-' . determine_locale() . '-' . md5( $script ) . '.json';
		file_put_contents( $path, $json );
		$this->shared_files[] = $path;
	}

	/**
	 * Gets a chunk file name no real pack uses.
	 *
	 * @param string $name Short name.
	 *
	 * @return string
	 */
	private function chunk( string $name ): string {
		return 'chunks/' . $this->run_id . '-' . $name . '.js';
	}

	/**
	 * Writes the chunk map the production build creates.
	 *
	 * @param array $map Chunk file => entry files.
	 */
	private function write_chunk_map( array $map ) {
		file_put_contents( $this->dist_dir . '/i18n-chunk-entries.json', wp_json_encode( $map ) );
	}

	/**
	 * Writes a chunk's translation file where WordPress would put it.
	 *
	 * @param string      $chunk    Chunk file relative to `dist/`.
	 * @param array       $messages Messages without the header.
	 * @param string|null $dir      Folder to write to, the test languages folder by default.
	 * @param string      $key      Catalog key.
	 */
	private function write_chunk( string $chunk, array $messages, ?string $dir = null, string $key = 'messages' ) {
		$path = $this->chunk_path( $chunk, $dir );
		if ( null !== $dir ) {
			wp_mkdir_p( $dir );
			$this->shared_files[] = $path;
		}
		file_put_contents( $path, $this->translation_json( $messages, $key ) );
	}

	/**
	 * Gets the path of a chunk's translation file.
	 *
	 * @param string      $chunk Chunk file relative to `dist/`.
	 * @param string|null $dir   Folder, the test languages folder by default.
	 *
	 * @return string
	 */
	private function chunk_path( string $chunk, ?string $dir = null ): string {
		return ( $dir ?? $this->languages_dir ) . '/woocommerce-payments-' . determine_locale() . '-' . md5( 'dist/' . $chunk ) . '.json';
	}

	/**
	 * Builds translation JSON in the shape translate.wordpress.org writes.
	 *
	 * @param array  $messages Messages without the header.
	 * @param string $key      Catalog key.
	 *
	 * @return string
	 */
	private function translation_json( array $messages, string $key = 'messages' ): string {
		return wp_json_encode(
			[
				'translation-revision-date' => '2026-11-13 08:26:26+0000',
				'generator'                 => 'GlotPress/4.1.0',
				'domain'                    => 'messages',
				'locale_data'               => [ $key => [ '' => $this->header() ] + $messages ],
			]
		);
	}

	/**
	 * The header entry of the messages.
	 *
	 * @return array
	 */
	private function header(): array {
		return [
			'domain'       => 'messages',
			'plural-forms' => 'nplurals=2; plural=n != 1;',
			'lang'         => 'es',
		];
	}

	/**
	 * Gets the messages out of translation JSON.
	 *
	 * @param string $json Translation JSON.
	 *
	 * @return array
	 */
	private function messages( string $json ): array {
		$data = json_decode( $json, true );

		return $data['locale_data']['messages'] ?? $data['locale_data'][ self::DOMAIN ];
	}
}
