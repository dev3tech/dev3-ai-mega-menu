<?php
/**
 * Versioned prompt templates and output schemas for every DEV3 AI feature of the plugin.
 *
 * Override with the `dev3mm_prompt_<id>` filter (e.g. `dev3mm_prompt_dev3mm_build_menu`).
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Ai;

use Dev3\AiMegaMenu\Core3\Api\Prompts;
use Dev3\AiMegaMenu\Menu\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Registers prompts on init.
 */
class Prompts_Library {

	const BUILD  = 'dev3mm_build_menu';
	const LABELS = 'dev3mm_labels';
	const AUDIT  = 'dev3mm_audit';
	const PROMO  = 'dev3mm_promo';
	const ICONS  = 'dev3mm_icons';

	/**
	 * String schema helper.
	 *
	 * @param int $max Max length.
	 * @return array
	 */
	private static function str( $max ) {
		return array(
			'type'      => 'STRING',
			'maxLength' => $max,
		);
	}

	/**
	 * Registers all templates.
	 */
	public static function register() {
		$icons = Icons::names();
		$link  = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'label' => self::str( 40 ),
				'ref'   => self::str( 120 ),
			),
			'required'   => array( 'label', 'ref' ),
		);
		$group = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'label'       => self::str( 40 ),
				'ref'         => self::str( 120 ),
				'description' => self::str( 90 ),
				'icon'        => self::str( 24 ),
				'links'       => array(
					'type'     => 'ARRAY',
					'maxItems' => 12,
					'items'    => $link,
				),
			),
			'required'   => array( 'label', 'ref' ),
		);

		Prompts::register(
			self::BUILD,
			array(
				'version'    => '1',
				'task'       => 'build_menu',
				'profile'    => 'heavy',
				'max_tokens' => 16384,
				'system'     => 'You are an information architect who designs clear, scannable website mega menus. You only link to content that exists in the inventory you are given, using its exact ref. Write every label in {{language}}.',
				'template'   => "Design the main navigation for the website \"{{site}}\" ({{tagline}}).\n\nRules:\n- At most {{max_top}} top-level items, ordered by importance for visitors.\n- A top-level item is type \"mega\" when it holds 2 or more groups (columns), \"flyout\" for a short list of up to 8 links, or \"link\" with no children.\n- Groups are columns: a heading (label + ref) and up to 12 links. Group related pages, categories and products; keep 3 levels at most.\n- Labels: short (1-3 words, at most 24 characters), clear, no marketing fluff, Title Case where the language uses it.\n- Optional group description: one short phrase (max 80 characters).\n- Refs must come from the inventory: \"page:ID\", \"category:ID\", \"product_cat:ID\", \"product:ID\", or the specials \"home\", \"shop\", \"blog\". Use \"#\" only for a top-level item that is purely a container.\n- icon: one of [{{icons}}] that fits the item, or \"\".\n- Put legal, cart, checkout and account pages last or leave them out. {{notes}}\n\nINVENTORY\n{{inventory}}",
				'schema'     => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'items' => array(
							'type'     => 'ARRAY',
							'maxItems' => 10,
							'items'    => array(
								'type'       => 'OBJECT',
								'properties' => array(
									'label'  => self::str( 40 ),
									'ref'    => self::str( 120 ),
									'type'   => array(
										'type' => 'STRING',
										'enum' => array( 'mega', 'flyout', 'link' ),
									),
									'icon'   => self::str( 24 ),
									'groups' => array(
										'type'     => 'ARRAY',
										'maxItems' => 6,
										'items'    => $group,
									),
								),
								'required'   => array( 'label', 'ref', 'type' ),
							),
						),
					),
					'required'   => array( 'items' ),
				),
			)
		);

		Prompts::register(
			self::LABELS,
			array(
				'version'    => '1',
				'task'       => 'menu_labels',
				'profile'    => 'fast',
				'max_tokens' => 8192,
				'cache'      => DAY_IN_SECONDS,
				'system'     => 'You write navigation labels and short menu descriptions that are clear, specific and scannable. Write in {{language}}.',
				'template'   => "Improve the labels and write short descriptions for these menu items of \"{{site}}\".\n- label: 1-3 words, at most 24 characters, keeps the meaning, no emojis, no trailing punctuation. Keep a label that is already clear.\n- description: one helpful phrase of at most 70 characters telling visitors what they will find.\n- Return every item id you were given.\n\nITEMS (id | current label | parent | links to)\n{{items}}",
				'schema'     => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'items' => array(
							'type'     => 'ARRAY',
							'maxItems' => 200,
							'items'    => array(
								'type'       => 'OBJECT',
								'properties' => array(
									'id'          => array( 'type' => 'INTEGER' ),
									'label'       => self::str( 40 ),
									'description' => self::str( 100 ),
								),
								'required'   => array( 'id', 'label', 'description' ),
							),
						),
					),
					'required'   => array( 'items' ),
				),
			)
		);

		Prompts::register(
			self::AUDIT,
			array(
				'version'    => '1',
				'task'       => 'menu_audit',
				'profile'    => 'fast',
				'max_tokens' => 4096,
				'system'     => 'You are a UX reviewer specialising in website navigation and accessibility. Be concrete and brief. Write in {{language}}.',
				'template'   => "Review this menu of \"{{site}}\" for usability problems: unclear or jargon labels, poor grouping, items in the wrong place, and important pages that are missing. Do not repeat these already-found issues: {{known}}.\nFor an unclear label give suggested_label. For a missing link give suggested_ref from the candidates. Return at most 10 issues, most important first; return none if the menu is good.\n\nMENU (indent = level; [id] label -> target)\n{{menu}}\n\nPAGES NOT IN THE MENU (ref | title)\n{{candidates}}",
				'schema'     => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'score'  => array( 'type' => 'INTEGER' ),
						'issues' => array(
							'type'     => 'ARRAY',
							'maxItems' => 10,
							'items'    => array(
								'type'       => 'OBJECT',
								'properties' => array(
									'severity'        => array(
										'type' => 'STRING',
										'enum' => array( 'high', 'medium', 'low' ),
									),
									'code'            => array(
										'type' => 'STRING',
										'enum' => array( 'unclear_label', 'grouping', 'missing_link', 'too_deep', 'duplicate', 'other' ),
									),
									'item_id'         => array( 'type' => 'INTEGER' ),
									'title'           => self::str( 90 ),
									'detail'          => self::str( 240 ),
									'suggested_label' => self::str( 40 ),
									'suggested_ref'   => self::str( 60 ),
								),
								'required'   => array( 'severity', 'code', 'title', 'detail' ),
							),
						),
					),
					'required'   => array( 'issues' ),
				),
			)
		);

		Prompts::register(
			self::PROMO,
			array(
				'version'    => '1',
				'task'       => 'menu_promo',
				'profile'    => 'fast',
				'max_tokens' => 2048,
				'system'     => 'You are a retail copywriter. Write short, honest promotional copy (no fake discounts or invented facts). Write in {{language}}.',
				'template'   => "Write a promo block for the mega menu of \"{{site}}\" for this occasion: {{occasion}}. Tone: {{tone}}.\n- badge: 1-2 words (e.g. \"Limited time\").\n- title: max 48 characters. text: one or two sentences, max 160 characters.\n- cta_label: 2-4 words.\n- product_ids: pick up to 3 of the candidate products that fit the occasion best (ids only from the list).\n{{offer}}\n\nCANDIDATE PRODUCTS (id | name | price | category | on sale)\n{{products}}",
				'schema'     => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'badge'       => self::str( 24 ),
						'title'       => self::str( 60 ),
						'text'        => self::str( 200 ),
						'cta_label'   => self::str( 40 ),
						'product_ids' => array(
							'type'     => 'ARRAY',
							'maxItems' => 3,
							'items'    => array( 'type' => 'INTEGER' ),
						),
					),
					'required'   => array( 'title', 'text', 'cta_label' ),
				),
			)
		);

		Prompts::register(
			self::ICONS,
			array(
				'version'    => '1',
				'task'       => 'menu_icons',
				'profile'    => 'fast',
				'max_tokens' => 4096,
				'cache'      => DAY_IN_SECONDS,
				'system'     => 'You match website menu items to icons from a fixed icon set and suggest stock-image search words.',
				'template'   => "For each menu item choose the best icon name from this set: [{{icons}}]. Use \"\" when nothing fits. Also give image_query: 1-3 English words to find a fitting photo in the media library.\n\nITEMS (id | label | parent)\n{{items}}",
				'schema'     => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'items' => array(
							'type'     => 'ARRAY',
							'maxItems' => 200,
							'items'    => array(
								'type'       => 'OBJECT',
								'properties' => array(
									'id'          => array( 'type' => 'INTEGER' ),
									'icon'        => array(
										'type' => 'STRING',
										'enum' => array_merge( array( '' ), $icons ),
									),
									'image_query' => self::str( 40 ),
								),
								'required'   => array( 'id', 'icon' ),
							),
						),
					),
					'required'   => array( 'items' ),
				),
			)
		);
	}

	/**
	 * Language for AI copy (setting, else the site locale).
	 *
	 * @return string
	 */
	public static function language() {
		$setting = (string) \Dev3\AiMegaMenu\Core3\Core\Settings_Store::get( 'ai_language', '' );
		if ( '' !== trim( $setting ) ) {
			return $setting;
		}
		$locale = determine_locale();
		$names  = array(
			'en' => 'English',
			'de' => 'German',
			'fr' => 'French',
			'es' => 'Spanish',
			'it' => 'Italian',
			'pt' => 'Portuguese',
			'nl' => 'Dutch',
			'sv' => 'Swedish',
			'da' => 'Danish',
			'nb' => 'Norwegian',
			'fi' => 'Finnish',
			'pl' => 'Polish',
			'cs' => 'Czech',
			'tr' => 'Turkish',
			'ru' => 'Russian',
			'uk' => 'Ukrainian',
			'ar' => 'Arabic',
			'he' => 'Hebrew',
			'hi' => 'Hindi',
			'ja' => 'Japanese',
			'ko' => 'Korean',
			'zh' => 'Chinese',
			'id' => 'Indonesian',
			'vi' => 'Vietnamese',
			'th' => 'Thai',
			'el' => 'Greek',
			'ro' => 'Romanian',
			'hu' => 'Hungarian',
		);
		$code   = strtolower( substr( $locale, 0, 2 ) );
		return ( isset( $names[ $code ] ) ? $names[ $code ] : 'the language of locale ' . $locale ) . ' (' . $locale . ')';
	}

	/**
	 * Common template vars.
	 *
	 * @return array
	 */
	public static function site_vars() {
		return array(
			'site'     => wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
			'tagline'  => wp_strip_all_tags( (string) get_bloginfo( 'description' ) ),
			'language' => self::language(),
			'icons'    => implode( ', ', Icons::names() ),
		);
	}
}
