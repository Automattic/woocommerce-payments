<?php
/**
 * Class WCPay_Playground_Demo_Http_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Unit tests for the fixture-backed HTTP client of the Playground demo preview.
 *
 * The client lives outside the plugin, in dev/playground, so it is loaded by path.
 */
class WCPay_Playground_Demo_Http_Test extends WCPAY_UnitTestCase {

	const PREVIEW_DIR   = '/dev/playground/mu-plugins/wcpay-playground-preview';
	const SITE_API      = 'https://public-api.wordpress.com/wpcom/v2/sites/%s/wcpay/';
	const SITE_ROOT_API = 'https://public-api.wordpress.com/wpcom/v2/sites/%s/';

	/**
	 * Temporary fixtures directory.
	 *
	 * @var string
	 */
	private $fixtures_dir;

	/**
	 * System under test.
	 *
	 * @var WCPay_Playground_Demo_Http
	 */
	private $http;

	/**
	 * The error_log setting to restore after each test.
	 *
	 * @var string
	 */
	private $error_log;

	/**
	 * Pre-test setup.
	 */
	public function set_up() {
		parent::set_up();

		require_once WCPAY_ABSPATH . 'includes/wc-payment-api/class-wc-payments-http-interface.php';
		require_once dirname( __DIR__, 3 ) . self::PREVIEW_DIR . '/class-wcpay-playground-demo-http.php';

		$this->fixtures_dir = trailingslashit( get_temp_dir() ) . 'wcpay-playground-fixtures-' . wp_generate_password( 8, false );

		$this->write_file( 'GET/deposits/overview-all.json', '{"balance":{"pending":[]}}' );
		$this->write_file( 'GET/charges/ch_3AbC.json', '{"id":"ch_3AbC"}' );
		$this->write_file( 'GET/transact/currency/rates.json', '{"usd":1}' );
		$this->write_file( 'GET/broken.json', '{ not json' );
		$this->write_file(
			'GET/dates.json',
			'{"created":"@timestamp:-2 days","created_ms":"@timestamp_ms:-2 days","date":"@datetime:-2 days","label":"@someone","nested":{"due":"@datetime:+1 day"}}'
		);
		// Sits one level above the per-method directories, where a `../` route would land.
		$this->write_file( 'outside.json', '{"leaked":true}' );

		$this->http = new WCPay_Playground_Demo_Http( $this->fixtures_dir );

		// The client logs every request it has no fixture for; keep that out of the test output.
		$this->error_log = (string) ini_set( 'error_log', '/dev/null' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	}

	/**
	 * Post-test cleanup.
	 */
	public function tear_down() {
		ini_set( 'error_log', $this->error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->fixtures_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $files as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $this->fixtures_dir );

		parent::tear_down();
	}

	public function test_answers_a_request_from_the_fixture_named_after_it() {
		$response = $this->request( 'GET', self::SITE_API . 'deposits/overview-all' );

		$this->assertSame( 200, $response['code'] );
		$this->assertSame( [ 'balance' => [ 'pending' => [] ] ], $response['body'] );
	}

	public function test_ignores_the_query_string() {
		$response = $this->request( 'GET', self::SITE_API . 'deposits/overview-all?page=1&pagesize=25' );

		$this->assertSame( 200, $response['code'] );
	}

	public function test_finds_site_endpoints_outside_the_wcpay_namespace() {
		$response = $this->request( 'GET', self::SITE_ROOT_API . 'transact/currency/rates' );

		$this->assertSame( [ 'usd' => 1 ], $response['body'] );
	}

	public function test_accepts_ids_with_uppercase_letters() {
		$response = $this->request( 'GET', self::SITE_API . 'charges/ch_3AbC' );

		$this->assertSame( [ 'id' => 'ch_3AbC' ], $response['body'] );
	}

	public function test_request_without_a_fixture_gets_an_error_that_names_it() {
		$response = $this->request( 'GET', self::SITE_API . 'transactions' );

		$this->assertSame( 400, $response['code'] );
		$this->assertSame( 'wcpay_playground_no_demo_data', $response['body']['code'] );
		$this->assertStringContainsString( 'GET transactions', $response['body']['message'] );
	}

	public function test_fixture_for_one_method_does_not_answer_another() {
		$response = $this->request( 'POST', self::SITE_API . 'deposits/overview-all' );

		$this->assertSame( 400, $response['code'] );
	}

	public function test_fixture_that_is_not_json_counts_as_missing() {
		$response = $this->request( 'GET', self::SITE_API . 'broken' );

		$this->assertSame( 400, $response['code'] );
	}

	/**
	 * @dataProvider provider_routes_that_are_not_plain_paths
	 */
	public function test_route_cannot_reach_a_file_outside_its_method_directory( string $route ) {
		$response = $this->request( 'GET', self::SITE_API . $route );

		$this->assertSame( 400, $response['code'] );
		$this->assertSame( 'wcpay_playground_no_demo_data', $response['body']['code'] );
	}

	public function provider_routes_that_are_not_plain_paths(): array {
		return [
			'parent directory'         => [ '../outside' ],
			'parent directory, nested' => [ 'deposits/../../outside' ],
			'encoded parent directory' => [ '%2e%2e/outside' ],
			'file extension'           => [ 'deposits/overview-all.json' ],
			'empty segment'            => [ 'deposits//overview-all' ],
		];
	}

	public function test_resolves_relative_dates() {
		$two_days_ago = time() - 2 * DAY_IN_SECONDS;

		$body = $this->request( 'GET', self::SITE_API . 'dates' )['body'];

		$this->assertEqualsWithDelta( $two_days_ago, $body['created'], 5 );
		$this->assertEqualsWithDelta( $two_days_ago * 1000, $body['created_ms'], 5000 );
		$this->assertEqualsWithDelta( $two_days_ago, strtotime( $body['date'] . ' UTC' ), 5 );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $body['date'] );
		$this->assertEqualsWithDelta( time() + DAY_IN_SECONDS, strtotime( $body['nested']['due'] . ' UTC' ), 5 );
	}

	public function test_leaves_other_strings_starting_with_an_at_sign_alone() {
		$body = $this->request( 'GET', self::SITE_API . 'dates' )['body'];

		$this->assertSame( '@someone', $body['label'] );
	}

	public function test_reports_a_connection() {
		$this->assertTrue( $this->http->is_connected() );
		$this->assertTrue( $this->http->has_connection_owner() );
		$this->assertTrue( $this->http->is_user_connected() );
	}

	public function test_every_shipped_fixture_is_served() {
		$shipped_dir = dirname( __DIR__, 3 ) . self::PREVIEW_DIR . '/fixtures';
		$http        = new WCPay_Playground_Demo_Http( $shipped_dir );
		$files       = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $shipped_dir, FilesystemIterator::SKIP_DOTS ) );
		$not_served  = [];

		foreach ( $files as $file ) {
			// A fixture path is `<METHOD>/<route>.json`.
			$relative        = substr( $file->getPathname(), strlen( $shipped_dir ) + 1, -strlen( '.json' ) );
			[ $method, $route ] = explode( '/', $relative, 2 );
			$response        = $http->remote_request(
				[
					'method' => $method,
					'url'    => self::SITE_API . $route,
				]
			);

			if ( 200 !== $response['response']['code'] ) {
				$not_served[] = $relative;
			}
		}

		$this->assertGreaterThan( 0, iterator_count( $files ) );
		$this->assertSame( [], $not_served );
	}

	/**
	 * Sends a request through the client and decodes what comes back.
	 *
	 * @param string $method HTTP method.
	 * @param string $url    Request URL.
	 *
	 * @return array Status code and decoded body.
	 */
	private function request( string $method, string $url ): array {
		$response = $this->http->remote_request(
			[
				'method' => $method,
				'url'    => $url,
			]
		);

		return [
			'code' => $response['response']['code'],
			'body' => json_decode( $response['body'], true ),
		];
	}

	/**
	 * Writes a file under the temporary fixtures directory.
	 *
	 * @param string $path     Path relative to the fixtures directory.
	 * @param string $contents File contents.
	 */
	private function write_file( string $path, string $contents ) {
		$file = $this->fixtures_dir . '/' . $path;

		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
}
