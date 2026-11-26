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

		$base                = trailingslashit( get_temp_dir() ) . 'wcpay-chunk-translations-' . uniqid();
		$this->dist_dir      = $base . '/dist';
		$this->languages_dir = $base . '/languages';
		mkdir( $this->dist_dir, 0777, true );
		mkdir( $this->languages_dir, 0777, true );

		$this->sut = new ChunkTranslations( $this->createMock( HooksProxy::class ), $this->dist_dir );
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
		/**
		 * @var HooksProxy|MockObject $hooks_proxy
		 */
		$hooks_proxy = $this->createMock( HooksProxy::class );
		$hooks_proxy->expects( $this->exactly( 2 ) )
			->method( 'add_filter' )
			->withConsecutive(
				[ 'load_script_translations', [ $this->sut, 'merge_chunk_translations' ], 10, 4 ],
				[ 'pre_load_script_translations', [ $this->sut, 'load_chunk_translations_only' ], 10, 4 ]
			);

		( new ChunkTranslations( $hooks_proxy, $this->dist_dir ) )->init_hooks();
	}

	public function test_merge_adds_only_the_chunks_the_entry_loads() {
		$this->write_chunk_map(
			[
				'chunks/wcpay-payouts.js'  => [ 'index.js' ],
				'chunks/wcpay-capital.js'  => [ 'index.js', 'settings.js' ],
				'chunks/wcpay-settings.js' => [ 'settings.js' ],
			]
		);
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ] );
		$this->write_chunk( 'chunks/wcpay-capital.js', [ 'Capital' => [ 'Capital es' ] ] );
		$this->write_chunk( 'chunks/wcpay-settings.js', [ 'Settings only' => [ 'Solo ajustes' ] ] );

		$result = $this->merge( $this->entry_json( [ 'Entry string' => [ 'Cadena' ] ] ) );

		$this->assertEquals(
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
		$this->write_chunk_map( [ 'chunks/wcpay-payouts.js' => [ 'index.js' ] ] );
		$this->write_chunk(
			'chunks/wcpay-payouts.js',
			[
				'Shared' => [ 'From the chunk' ],
				'Chunk'  => [ 'Only chunk' ],
			]
		);

		$result = json_decode( $this->merge( $this->entry_json( [ 'Shared' => [ 'From the entry' ] ] ) ), true );

		$this->assertSame( 'messages', $result['domain'] );
		$this->assertSame( [ 'messages' ], array_keys( $result['locale_data'] ) );
		$this->assertSame( $this->header(), $result['locale_data']['messages'][''] );
		$this->assertSame( [ 'From the entry' ], $result['locale_data']['messages']['Shared'] );
		$this->assertSame( [ 'Only chunk' ], $result['locale_data']['messages']['Chunk'] );
	}

	public function test_merge_keeps_unicode_and_slashes_unescaped() {
		$this->write_chunk_map( [ 'chunks/wcpay-payouts.js' => [ 'index.js' ] ] );
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Bank' => [ 'بانک / Banco' ] ] );

		$result = $this->merge( $this->entry_json( [] ) );

		$this->assertStringContainsString( 'بانک / Banco', $result );
	}

	public function test_merge_uses_the_built_in_chunk_list_without_a_chunk_map() {
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ] );

		$result = $this->merge( $this->entry_json( [] ) );

		$this->assertSame( [ 'Historial de pagos' ], $this->messages( $result )['Payout history'] );
	}

	public function test_merge_uses_the_built_in_chunk_list_when_the_chunk_map_is_invalid() {
		file_put_contents( $this->dist_dir . '/i18n-chunk-entries.json', '{not json' );
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ] );

		$result = $this->merge( $this->entry_json( [] ) );

		$this->assertSame( [ 'Historial de pagos' ], $this->messages( $result )['Payout history'] );
	}

	public function test_merge_is_a_no_op_when_the_chunk_files_are_missing() {
		$this->write_chunk_map( [ 'chunks/wcpay-payouts.js' => [ 'index.js' ] ] );
		$entry_json = $this->entry_json( [ 'Entry string' => [ 'Cadena' ] ] );

		$this->assertSame( $entry_json, $this->merge( $entry_json ) );
	}

	public function test_merge_skips_invalid_chunk_files_and_merges_the_rest() {
		$this->write_chunk_map(
			[
				'chunks/wcpay-payouts.js' => [ 'index.js' ],
				'chunks/wcpay-capital.js' => [ 'index.js' ],
				'chunks/wcpay-reports.js' => [ 'index.js' ],
			]
		);
		file_put_contents( $this->chunk_path( 'chunks/wcpay-payouts.js' ), '{not json' );
		file_put_contents( $this->chunk_path( 'chunks/wcpay-reports.js' ), '{"locale_data":{"messages":"nope"}}' );
		$this->write_chunk( 'chunks/wcpay-capital.js', [ 'Capital' => [ 'Capital es' ] ] );

		$result = $this->merge( $this->entry_json( [] ) );

		$this->assertSame( [ '', 'Capital' ], array_keys( $this->messages( $result ) ) );
	}

	public function test_merge_returns_an_invalid_entry_json_untouched() {
		$this->write_chunk_map( [ 'chunks/wcpay-payouts.js' => [ 'index.js' ] ] );
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ] );

		$this->assertSame( '{not json', $this->merge( '{not json' ) );
	}

	public function test_merge_twice_adds_nothing_more() {
		$this->write_chunk_map( [ 'chunks/wcpay-payouts.js' => [ 'index.js' ] ] );
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ] );

		$once = $this->merge( $this->entry_json( [] ) );

		$this->assertSame( $once, $this->merge( $once ) );
	}

	public function test_merge_ignores_other_handles_and_domains() {
		$this->write_chunk_map( [ 'chunks/wcpay-payouts.js' => [ 'index.js' ] ] );
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ] );
		$entry_json = $this->entry_json( [] );
		$file       = $this->languages_dir . '/entry.json';

		$this->assertSame( $entry_json, $this->sut->merge_chunk_translations( $entry_json, $file, 'WCPAY_TOS', self::DOMAIN ) );
		$this->assertSame( $entry_json, $this->sut->merge_chunk_translations( $entry_json, $file, self::HANDLE, 'woocommerce' ) );
		$this->assertSame( $entry_json, $this->sut->merge_chunk_translations( $entry_json, $file, [ self::HANDLE ], self::DOMAIN ) );
		$this->assertFalse( $this->sut->merge_chunk_translations( false, $file, self::HANDLE, self::DOMAIN ) );
		$this->assertSame( $entry_json, $this->sut->merge_chunk_translations( $entry_json, false, self::HANDLE, self::DOMAIN ) );
	}

	public function test_merge_uses_the_settings_entry_chunks_for_the_settings_handle() {
		$this->write_chunk_map(
			[
				'chunks/wcpay-payouts.js' => [ 'index.js' ],
				'chunks/wcpay-phones.js'  => [ 'settings.js' ],
			]
		);
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ] );
		$this->write_chunk( 'chunks/wcpay-phones.js', [ 'Phone' => [ 'Teléfono' ] ] );

		$result = $this->sut->merge_chunk_translations( $this->entry_json( [] ), $this->languages_dir . '/entry.json', 'WCPAY_ADMIN_SETTINGS', self::DOMAIN );

		$this->assertSame( [ '', 'Phone' ], array_keys( $this->messages( $result ) ) );
	}

	public function test_load_chunk_translations_only_builds_json_when_the_entry_has_none() {
		$this->write_chunk_map( [ 'chunks/wcpay-payouts.js' => [ 'index.js' ] ] );
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ], WP_LANG_DIR . '/plugins' );

		$result = $this->sut->load_chunk_translations_only( null, false, self::HANDLE, self::DOMAIN );

		$this->assertSame( [ 'Historial de pagos' ], $this->messages( $result )['Payout history'] );
		$this->assertSame( $this->header(), $this->messages( $result )[''] );
	}

	public function test_load_chunk_translations_only_returns_null_when_no_chunk_has_translations() {
		$this->write_chunk_map( [ 'chunks/wcpay-payouts.js' => [ 'index.js' ] ] );

		$this->assertNull( $this->sut->load_chunk_translations_only( null, false, self::HANDLE, self::DOMAIN ) );
	}

	public function test_load_chunk_translations_only_leaves_other_calls_alone() {
		$this->write_chunk_map( [ 'chunks/wcpay-payouts.js' => [ 'index.js' ] ] );
		$this->write_chunk( 'chunks/wcpay-payouts.js', [ 'Payout history' => [ 'Historial de pagos' ] ], WP_LANG_DIR . '/plugins' );

		$this->assertNull( $this->sut->load_chunk_translations_only( null, '/some/missing.json', self::HANDLE, self::DOMAIN ) );
		$this->assertSame( '{"set":"by another callback"}', $this->sut->load_chunk_translations_only( '{"set":"by another callback"}', false, self::HANDLE, self::DOMAIN ) );
		$this->assertNull( $this->sut->load_chunk_translations_only( null, false, 'WCPAY_TOS', self::DOMAIN ) );
		$this->assertNull( $this->sut->load_chunk_translations_only( null, false, self::HANDLE, 'woocommerce' ) );
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
	 */
	private function write_chunk( string $chunk, array $messages, ?string $dir = null ) {
		$path = $this->chunk_path( $chunk, $dir );
		if ( null !== $dir ) {
			wp_mkdir_p( $dir );
			$this->shared_files[] = $path;
		}
		file_put_contents( $path, $this->entry_json( $messages ) );
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
	 * @param array $messages Messages without the header.
	 *
	 * @return string
	 */
	private function entry_json( array $messages ): string {
		return wp_json_encode(
			[
				'translation-revision-date' => '2026-11-13 08:26:26+0000',
				'generator'                 => 'GlotPress/4.1.0',
				'domain'                    => 'messages',
				'locale_data'               => [ 'messages' => [ '' => $this->header() ] + $messages ],
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
		return json_decode( $json, true )['locale_data']['messages'];
	}
}
