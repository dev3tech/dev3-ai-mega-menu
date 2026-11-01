<?php
/**
 * Shared test helpers: fake Gemini over pre_http_request, menu factories, REST calls.
 *
 * @package Dev3\AiMegaMenu
 */

use Dev3\AiMegaMenu\Ai\Prompts_Library;
use Dev3\AiMegaMenu\Core3\Api\Ai;
use Dev3\AiMegaMenu\Core3\Core\Cache;
use Dev3\AiMegaMenu\Core3\Core\Schema;
use Dev3\AiMegaMenu\Core3\Core\Settings_Store;
use Dev3\AiMegaMenu\Core3\Data\Usage_Repository;

/**
 * Base class for plugin tests.
 */
abstract class Dev3mm_TestCase extends WP_UnitTestCase {

	/**
	 * Captured Gemini requests.
	 *
	 * @var array
	 */
	protected $requests = array();

	/**
	 * Queue of fake responses ([ status, body ] or callables); the last one repeats.
	 *
	 * @var array
	 */
	protected $responses = array();

	public function set_up() {
		parent::set_up();
		Schema::install( DEV3MM_DB_VERSION );
		update_option( Settings_Store::OPTION, array() );
		Settings_Store::flush();
		Usage_Repository::flush();
		Cache::reset_memo();
		Ai::$client = null;
		Prompts_Library::register();
		delete_option( 'dev3mm_locations' );
		delete_option( 'dev3mm_themes' );
		$this->requests  = array();
		$this->responses = array();
		add_filter( 'dev3mm_retry_delay_ms', '__return_zero' );
		add_filter( 'pre_http_request', array( $this, 'fake_http' ), 10, 3 );
		\Dev3\AiMegaMenu\Front\Renderer::reset();
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_http' ), 10 );
		remove_filter( 'dev3mm_retry_delay_ms', '__return_zero' );
		parent::tear_down();
	}

	protected function with_key() {
		Settings_Store::update( array( 'api_key' => 'AIza-test-key-000000000000' ) );
		Settings_Store::flush();
	}

	public function fake_http( $pre, $args, $url ) {
		if ( false === strpos( (string) $url, 'generativelanguage.googleapis.com' ) ) {
			return $pre;
		}
		$this->requests[] = array(
			'url'  => $url,
			'body' => json_decode( (string) $args['body'], true ),
		);
		$next = count( $this->responses ) > 1 ? array_shift( $this->responses ) : ( $this->responses ? $this->responses[0] : array( 500, array() ) );
		if ( is_callable( $next ) ) {
			$next = $next( end( $this->requests ) );
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $next[1] ),
			'response' => array(
				'code'    => $next[0],
				'message' => 'x',
			),
			'cookies'  => array(),
		);
	}

	protected function json_response( array $data ) {
		return array(
			200,
			array(
				'candidates'    => array(
					array(
						'content'      => array( 'parts' => array( array( 'text' => wp_json_encode( $data ) ) ) ),
						'finishReason' => 'STOP',
					),
				),
				'usageMetadata' => array(
					'promptTokenCount'     => 120,
					'candidatesTokenCount' => 80,
					'totalTokenCount'      => 200,
				),
			),
		);
	}

	/**
	 * Prompt text of the last Gemini request.
	 *
	 * @return string
	 */
	protected function last_prompt() {
		$req = end( $this->requests );
		return (string) $req['body']['contents'][0]['parts'][0]['text'];
	}

	/**
	 * A 3-level menu: Shop (Men > Shirts, Pants; Women > Dresses), About (Team), Contact.
	 *
	 * @return array { menu, ids }
	 */
	protected function make_menu() {
		$menu  = wp_create_nav_menu( 'Main ' . wp_generate_password( 4, false ) );
		$about = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'About us',
			)
		);
		$ids   = array();
		$add   = static function ( $title, $parent = 0, $extra = array() ) use ( $menu, &$ids ) {
			$id             = wp_update_nav_menu_item(
				$menu,
				0,
				array_merge(
					array(
						'menu-item-title'     => $title,
						'menu-item-url'       => 'https://example.org/' . sanitize_title( $title ),
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => $parent,
					),
					$extra
				)
			);
			$ids[ $title ] = $id;
			return $id;
		};
		$shop  = $add( 'Shop' );
		$men   = $add( 'Men', $shop );
		$add( 'Shirts', $men );
		$add( 'Pants', $men );
		$women = $add( 'Women', $shop );
		$add( 'Dresses', $women );
		$abt   = $add(
			'About',
			0,
			array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $about,
			)
		);
		$add( 'Team', $abt );
		$add( 'Contact' );
		return array(
			'menu' => (int) $menu,
			'ids'  => $ids,
			'page' => $about,
		);
	}

	/**
	 * REST call as the current user (with a valid nonce).
	 *
	 * @param string $method Method.
	 * @param string $route  Route under dev3mm/v1.
	 * @param array  $params Params.
	 * @param bool   $nonce  Send a nonce.
	 * @return WP_REST_Response
	 */
	protected function rest( $method, $route, array $params = array(), $nonce = true ) {
		$req = new WP_REST_Request( $method, '/dev3mm/v1/' . ltrim( $route, '/' ) );
		if ( $nonce ) {
			$req->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		}
		foreach ( $params as $k => $v ) {
			$req->set_param( $k, $v );
		}
		return rest_do_request( $req );
	}

	protected function admin() {
		$id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $id );
		return $id;
	}
}
