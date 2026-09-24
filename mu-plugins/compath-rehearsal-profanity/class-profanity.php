<?php
/**
 * Profanity filter for song titles and other choir text.
 *
 * Reusable from the Lite/Demo plugin (setting choir_rehearsal_block_profanity,
 * on by default for the Demo build) and from the demo must-use plugin, which
 * forces it on for every user who cannot manage site settings.
 *
 * Filters:
 * - choir_rehearsal_block_profanity (bool): turn enforcement on or off.
 * - choir_rehearsal_profanity_admin_bypass (bool): site managers skip the check.
 * - choir_rehearsal_profanity_lists (array): extend block and allow lists.
 * - choir_rehearsal_profanity_post_types (string[]): post types that are checked.
 * - choir_rehearsal_profanity_taxonomies (string[]): taxonomies that are checked.
 *
 * There is no playlist post type. Part names are the choir_voice_type taxonomy.
 *
 * @package Compath_Choir_Rehearsal
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Choir_Rehearsal_Profanity', false ) ) :

	final class Choir_Rehearsal_Profanity {

		public const TEXT_DOMAIN = 'compath-choir-rehearsal';

		public const OPTION = 'choir_rehearsal_block_profanity';

		public const MESSAGE_TITLE = 'Please use appropriate language in the song title.';

		public const MESSAGE_NOTES = 'Please use appropriate language in the description.';

		public const MESSAGE_PART = 'Please use appropriate language in the part name.';

		public const MESSAGE_FILE = 'Please use appropriate language in the file name.';

		public const MESSAGE_CATEGORY = 'Please use appropriate language in the category name.';

		public const MESSAGE_SETTING = 'Block profanity in song titles, notes, part names, and file names.';

		public const MESSAGE_SETTING_HELP = 'When this is on, those fields are rejected for everyone except a user who can manage site settings. The Demo build starts with this on. The demo must-use plugin keeps it on for the shared accounts.';

		private const TRANSIENT = 'choir_rehearsal_profanity_notice_';

		private static bool $hooks_registered = false;

		private static bool $force_for_non_admins = false;

		private static ?bool $test_actor = null;

		/**
		 * @var array<string, string>
		 */
		private const CYR_TO_LAT_C = array(
			'а' => 'a',
			'е' => 'e',
			'о' => 'o',
			'р' => 'p',
			'с' => 'c',
			'х' => 'x',
			'у' => 'u',
			'к' => 'k',
			'і' => 'i',
			'ї' => 'i',
			'ј' => 'j',
			'һ' => 'h',
			'ѕ' => 's',
		);

		/**
		 * Same homoglyphs, with Cyrillic es folded to Latin s (асс → ass).
		 *
		 * @var array<string, string>
		 */
		private const CYR_TO_LAT_S = array(
			'а' => 'a',
			'е' => 'e',
			'о' => 'o',
			'р' => 'p',
			'с' => 's',
			'х' => 'x',
			'у' => 'u',
			'к' => 'k',
			'і' => 'i',
			'ї' => 'i',
			'ј' => 'j',
			'һ' => 'h',
			'ѕ' => 's',
		);

		/**
		 * @var array<string, string>
		 */
		private const LAT_TO_CYR = array(
			'a' => 'а',
			'e' => 'е',
			'o' => 'о',
			'p' => 'р',
			'c' => 'с',
			'x' => 'х',
			'y' => 'у',
			'k' => 'к',
			'i' => 'и',
		);

		public static function register(): void {
			if ( self::$hooks_registered || ! function_exists( 'add_filter' ) ) {
				return;
			}
			self::$hooks_registered = true;

			add_filter( 'wp_insert_post_data', array( self::class, 'filter_insert_post_data' ), 9, 2 );
			add_filter( 'wp_insert_post_data', array( self::class, 'filter_insert_post_data' ), 30, 2 );
			add_filter( 'rest_pre_insert_choir_song', array( self::class, 'filter_rest_pre_insert' ), 10, 2 );
			add_filter( 'rest_pre_insert_choir_track', array( self::class, 'filter_rest_pre_insert' ), 10, 2 );
			add_filter( 'rest_pre_insert_attachment', array( self::class, 'filter_rest_pre_insert' ), 10, 2 );
			add_filter( 'rest_pre_insert_choir_voice_type', array( self::class, 'filter_rest_pre_insert_term' ), 10, 2 );
			add_filter( 'pre_insert_term', array( self::class, 'filter_pre_insert_term' ), 10, 2 );
			add_filter( 'wp_handle_upload_prefilter', array( self::class, 'filter_upload' ) );
			add_action( 'admin_notices', array( self::class, 'render_admin_notice' ) );
			add_filter( 'gettext', array( self::class, 'filter_gettext' ), 10, 3 );
		}

		/**
		 * Demo must-use plugin: check every user who cannot manage site settings.
		 */
		public static function register_demo(): void {
			self::$force_for_non_admins = true;
			self::register();
			if ( function_exists( 'add_filter' ) ) {
				add_filter( 'choir_rehearsal_block_profanity', array( self::class, 'filter_force_demo' ), 1000 );
			}
		}

		public static function demo_enforced(): bool {
			return self::$force_for_non_admins;
		}

		public static function enabled_for_channel( string $channel ): bool {
			return 'demo' === $channel;
		}

		public static function setting_enabled(): bool {
			$default = class_exists( 'Choir_Rehearsal_Distribution' ) && self::enabled_for_channel( Choir_Rehearsal_Distribution::channel() );
			if ( ! function_exists( 'get_option' ) ) {
				return $default;
			}
			$value = get_option( self::OPTION, $default );
			if ( is_bool( $value ) ) {
				return $value;
			}
			return '1' === (string) $value || 1 === $value;
		}

		public static function register_setting(): void {
			if ( ! function_exists( 'register_setting' ) ) {
				return;
			}
			register_setting(
				'choir_rehearsal_settings',
				self::OPTION,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => static fn( $value ) => '1' === (string) $value || 1 === $value || true === $value,
					'default'           => class_exists( 'Choir_Rehearsal_Distribution' ) && self::enabled_for_channel( Choir_Rehearsal_Distribution::channel() ),
				)
			);
		}

		/**
		 * @param mixed $block Current decision.
		 */
		public static function filter_force_demo( $block ): bool {
			if ( self::actor_is_admin() ) {
				return (bool) $block;
			}
			return true;
		}

		public static function should_block(): bool {
			$block = self::$force_for_non_admins || self::setting_enabled();
			if ( function_exists( 'apply_filters' ) ) {
				$block = (bool) apply_filters( 'choir_rehearsal_block_profanity', $block );
			}
			if ( self::actor_is_admin() ) {
				$bypass = true;
				if ( function_exists( 'apply_filters' ) ) {
					$bypass = (bool) apply_filters( 'choir_rehearsal_profanity_admin_bypass', true );
				}
				if ( $bypass ) {
					return false;
				}
			}
			return $block;
		}

		public static function set_test_actor( ?bool $is_admin ): void {
			self::$test_actor = $is_admin;
		}

		public static function reset_for_tests(): void {
			self::$hooks_registered     = false;
			self::$force_for_non_admins = false;
			self::$test_actor           = null;
		}

		/**
		 * @return array<string, list<string>>
		 */
		public static function default_lists(): array {
			return array(
				'block_substrings'      => array(
					'fuck',
					'shit',
					'bitch',
					'cunt',
					'slut',
					'whore',
					'bastard',
					'bollock',
					'пизд',
					'гандон',
					'гондон',
					'залуп',
				),
				'block_tokens'          => array(
					'hui',
					'huy',
					'huja',
					'huya',
					'xuy',
					'pizda',
					'pizdec',
					'pizdets',
					'blyad',
					'blyat',
					'bljad',
					'suka',
					'suki',
					'mudak',
					'zalupa',
					'gandon',
					'ebat',
					'ebal',
					'eban',
					'nigger',
					'niggers',
					'nigga',
					'niggas',
				),
				'block_token_patterns'  => array(
					'/^(?:jack|dumb|bad)?ass(?:es|hole|holes|hat|hats)?$/u',
					'/^tit(?:s|ties|ty)?$/u',
					'/^cum(?:s|ming|shot|shots)?$/u',
					'/^dick(?:s|head|heads)?$/u',
					'/^cock(?:s|sucker|suckers)?$/u',
					'/^fag(?:s|got|gots)?$/u',
					'/^twats?$/u',
					'/^wank(?:er|ers|ing)?$/u',
					'/^boners?$/u',
					'/^puss(?:y|ies)$/u',
					'/^piss(?:ed|er|ers|ing)?$/u',
					'/^niggers?$/u',
					'/^niggas?$/u',
				),
				'allow_tokens'          => array(
					'scunthorpe',
					'scunthorpes',
					'shiitake',
					'shitake',
					'dickinson',
					'dickens',
					'cocktail',
					'peacock',
					'cockatiel',
					'cockburn',
					'hancock',
					'litsents',
					'litsentsi',
					'raiskama',
					'raiskamine',
					'raiskab',
					'raiskavad',
					'raiskaja',
					'raisatud',
				),
				'allow_stems'           => array(
					'истреб',
					'рубл',
					'оскорб',
					'сукн',
					'персик',
					'учеб',
					'хлеб',
					'неб',
					'ребр',
					'хреб',
					'лебед',
					'мудр',
					'гребл',
					'корабл',
					'persever',
					'persephon',
					'perseus',
					'turandot',
					'litsents',
					'vittori',
					'vittore',
					'raiska',
					'shitake',
					'shiitake',
				),
				'allow_phrases'         => array(
					'moby dick',
				),
			);
		}

		/**
		 * @return array<string, list<string>>
		 */
		public static function lists(): array {
			$lists = self::default_lists();
			if ( function_exists( 'apply_filters' ) ) {
				$filtered = apply_filters( 'choir_rehearsal_profanity_lists', $lists );
				if ( is_array( $filtered ) ) {
					$lists = self::normalize_lists( $filtered );
				}
			}
			return $lists;
		}

		/**
		 * @param array<string, mixed> $lists Partial or full lists.
		 * @return array<string, list<string>>
		 */
		public static function normalize_lists( array $lists ): array {
			$defaults = self::default_lists();
			foreach ( $defaults as $key => $value ) {
				if ( ! isset( $lists[ $key ] ) || ! is_array( $lists[ $key ] ) ) {
					$lists[ $key ] = $value;
				}
			}
			return $lists;
		}

		public static function contains( string $text, ?array $lists = null ): bool {
			$text = trim( $text );
			if ( '' === $text || self::is_only_youtube_url( $text ) ) {
				return false;
			}
			$lists = self::normalize_lists( null === $lists ? self::lists() : $lists );
			foreach ( array( 'lat_i', 'lat_l', 'lat_s', 'cyr_z', 'cyr_e' ) as $mode ) {
				$tokens = self::strip_phrases( self::tokens( self::prepare( $text, $mode ) ), $lists['allow_phrases'] );
				foreach ( $tokens as $token ) {
					$blocked = false;
					if ( str_starts_with( $mode, 'lat' ) ) {
						$blocked = self::english_token( $token, $lists ) || self::estonian_token( $token );
					} else {
						$blocked = self::russian_token( $token );
					}
					if ( ! $blocked ) {
						foreach ( $lists['block_substrings'] as $needle ) {
							if ( is_string( $needle ) && strlen( $needle ) >= 3 && str_contains( $token, $needle ) ) {
								$blocked = true;
								break;
							}
						}
					}
					if ( $blocked && ! self::is_allowed( $token, $lists ) ) {
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * @param array<string, string> $fields   Writable post fields.
		 * @param array<string, string> $previous Previous values for an update.
		 * @return array{fields: array<string, string>, errors: list<string>}
		 */
		public static function scrub_fields( array $fields, array $previous = array() ): array {
			$kinds  = array(
				'post_title'   => 'title',
				'post_content' => 'notes',
				'post_excerpt' => 'notes',
				'post_name'    => 'title',
			);
			$errors = array();
			foreach ( $kinds as $field => $kind ) {
				if ( ! isset( $fields[ $field ] ) || ! is_string( $fields[ $field ] ) ) {
					continue;
				}
				if ( self::is_only_youtube_url( $fields[ $field ] ) || ! self::contains( $fields[ $field ] ) ) {
					continue;
				}
				$errors[] = $kind;
				$prior    = $previous[ $field ] ?? '';
				if ( is_string( $prior ) && '' !== $prior && ! self::contains( $prior ) ) {
					$fields[ $field ] = $prior;
				} else {
					$fields[ $field ] = '';
				}
			}
			return array(
				'fields' => $fields,
				'errors' => array_values( array_unique( $errors ) ),
			);
		}

		public static function watches_post_type( string $type ): bool {
			$types = array( 'choir_song', 'choir_track', 'attachment' );
			if ( function_exists( 'apply_filters' ) ) {
				$filtered = apply_filters( 'choir_rehearsal_profanity_post_types', $types );
				if ( is_array( $filtered ) ) {
					$types = $filtered;
				}
			}
			return in_array( $type, $types, true );
		}

		public static function watches_taxonomy( string $taxonomy ): bool {
			$taxes = array( 'choir_voice_type' );
			if ( function_exists( 'apply_filters' ) ) {
				$filtered = apply_filters( 'choir_rehearsal_profanity_taxonomies', $taxes );
				if ( is_array( $filtered ) ) {
					$taxes = $filtered;
				}
			}
			return in_array( $taxonomy, $taxes, true );
		}

		public static function message( string $field, ?string $locale = null ): string {
			$messages = self::messages();
			$english  = $messages[ $field ] ?? self::MESSAGE_TITLE;
			if ( null === $locale ) {
				$locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : 'en_US';
			}
			return self::translate( $english, $locale );
		}

		/**
		 * @return array<string, string>
		 */
		public static function messages(): array {
			return array(
				'title'    => self::MESSAGE_TITLE,
				'notes'    => self::MESSAGE_NOTES,
				'part'     => self::MESSAGE_PART,
				'file'     => self::MESSAGE_FILE,
				'category' => self::MESSAGE_CATEGORY,
				'setting'  => self::MESSAGE_SETTING,
				'help'     => self::MESSAGE_SETTING_HELP,
			);
		}

		/**
		 * @return array<string, array<string, string>>
		 */
		public static function catalog(): array {
			return array(
				'et' => array(
					self::MESSAGE_TITLE    => 'Palun kasuta laulu pealkirjas sobivat keelt.',
					self::MESSAGE_NOTES    => 'Palun kasuta kirjelduses sobivat keelt.',
					self::MESSAGE_PART     => 'Palun kasuta partiinimes sobivat keelt.',
					self::MESSAGE_FILE     => 'Palun kasuta failinimes sobivat keelt.',
					self::MESSAGE_CATEGORY => 'Palun kasuta kategooria nimes sobivat keelt.',
					self::MESSAGE_SETTING      => 'Blokeeri roppused laulude pealkirjades, märkustes, partiinimedes ja failinimedes.',
					self::MESSAGE_SETTING_HELP => 'Kui see on sees, lükatakse need väljad tagasi kõigil peale kasutaja, kes saab saidi seadeid hallata. Demo-versioonis on see alguses sees. Demo must-use plugin hoiab selle ühiskontodel sees.',
				),
				'ru' => array(
					self::MESSAGE_TITLE    => 'Пожалуйста, используйте приемлемые выражения в названии песни.',
					self::MESSAGE_NOTES    => 'Пожалуйста, используйте приемлемые выражения в описании.',
					self::MESSAGE_PART     => 'Пожалуйста, используйте приемлемые выражения в названии партии.',
					self::MESSAGE_FILE     => 'Пожалуйста, используйте приемлемые выражения в имени файла.',
					self::MESSAGE_CATEGORY => 'Пожалуйста, используйте приемлемые выражения в названии категории.',
					self::MESSAGE_SETTING      => 'Блокировать ненормативную лексику в названиях песен, заметках, названиях партий и именах файлов.',
					self::MESSAGE_SETTING_HELP => 'Когда это включено, такие поля отклоняются у всех, кроме пользователя, который может управлять настройками сайта. В демо-сборке это включено с самого начала. Must-use плагин демо держит это включённым для общих учёток.',
				),
			);
		}

		public static function translate( string $text, string $locale ): string {
			$lang    = strtolower( substr( str_replace( '-', '_', $locale ), 0, 2 ) );
			$catalog = self::catalog();
			if ( isset( $catalog[ $lang ][ $text ] ) ) {
				return $catalog[ $lang ][ $text ];
			}
			return $text;
		}

		/**
		 * @param string $translation Existing translation.
		 * @param string $text        Original English text.
		 * @param string $domain      Text domain.
		 */
		public static function filter_gettext( string $translation, string $text, string $domain ): string {
			if ( self::TEXT_DOMAIN !== $domain ) {
				return $translation;
			}
			$locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : 'en_US';
			$custom = self::translate( $text, $locale );
			return $custom !== $text ? $custom : $translation;
		}

		/**
		 * @param array<string, mixed> $data    Post data.
		 * @param array<string, mixed> $postarr Raw post array.
		 * @return array<string, mixed>
		 */
		public static function filter_insert_post_data( array $data, array $postarr ): array {
			if ( ! self::should_block() ) {
				return $data;
			}
			$type = (string) ( $data['post_type'] ?? ( $postarr['post_type'] ?? '' ) );
			if ( ! self::watches_post_type( $type ) ) {
				return $data;
			}

			$previous = array();
			$id       = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
			if ( $id > 0 && function_exists( 'get_post' ) ) {
				$post = get_post( $id );
				if ( is_object( $post ) ) {
					foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name' ) as $field ) {
						$previous[ $field ] = isset( $post->$field ) ? (string) $post->$field : '';
					}
				}
			}

			$slice = array();
			foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_name' ) as $field ) {
				if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
					$slice[ $field ] = $data[ $field ];
				}
			}
			$result = self::scrub_fields( $slice, $previous );
			foreach ( $result['fields'] as $field => $value ) {
				$data[ $field ] = $value;
			}
			self::remember( $result['errors'] );
			return $data;
		}

		/**
		 * @param mixed $prepared Prepared REST post.
		 * @param mixed $request  Request, unused.
		 * @return mixed
		 */
		public static function filter_rest_pre_insert( $prepared, $request = null ) {
			unset( $request );
			if ( ! self::should_block() || ! is_object( $prepared ) ) {
				return $prepared;
			}
			$slice = array();
			foreach ( array( 'post_title', 'post_content', 'post_excerpt' ) as $field ) {
				if ( isset( $prepared->$field ) && is_string( $prepared->$field ) ) {
					$slice[ $field ] = $prepared->$field;
				}
			}
			$result = self::scrub_fields( $slice );
			if ( array() === $result['errors'] ) {
				return $prepared;
			}
			if ( class_exists( 'WP_Error' ) ) {
				return new WP_Error(
					'choir_rehearsal_profanity',
					self::message( $result['errors'][0] ),
					array( 'status' => 400 )
				);
			}
			return $prepared;
		}

		/**
		 * @param mixed $prepared Prepared term.
		 * @param mixed $request  Request, unused.
		 * @return mixed
		 */
		public static function filter_rest_pre_insert_term( $prepared, $request = null ) {
			unset( $request );
			if ( ! self::should_block() ) {
				return $prepared;
			}
			$name = '';
			if ( is_object( $prepared ) && isset( $prepared->name ) ) {
				$name = (string) $prepared->name;
			} elseif ( is_array( $prepared ) && isset( $prepared['name'] ) ) {
				$name = (string) $prepared['name'];
			}
			if ( '' !== $name && self::contains( $name ) && class_exists( 'WP_Error' ) ) {
				return new WP_Error( 'choir_rehearsal_profanity', self::message( 'part' ), array( 'status' => 400 ) );
			}
			return $prepared;
		}

		/**
		 * @param mixed  $term     Term name, or an existing error.
		 * @param string $taxonomy Taxonomy slug.
		 * @return mixed
		 */
		public static function filter_pre_insert_term( $term, $taxonomy ) {
			if ( ! self::should_block() || ! is_string( $term ) || ! self::watches_taxonomy( (string) $taxonomy ) ) {
				return $term;
			}
			if ( self::contains( $term ) && class_exists( 'WP_Error' ) ) {
				$kind = 'choir_voice_type' === (string) $taxonomy ? 'part' : 'category';
				return new WP_Error( 'choir_rehearsal_profanity', self::message( $kind ), array( 'status' => 400 ) );
			}
			return $term;
		}

		/**
		 * @param array<string, mixed> $file Upload file array.
		 * @return array<string, mixed>
		 */
		public static function filter_upload( array $file ): array {
			if ( ! self::should_block() ) {
				return $file;
			}
			$name = isset( $file['name'] ) ? (string) $file['name'] : '';
			if ( '' !== $name && self::contains( $name ) && empty( $file['error'] ) ) {
				$file['error'] = self::message( 'file' );
			}
			return $file;
		}

		public static function render_admin_notice(): void {
			if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'get_transient' ) || ! function_exists( 'delete_transient' ) ) {
				return;
			}
			$key      = self::TRANSIENT . get_current_user_id();
			$messages = get_transient( $key );
			if ( ! is_array( $messages ) || array() === $messages ) {
				return;
			}
			delete_transient( $key );
			echo '<div class="notice notice-error"><p>';
			$safe = function_exists( 'esc_html' ) ? esc_html( implode( ' ', $messages ) ) : htmlspecialchars( implode( ' ', $messages ), ENT_QUOTES, 'UTF-8' );
			echo $safe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			echo '</p></div>';
		}

		/**
		 * @param list<string> $kinds Field kinds that failed.
		 */
		private static function remember( array $kinds ): void {
			if ( array() === $kinds || ! function_exists( 'set_transient' ) || ! function_exists( 'get_current_user_id' ) ) {
				return;
			}
			$messages = array();
			foreach ( $kinds as $kind ) {
				$messages[] = self::message( $kind );
			}
			set_transient( self::TRANSIENT . get_current_user_id(), array_values( array_unique( $messages ) ), 60 );
		}

		private static function actor_is_admin(): bool {
			if ( null !== self::$test_actor ) {
				return self::$test_actor;
			}
			return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
		}

		private static function is_only_youtube_url( string $text ): bool {
			return (bool) preg_match( '#^https?://(?:www\.)?(?:youtube\.com|youtu\.be)/\S+$#i', trim( $text ) );
		}

		/**
		 * @param array<string, list<string>> $lists Normalized lists.
		 */
		/**
		 * @param array<string, list<string>> $lists Normalized lists.
		 */
		private static function is_allowed( string $token, array $lists ): bool {
			if ( in_array( $token, $lists['allow_tokens'], true ) ) {
				return true;
			}
			foreach ( $lists['allow_stems'] as $stem ) {
				if ( ! is_string( $stem ) || '' === $stem || ! str_starts_with( $token, $stem ) ) {
					continue;
				}
				$rest = self::substr( $token, self::length( $stem ), max( 0, self::length( $token ) - self::length( $stem ) ) );
				if ( self::remainder_is_swear( $rest ) ) {
					continue;
				}
				return true;
			}
			return false;
		}

		/**
		 * A stem must not hide a second swear glued onto an innocent word.
		 */
		private static function remainder_is_swear( string $rest ): bool {
			if ( '' === $rest ) {
				return false;
			}
			return (bool) preg_match( '/еб|пизд|ху[еийоюя]|бля|гандон|залуп|fuck|shit|cunt|bitch/u', $rest );
		}

		/**
		 * @param list<string> $tokens  Tokens.
		 * @param list<string> $phrases Allowed phrases.
		 * @return list<string>
		 */
		private static function strip_phrases( array $tokens, array $phrases ): array {
			if ( array() === $tokens || array() === $phrases ) {
				return $tokens;
			}
			$text = ' ' . implode( ' ', $tokens ) . ' ';
			foreach ( $phrases as $phrase ) {
				if ( ! is_string( $phrase ) ) {
					continue;
				}
				$phrase = trim( self::lower( $phrase ) );
				if ( '' === $phrase ) {
					continue;
				}
				$text = str_replace( ' ' . $phrase . ' ', ' ', $text );
			}
			$out = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
			return is_array( $out ) ? array_values( $out ) : array();
		}

		/**
		 * @param array<string, list<string>> $lists Normalized lists.
		 */
		private static function english_token( string $token, array $lists ): bool {
			if ( in_array( $token, $lists['block_tokens'], true ) ) {
				return true;
			}
			foreach ( $lists['block_token_patterns'] as $pattern ) {
				if ( is_string( $pattern ) && '' !== $pattern && preg_match( $pattern, $token ) ) {
					return true;
				}
			}
			return false;
		}

		private static function estonian_token( string $token ): bool {
			if ( preg_match( '/^pers+e/u', $token ) ) {
				return true;
			}
			if ( preg_match( '/^(?:tu|ty)ra/u', $token ) ) {
				return true;
			}
			if ( preg_match( '/^munn/u', $token ) ) {
				return true;
			}
			if ( preg_match( '/^vitt/u', $token ) ) {
				return true;
			}
			if ( preg_match( '/^lits(?!ents)/u', $token ) ) {
				return true;
			}
			if ( preg_match( '/^raisk(?!a)/u', $token ) ) {
				return true;
			}
			if ( preg_match( '/^pede(?:d|de|kas|st|le|ga|ks|ni)?$/u', $token ) ) {
				return true;
			}
			return false;
		}

		private static function russian_token( string $token ): bool {
			if ( preg_match( '/ху[еийоюя]/u', $token ) ) {
				return true;
			}
			if ( str_contains( $token, 'пизд' ) ) {
				return true;
			}
			if ( preg_match( '/^бля/u', $token ) ) {
				return true;
			}
			if ( str_contains( $token, 'гандон' ) || str_contains( $token, 'гондон' ) || str_contains( $token, 'залуп' ) ) {
				return true;
			}
			if ( preg_match( '/муд(?!р)/u', $token ) ) {
				return true;
			}
			if ( preg_match( '/сук[аеиоуы]/u', $token ) ) {
				return true;
			}
			if ( preg_match( '/суч[каиье]/u', $token ) ) {
				return true;
			}
			return self::has_russian_eb( $token );
		}

		private static function has_russian_eb( string $token ): bool {
			if ( preg_match_all( '/еб/u', $token ) >= 2 ) {
				return true;
			}
			if ( preg_match( '/^еб/u', $token ) ) {
				return true;
			}
			$prefixes = array( 'долбо', 'пере', 'подъ', 'разъ', 'отъ', 'объ', 'въ', 'съ', 'под', 'раз', 'про', 'при', 'вз', 'вы', 'за', 'на', 'по', 'до', 'об', 'от', 'из', 'у' );
			$offset   = 0;
			$length   = self::length( $token );
			while ( $offset < $length ) {
				$pos = self::strpos( $token, 'еб', $offset );
				if ( false === $pos || 0 === $pos ) {
					break;
				}
				$before = self::substr( $token, 0, $pos );
				foreach ( $prefixes as $prefix ) {
					if ( str_ends_with( $before, $prefix ) ) {
						return true;
					}
				}
				$offset = $pos + 1;
			}
			return false;
		}

		private static function prepare( string $text, string $mode ): string {
			$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$text = strip_tags( $text );
			$text = str_replace( "\xc2\xa0", ' ', $text );
			$text = preg_replace( '/[\x{00AD}\x{200B}-\x{200D}\x{FEFF}]/u', '', $text ) ?? $text;
			$text = self::lower( $text );
			$text = strtr(
				$text,
				array(
					'ё' => 'е',
					'ë' => 'e',
					'à' => 'a',
					'á' => 'a',
					'â' => 'a',
					'ã' => 'a',
					'ä' => 'a',
					'å' => 'a',
					'è' => 'e',
					'é' => 'e',
					'ê' => 'e',
					'ì' => 'i',
					'í' => 'i',
					'î' => 'i',
					'ï' => 'i',
					'ò' => 'o',
					'ó' => 'o',
					'ô' => 'o',
					'õ' => 'o',
					'ö' => 'o',
					'ù' => 'u',
					'ú' => 'u',
					'û' => 'u',
					'ü' => 'u',
					'ý' => 'y',
					'ÿ' => 'y',
					'ñ' => 'n',
					'ç' => 'c',
					'š' => 's',
					'ž' => 'z',
				)
			);
			$text = self::leet( $text, $mode );
			$text = self::lookalikes( $text, $mode );
			$text = self::strip_extension( $text );
			$text = self::strip_separators( $text );
			return self::collapse( $text );
		}

		private static function leet( string $text, string $mode ): string {
			$cyr = str_starts_with( $mode, 'cyr' );
			$map = array(
				'@' => $cyr ? 'а' : 'a',
				'$' => $cyr ? 'с' : 's',
				'0' => $cyr ? 'о' : 'o',
				'4' => $cyr ? 'а' : 'a',
				'5' => $cyr ? 'с' : 's',
				'7' => $cyr ? 'т' : 't',
				'!' => $cyr ? 'и' : 'i',
			);
			if ( 'lat_l' === $mode ) {
				$map['1'] = 'l';
				$map['3'] = 'e';
			} elseif ( $cyr ) {
				$map['1'] = 'и';
				$map['3'] = 'cyr_e' === $mode ? 'е' : 'з';
			} else {
				$map['1'] = 'i';
				$map['3'] = 'e';
			}
			return strtr( $text, $map );
		}

		private static function lookalikes( string $text, string $mode ): string {
			if ( str_starts_with( $mode, 'cyr' ) ) {
				return strtr( $text, self::LAT_TO_CYR );
			}
			if ( 'lat_s' === $mode ) {
				return strtr( $text, self::CYR_TO_LAT_S );
			}
			return strtr( $text, self::CYR_TO_LAT_C );
		}

		private static function strip_extension( string $text ): string {
			return preg_replace( '/\.(?:mp3|wav|ogg|m4a|aac|flac|webm|mp4|pdf|jpe?g|png|gif|webp|txt|zip)$/iu', '', $text ) ?? $text;
		}

		private static function strip_separators( string $text ): string {
			$guard = 0;
			$prev  = '';
			while ( $prev !== $text && $guard < 12 ) {
				$prev  = $text;
				$text  = preg_replace( '/(\p{L})[\.\*_\-\'"+\\\\\/|#~`^=]+(\p{L})/u', '$1$2', $text ) ?? $text;
				$guard++;
			}
			return $text;
		}

		private static function collapse( string $text ): string {
			$text = preg_replace( '/(\p{L})\1{2,}/u', '$1', $text ) ?? $text;
			$text = preg_replace( '/([aeiouyаеиоуыэюя])\1+/u', '$1', $text ) ?? $text;
			return $text;
		}

		/**
		 * @return list<string>
		 */
		private static function tokens( string $prepared ): array {
			$parts = preg_split( '/[^\p{L}]+/u', $prepared, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! is_array( $parts ) ) {
				return array();
			}
			$tokens = array_values( $parts );
			$extra  = array();
			$run    = '';
			$count  = 0;
			foreach ( $tokens as $token ) {
				if ( 1 === self::length( $token ) ) {
					$run .= $token;
					$count++;
					continue;
				}
				if ( $count >= 3 ) {
					$extra[] = self::collapse( $run );
				}
				$run   = '';
				$count = 0;
			}
			if ( $count >= 3 ) {
				$extra[] = self::collapse( $run );
			}
			return array_merge( $tokens, $extra );
		}

		private static function lower( string $text ): string {
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		}

		private static function length( string $text ): int {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		}

		private static function strpos( string $haystack, string $needle, int $offset ): int|false {
			return function_exists( 'mb_strpos' ) ? mb_strpos( $haystack, $needle, $offset, 'UTF-8' ) : strpos( $haystack, $needle, $offset );
		}

		private static function substr( string $text, int $start, int $length ): string {
			return function_exists( 'mb_substr' ) ? mb_substr( $text, $start, $length, 'UTF-8' ) : substr( $text, $start, $length );
		}
	}

endif;
