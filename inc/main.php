<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * XO Event Calendar main.
 *
 * @package xo-event-calendar
 */

/**
 * XO Event Calendar main class.
 */
class XO_Event_Calendar {
	/**
	 * Options.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $options;

	/**
	 * XO Event Calendar admin.
	 *
	 * @since 1.0.0
	 * @var XO_Event_Calendar_Admin
	 */
	public $admin;

	/**
	 * Stores strings for weekday names.
	 *
	 * @since 3.0.2
	 * @var string[]
	 */
	public $weekday_initial;

	/**
	 * Construction.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		load_plugin_textdomain( 'xo-event-calendar', false, 'xo-event-calendar/languages' );

		$this->options = get_option(
			'xo_event_calendar_options',
			array(
				'disable_dashicons'  => true,
				'disable_event_link' => false,
				'delete_data'        => false,
			)
		);

		if ( class_exists( 'XO_Event_Calendar_Admin' ) ) {
			$this->admin = new XO_Event_Calendar_Admin( $this );
		}

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Plugins loaded process.
	 *
	 * @since 1.0.0
	 */
	public function plugins_loaded() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_xo_event_calendar_month', array( $this, 'ajax_event_calendar' ) );
		add_action( 'wp_ajax_nopriv_xo_event_calendar_month', array( $this, 'ajax_event_calendar' ) );
		add_action( 'wp_ajax_xo_simple_calendar_month', array( $this, 'ajax_simple_calendar' ) );
		add_action( 'wp_ajax_nopriv_xo_simple_calendar_month', array( $this, 'ajax_simple_calendar' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_filter( 'template_include', array( $this, 'template_include' ) );

		add_action( 'admin_init', array( $this, 'register_setting' ) );

		if ( function_exists( 'register_block_type' ) ) {
			add_action( 'init', array( $this, 'register_block_type' ) );
			add_action( 'rest_api_init', array( $this, 'register_setting' ) );
		}

		add_shortcode( 'xo_event_calendar', array( $this, 'event_calendar_shortcode' ) );
		add_shortcode( 'xo_event_field', array( $this, 'event_field_shortcode' ) );
	}

	/**
	 * Retrieves the post type of the event post
	 *
	 * @since 1.0.0
	 *
	 * @return string Returns the post type of the event post.
	 */
	public static function get_post_type() {
		return XO_EVENT_CALENDAR_EVENT_POST_TYPE;
	}

	/**
	 * Retrieves the taxonomy of the event category.
	 *
	 * @since 1.0.0
	 *
	 * @return string Returns the taxonomy of the event category.
	 */
	public static function get_taxonomy_type() {
		return XO_EVENT_CALENDAR_EVENT_TAXONOMY;
	}

	/**
	 * Plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivation() {
		$uninstall = array(
			self::get_post_type(),
			self::get_taxonomy_type(),
		);
		set_site_transient( 'xo_event_calendar_uninstall_options', $uninstall, MINUTE_IN_SECONDS );
	}

	/**
	 * Plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $network_wide Whether to enable the plugin for all sites in the network. Default false.
	 */
	public function activation( $network_wide ) {
		global $wpdb;

		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				$this->activation_site();
			}
			restore_current_blog();
		} else {
			$this->activation_site();
		}
	}

	/**
	 * Activation the site.
	 *
	 * @since 1.0.0
	 */
	private function activation_site() {
		$holiday_settings = get_option( 'xo_event_calendar_holiday_settings' );
		if ( false === $holiday_settings ) {
			$holiday_settings        = array();
			$holiday_settings['all'] = array(
				'title'           => __( 'Regular holiday', 'xo-event-calendar' ),
				'dayofweek'       => array(
					'sun' => true,
					'mon' => false,
					'tue' => false,
					'wed' => false,
					'thu' => false,
					'fri' => false,
					'sat' => true,
				),
				'special_holiday' => null,
				'non_holiday'     => null,
				'color'           => '#fddde6',
			);
			$holiday_settings['am']  = array(
				'title'           => __( 'Morning Off', 'xo-event-calendar' ),
				'dayofweek'       => array(
					'sun' => false,
					'mon' => false,
					'tue' => false,
					'wed' => false,
					'thu' => false,
					'fri' => false,
					'sat' => false,
				),
				'special_holiday' => null,
				'non_holiday'     => null,
				'color'           => '#dbf6cc',
			);
			$holiday_settings['pm']  = array(
				'title'           => __( 'Afternoon Off', 'xo-event-calendar' ),
				'dayofweek'       => array(
					'sun' => false,
					'mon' => false,
					'tue' => false,
					'wed' => false,
					'thu' => false,
					'fri' => false,
					'sat' => false,
				),
				'special_holiday' => null,
				'non_holiday'     => null,
				'color'           => '#def0fc',
			);

			update_option( 'xo_event_calendar_holiday_settings', $holiday_settings );
		}

		$this->register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Enqueues scripts for this search page.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! isset( $this->options['disable_dashicons'] ) || ! $this->options['disable_dashicons'] ) {
			wp_enqueue_style( 'dashicons' );
		}

		wp_enqueue_style( 'xo-event-calendar', XO_EVENT_CALENDAR_URL . 'css/xo-event-calendar.css', array(), XO_EVENT_CALENDAR_VERSION );

		if ( ! function_exists( 'wp_should_load_separate_core_block_assets' ) || wp_should_load_separate_core_block_assets() ) {
			wp_enqueue_style( 'xo-event-calendar-event-calendar', XO_EVENT_CALENDAR_URL . 'build/event-calendar/style-index.css', array(), XO_EVENT_CALENDAR_VERSION );
		}

		wp_enqueue_script( 'xo-event-calendar-ajax', XO_EVENT_CALENDAR_URL . 'js/ajax.js', array(), XO_EVENT_CALENDAR_VERSION, true );
		wp_localize_script(
			'xo-event-calendar-ajax',
			'xo_event_calendar_object',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'action'   => 'xo_event_calendar_month',
			)
		);
		wp_localize_script(
			'xo-event-calendar-ajax',
			'xo_simple_calendar_object',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'action'   => 'xo_simple_calendar_month',
			)
		);
	}

	/**
	 * Escape a color for use in css.
	 *
	 * @since 3.0.4
	 *
	 * @param string $color RGB color.
	 * @return string
	 */
	private function esc_color( $color ) {
		$default = 'transparent';
		$color   = trim( $color );

		if ( 0 === strpos( $color, '#' ) ) {
			$color = substr( $color, 1 );
		}
		if ( 0 === strpos( $color, '%23' ) ) {
			$color = substr( $color, 3 );
		}
		if ( ! ctype_xdigit( $color ) ) {
			return $default;
		}
		if ( ! in_array( strlen( $color ), array( 3, 6 ), true ) ) {
			return $default;
		}
		return '#' . strtolower( $color );
	}

	/**
	 * Retrieve the date in localized format, based on timestamp.
	 *
	 * @since 1.7.1
	 *
	 * @param string   $dateformatstring Format to display the date.
	 * @param bool|int $unixtimestamp    Optional. Unix timestamp. Default false.
	 * @param bool     $gmt              Optional. Whether to use GMT timezone. Default false.
	 *
	 * @return string The date, translated if locale specifies it.
	 */
	private function date( $dateformatstring, $unixtimestamp = false, $gmt = false ) {
		return date_i18n( $dateformatstring, $unixtimestamp, $gmt );
	}

	/**
	 * Retrieves weekday.
	 *
	 * @since 3.0.0
	 *
	 * @param int $weekday_number 0 for Sunday through 6 Saturday.
	 * @return string Weekday.
	 */
	private function get_weekday( $weekday_number ) {
		$weeks = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		return $weeks[ $weekday_number ];
	}

	/**
	 * Retrieves weekday initial.
	 *
	 * @since 3.0.0
	 *
	 * @param int $weekday_number 0 for Sunday through 6 Saturday.
	 * @return string Weekday initial.
	 */
	private function get_weekday_initial( $weekday_number ) {
		$weeks = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );
		return $weeks[ $weekday_number ];
	}

	/**
	 * Retrieves localized weekday initial.
	 *
	 * @since 3.0.1
	 *
	 * @param int $weekday_number 0 for Sunday through 6 Saturday.
	 * @return string Weekday initial.
	 */
	private function get_locale_weekday_initial( $weekday_number ) {
		if ( ! isset( $this->weekday_initial ) ) {
			$this->weekday_initial = array(
				_x( 'S', 'Sunday initial', 'xo-event-calendar' ),
				_x( 'M', 'Monday initial', 'xo-event-calendar' ),
				_x( 'T', 'Tuesday initial', 'xo-event-calendar' ),
				_x( 'W', 'Wednesday initial', 'xo-event-calendar' ),
				_x( 'T', 'Thursday initial', 'xo-event-calendar' ),
				_x( 'F', 'Friday initial', 'xo-event-calendar' ),
				_x( 'S', 'Saturday initial', 'xo-event-calendar' ),
			);
		}
		return $this->weekday_initial[ $weekday_number ];
	}

	/**
	 * Ajax handler for updating the event calendar.
	 *
	 * @since 3.0.0
	 */
	public function ajax_event_calendar() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- we do not need to verify the nonce for this public request for publicly accessible data.
		$id            = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : '';
		$month         = isset( $_REQUEST['month'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['month'] ) ) : '';
		$show_event    = isset( $_REQUEST['event'] ) ? (bool) $_REQUEST['event'] : false;
		$categories    = isset( $_REQUEST['categories'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['categories'] ) ) : '';
		$holidays      = isset( $_REQUEST['holidays'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['holidays'] ) ) : '';
		$prev          = isset( $_REQUEST['prev'] ) ? intval( $_REQUEST['prev'] ) : -1;
		$next          = isset( $_REQUEST['next'] ) ? intval( $_REQUEST['next'] ) : -1;
		$start_of_week = isset( $_REQUEST['start_of_week'] ) ? intval( $_REQUEST['start_of_week'] ) : 0;
		$months        = isset( $_REQUEST['months'] ) ? intval( $_REQUEST['months'] ) : 1;
		$navigation    = isset( $_REQUEST['navigation'] ) ? (bool) $_REQUEST['navigation'] : false;
		$mhc           = isset( $_REQUEST['mhc'] ) ? (bool) $_REQUEST['mhc'] : false;
		$base_month    = isset( $_REQUEST['base_month'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['base_month'] ) ) : '';
		$title_format  = isset( $_REQUEST['title_format'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['title_format'] ) ) : '';
		$locale        = isset( $_REQUEST['is_locale'] ) ? (bool) $_REQUEST['is_locale'] : true;
		$columns       = isset( $_REQUEST['columns'] ) ? intval( $_REQUEST['columns'] ) : 1;
		// phpcs:enable

		$allowed_html = wp_kses_allowed_html( 'post' );

		$allowed_html['button']['onclick'] = true;

		preg_match( '/^([0-9]{4})-([0-9]{1,2})/', $month, $m1 );
		if ( 3 === count( $m1 ) ) {
			$y = $m1[1];
			$m = $m1[2];

			preg_match( '/^([0-9]{4})-([0-9]{1,2})/', $base_month, $m2 );
			if ( count( $m2 ) === 3 ) {
				$base_y = $m2[1];
				$base_m = $m2[2];
			} else {
				$base_y = $y;
				$base_m = $m;
			}

			for ( $i = 1; $i <= $months; $i++ ) {
				echo wp_kses(
					$this->get_event_calendar_month(
						array(
							'id'                => $id,
							'year'              => $y,
							'month'             => $m,
							'show_event'        => $show_event,
							'categories_string' => $categories,
							'holidays_string'   => $holidays,
							'prev_month_feed'   => $prev,
							'next_month_feed'   => $next,
							'start_of_week'     => $start_of_week,
							'months'            => $months,
							'navigation'        => $navigation,
							'base_year'         => $base_y,
							'base_month'        => $base_m,
							'title_format'      => $title_format,
							'locale'            => $locale,
							'columns'           => $columns,
						),
						$i
					),
					$allowed_html
				);

				$next_time = strtotime( '+1 month', strtotime( "{$y}-{$m}-1" ) );

				$y = gmdate( 'Y', $next_time );
				$m = gmdate( 'n', $next_time );
			}
		}

		die();
	}

	/**
	 * Ajax handler for updating the simple calendar.
	 *
	 * @since 3.0.0
	 */
	public function ajax_simple_calendar() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- we do not need to verify the nonce for this public request for publicly accessible data.
		$id              = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : '';
		$month           = isset( $_REQUEST['month'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['month'] ) ) : '';
		$holidays        = isset( $_REQUEST['holidays'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['holidays'] ) ) : '';
		$prev            = isset( $_REQUEST['prev'] ) ? intval( $_REQUEST['prev'] ) : -1;
		$next            = isset( $_REQUEST['next'] ) ? intval( $_REQUEST['next'] ) : -1;
		$start_of_week   = isset( $_REQUEST['start_of_week'] ) ? intval( $_REQUEST['start_of_week'] ) : 0;
		$months          = isset( $_REQUEST['months'] ) ? intval( $_REQUEST['months'] ) : 1;
		$navigation      = isset( $_REQUEST['navigation'] ) ? (bool) $_REQUEST['navigation'] : false;
		$base_month      = isset( $_REQUEST['base_month'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['base_month'] ) ) : '';
		$title_format    = isset( $_REQUEST['title_format'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['title_format'] ) ) : '';
		$locale          = isset( $_REQUEST['is_locale'] ) ? (bool) $_REQUEST['is_locale'] : true;
		$caption_color   = isset( $_REQUEST['caption_color'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['caption_color'] ) ) : '';
		$caption_bgcolor = isset( $_REQUEST['caption_bgcolor'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['caption_bgcolor'] ) ) : '';
		$columns         = isset( $_REQUEST['columns'] ) ? intval( $_REQUEST['columns'] ) : 1;
		// phpcs:enable

		$allowed_html = wp_kses_allowed_html( 'post' );

		$allowed_html['button']['onclick'] = true;

		preg_match( '/^([0-9]{4})-([0-9]{1,2})/', $month, $m1 );
		if ( count( $m1 ) === 3 ) {
			$y = $m1[1];
			$m = $m1[2];

			preg_match( '/^([0-9]{4})-([0-9]{1,2})/', $base_month, $m2 );
			if ( count( $m2 ) === 3 ) {
				$base_y = $m2[1];
				$base_m = $m2[2];
			} else {
				$base_y = $y;
				$base_m = $m;
			}

			for ( $i = 1; $i <= $months; $i++ ) {
				echo wp_kses(
					$this->get_simple_calendar_month(
						array(
							'id'              => $id,
							'year'            => $y,
							'month'           => $m,
							'holidays_string' => $holidays,
							'prev_month_feed' => $prev,
							'next_month_feed' => $next,
							'start_of_week'   => $start_of_week,
							'months'          => $months,
							'navigation'      => $navigation,
							'base_year'       => $base_y,
							'base_month'      => $base_m,
							'title_format'    => $title_format,
							'locale'          => $locale,
							'columns'         => $columns,
							'caption_color'   => $caption_color,
							'caption_bgcolor' => $caption_bgcolor,
						),
						$i
					),
					$allowed_html
				);

				$next_time = strtotime( '+1 month', strtotime( "{$y}-{$m}-1" ) );

				$y = gmdate( 'Y', $next_time );
				$m = gmdate( 'n', $next_time );
			}
		}

		die();
	}

	/**
	 * Registers the post type and the taxonomy type.
	 *
	 * @since 1.0.0
	 */
	public function register_post_type() {
		$post_type = $this->get_post_type();

		$args = array(
			'labels'        => array(
				'name'               => _x( 'Events', 'post type general name', 'xo-event-calendar' ),
				'singular_name'      => _x( 'Event', 'post type singular name', 'xo-event-calendar' ),
				'menu_name'          => _x( 'Events', 'admin menu', 'xo-event-calendar' ),
				'name_admin_bar'     => _x( 'Event', 'add new on admin bar', 'xo-event-calendar' ),
				'add_new'            => __( 'Add New Event', 'xo-event-calendar' ),
				'add_new_item'       => __( 'Add New Event', 'xo-event-calendar' ),
				'new_item'           => __( 'New Event', 'xo-event-calendar' ),
				'edit_item'          => __( 'Edit Event', 'xo-event-calendar' ),
				'view_item'          => __( 'View Event', 'xo-event-calendar' ),
				'all_items'          => __( 'All Events', 'xo-event-calendar' ),
				'search_items'       => __( 'Search Events', 'xo-event-calendar' ),
				'parent_item_colon'  => __( 'Parent Events:', 'xo-event-calendar' ),
				'not_found'          => __( 'No Events found.', 'xo-event-calendar' ),
				'not_found_in_trash' => __( 'No Events found in Trash.', 'xo-event-calendar' ),
			),
			'public'        => true,
			'menu_position' => 4,
			'menu_icon'     => 'dashicons-calendar-alt',
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'has_archive'   => true,
			'show_in_rest'  => true,
		);

		$args = apply_filters( 'xo_event_calendar_register_post_args', $args );

		register_post_type( $post_type, $args );

		$taxonomy_type = $this->get_taxonomy_type();

		$args = array(
			'labels'       => array(
				'name'              => _x( 'Categories', 'taxonomy general name', 'xo-event-calendar' ),
				'singular_name'     => _x( 'Category', 'taxonomy singular name', 'xo-event-calendar' ),
				'search_items'      => __( 'Search Categories', 'xo-event-calendar' ),
				'all_items'         => __( 'All Categories', 'xo-event-calendar' ),
				'parent_item'       => __( 'Parent Category', 'xo-event-calendar' ),
				'parent_item_colon' => __( 'Parent Category:', 'xo-event-calendar' ),
				'edit_item'         => __( 'Edit Category', 'xo-event-calendar' ),
				'update_item'       => __( 'Update Category', 'xo-event-calendar' ),
				'add_new_item'      => __( 'Add New Category', 'xo-event-calendar' ),
				'new_item_name'     => __( 'New Category Name', 'xo-event-calendar' ),
				'menu_name'         => __( 'Category', 'xo-event-calendar' ),
			),
			'hierarchical' => true,
			'show_ui'      => true,
			'query_var'    => true,
			'rewrite'      => array( 'slug' => $taxonomy_type ),
			'show_in_rest' => true,
		);

		$args = apply_filters( 'xo_event_calendar_register_taxonomy_args', $args );

		register_taxonomy( $taxonomy_type, $post_type, $args );
	}

	/**
	 * Get event posts.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $start_date Event start datetime.
	 * @param int   $end_date   Event End datetime.
	 * @param array $terms      Event categories.
	 * @return array Events array.
	 */
	private function get_events( $start_date, $end_date, $terms = null ) {
		$post_type     = $this->get_post_type();
		$taxonomy_type = $this->get_taxonomy_type();

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'orderby'        => array(
				'event_start_date'   => 'ASC',
				'event_end_date'     => 'DESC',
				'event_start_hour'   => 'ASC',
				'event_start_minute' => 'ASC',
			),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'event_start_date'   => array(
					'key'     => 'event_start_date',
					'value'   => gmdate( 'Y-m-d', $end_date ),
					'compare' => '<=',
					'type'    => 'DATE',
				),
				'event_end_date'     => array(
					'key'     => 'event_end_date',
					'value'   => gmdate( 'Y-m-d', $start_date ),
					'compare' => '>=',
					'type'    => 'DATE',
				),
				'event_start_hour'   => array(
					'key'  => 'event_start_hour',
					'type' => 'NUMERIC',
				),
				'event_start_minute' => array(
					'key'  => 'event_start_minute',
					'type' => 'NUMERIC',
				),
			),
			'posts_per_page' => -1,
		);

		if ( ! empty( $terms ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => $taxonomy_type,
					'field'    => 'slug',
					'terms'    => $terms,
				),
			);
		}

		$query = new WP_Query( $args );

		$events = array();
		while ( $query->have_posts() ) {
			global $post;
			$query->the_post();

			// Get category color.
			$bg_color = '#ccc';
			$category = '';
			$terms    = get_the_terms( $post->ID, $taxonomy_type );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $cate ) {
					$cat_data = get_option( 'xo_event_calendar_cat_' . intval( $cate->term_id ) );
					if ( $cat_data && $cat_data['category_color'] ) {
						$bg_color = $cat_data['category_color'];
						$category = $cate->slug;
						break;
					}
				}
			}

			$events[] = array(
				'post'        => $post,
				'title'       => get_the_title(),
				'start_date'  => get_post_meta( $post->ID, 'event_start_date', true ),
				'end_date'    => get_post_meta( $post->ID, 'event_end_date', true ),
				'bg_color'    => $bg_color,
				'permalink'   => get_permalink( $post->ID ),
				'short_title' => get_post_meta( $post->ID, 'short_title', true ),
				'category'    => $category,
			);
		}
		wp_reset_postdata();

		return $events;
	}

	/**
	 * Get holiday slug.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $holidays         Holidays slug array.
	 * @param array  $holiday_settings Holiday settings.
	 * @param string $date             Date.
	 * @return string Holiday slug.
	 */
	private function get_holiday_slug( $holidays, $holiday_settings, $date ) {
		$weeks = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );
		$slugs = array();

		foreach ( $holidays as $holiday ) {
			if ( array_key_exists( $holiday, $holiday_settings ) ) {
				if ( ( $holiday_settings[ $holiday ]['dayofweek'][ $weeks[ gmdate( 'w', $date ) ] ] ||
					strpos( (string) $holiday_settings[ $holiday ]['special_holiday'], gmdate( 'Y-m-d', $date ) ) !== false ) &&
					strpos( (string) $holiday_settings[ $holiday ]['non_holiday'], gmdate( 'Y-m-d', $date ) ) === false
				) {
					$slugs[] = $holiday;
				}
			}
		}

		return $slugs;
	}

	/**
	 * Renders the month of the event calendar.
	 *
	 * @param array $args An array of arguments used to retrieve monthly calendar.
	 * @param int   $month_index Calendar number.
	 * @return string HTML.
	 */
	private function get_event_calendar_month( $args, $month_index = 1 ) {
		global $wp_locale;

		if ( $args['month'] < 1 ) {
			$args['month'] = 1;
		} elseif ( $args['month'] > 12 ) {
			$args['month'] = 12;
		}
		if ( ! isset( $args['base_year'] ) ) {
			$args['base_year'] = $args['year'];
		}
		if ( ! isset( $args['base_month'] ) ) {
			$args['base_month'] = $args['month'];
		}
		$base_month_time = strtotime( "{$args['base_year']}-{$args['base_month']}-1" );
		$base_month      = gmdate( 'Y-n', $base_month_time );

		// 月末の取得.
		$last_day = gmdate( 'j', mktime( 0, 0, 0, $args['month'] + 1, 0, $args['year'] ) );

		// 曜日の取得（0:日 ～ 6:土）.
		$week = gmdate( 'w', mktime( 0, 0, 0, $args['month'], 1, $args['year'] ) );

		// 週数を取得.
		$week_count = ceil( ( ( ( 7 + $week - $args['start_of_week'] ) % 7 ) + $last_day ) / 7 );

		// 日数を取得.
		$day_count = $week_count * 7;

		// 開始日を取得.
		$start_time = strtotime( "{$args['year']}-{$args['month']}-1" );
		if ( (int) $args['start_of_week'] !== $week ) {
			$d          = ( 7 + $week - $args['start_of_week'] ) % 7;
			$start_time = strtotime( "-{$d} day", $start_time );
		}

		// 終了日を取得.
		$end_time = strtotime( "+{$day_count} day", $start_time );

		if ( $args['show_event'] ) {
			if ( empty( $args['categories_string'] ) ) {
				$events = $this->get_events( $start_time, $end_time );
			} else {
				$categories = explode( ',', $args['categories_string'] );
				$events     = $this->get_events( $start_time, $end_time, $categories );
			}

			/**
			 * Filters Event calendar events' data.
			 *
			 * @since 2.2.1
			 *
			 * @param array An array of events' data.
			 * @param array $args An array of arguments used to retrieve monthly calendar.
			 * @param int $month_index Calendar number.
			 */
			$events = apply_filters( 'xo_event_calendar_events', $events, $args, $month_index );

			$args['show_event'] = 1;
		} else {
			$events = array();

			$args['show_event'] = 0;
		}

		$today = $this->date( 'Y-m-d' );

		$holidays         = explode( ',', (string) $args['holidays_string'] );
		$holiday_settings = get_option( 'xo_event_calendar_holiday_settings' );

		$month_html = '';
		$line_count = 0;
		for ( $week_index = 0; $week_index < $week_count; $week_index++ ) {
			$month_html .= '<tr><td colspan="7" class="month-week">';

			$month_html .= '<table class="month-dayname">';
			$month_html .= '<tbody>';
			$month_html .= '<tr class="dayname">';
			for ( $day_index = 0; $day_index < 7; $day_index++ ) {
				$d     = $week_index * 7 + $day_index;
				$date  = strtotime( "+{$d} days", $start_time );
				$class = '';
				if ( (int) gmdate( 'n', $date ) !== (int) $args['month'] ) {
					$class .= 'other-month ';
				}
				if ( gmdate( 'Y-m-d', $date ) === $today ) {
					$class .= 'today ';
				}
				$style = '';
				if ( $holiday_settings ) {
					$holiday_slugs = $this->get_holiday_slug( $holidays, $holiday_settings, $date );
					if ( count( $holiday_slugs ) ) {
						$holiday_slug = end( $holiday_slugs );
						$style        = 'style="background-color: ' . $holiday_settings[ $holiday_slug ]['color'] . ';"';
						$class       .= implode(
							' ',
							array_map(
								function ( $s ) {
									return "holiday-{$s}";
								},
								$holiday_slugs
							)
						);
					}
				}
				if ( ! empty( $class ) ) {
					$class = 'class="' . trim( $class ) . '" ';
				}
				$month_html .= "<td><div {$class}{$style}>" . gmdate( 'j', $date ) . '</div></td>';
			}
			$month_html .= '</tr>';
			$month_html .= '</tbody>';
			$month_html .= '</table>';

			$month_html .= '<div class="month-dayname-space"></div>';

			if ( count( $events ) > 0 ) {
				$days_week = array_fill( 0, 7, array() );

				for ( $day_index = 0; $day_index < 7; $day_index++ ) {
					$d   = $week_index * 7 + $day_index;
					$day = gmdate( 'Y-m-d', strtotime( "+{$d} day", $start_time ) );

					$events_count = count( $events );
					for ( $i = 0; $i < $events_count; $i++ ) {
						$event = $events[ $i ];
						if ( empty( $event['end_date'] ) ) {
							if ( $event['start_date'] === $day ) {
								$days_week[ $day_index ][] = $i;
							}
						} elseif ( $event['start_date'] <= $day && $day <= $event['end_date'] ) {
							$days_week[ $day_index ][] = $i;
						}
					}
				}

				for ( $day_index = 0; $day_index < 7; $day_index++ ) {
					$day_index_count = count( $days_week[ $day_index ] );
					for ( $line_index = 0; $line_index < $day_index_count; $line_index++ ) {
						if ( isset( $days_week[ $day_index ][ $line_index ] ) && null !== $days_week[ $day_index ][ $line_index ] ) {
							$max_line = -1;
							for ( $i = 0; $i < 7; $i++ ) {
								$days_week_count = count( $days_week[ $i ] );
								for ( $j = 0; $j < $days_week_count; $j++ ) {
									if ( isset( $days_week[ $i ][ $j ] ) && $days_week[ $i ][ $j ] === $days_week[ $day_index ][ $line_index ] ) {
										if ( $max_line < $j ) {
											$max_line = $j;
										}
									}
								}
							}
							if ( $max_line > $line_index ) {
								array_splice( $days_week[ $day_index ], $line_index, 0, -1 );
								$days_week[ $day_index ][ $line_index ] = -1;

								$day_index_count = count( $days_week[ $day_index ] );
							}
						}
					}
				}

				$line_count = max(
					count( $days_week[0] ),
					count( $days_week[1] ),
					count( $days_week[2] ),
					count( $days_week[3] ),
					count( $days_week[4] ),
					count( $days_week[5] ),
					count( $days_week[6] )
				);

				for ( $line_index = 0; $line_index < $line_count; $line_index++ ) {
					$month_html .= '<table class="month-event">';
					$month_html .= '<tbody>';
					$month_html .= '<tr>';
					for ( $day_index = 0; $day_index < 7; $day_index++ ) {
						if ( ! isset( $days_week[ $day_index ][ $line_index ] ) ) {
							$month_html .= '<td></td>';
						} elseif ( -1 === $days_week[ $day_index ][ $line_index ] ) {
							$month_html .= '<td></td>';
						} else {
							$colspan = 1;
							if ( $day_index < 7 ) {
								for ( $i = $day_index + 1; $i < 7; $i++ ) {
									if ( isset( $days_week[ $i ][ $line_index ] ) && $days_week[ $day_index ][ $line_index ] === $days_week[ $i ][ $line_index ] ) {
										++$colspan;
									} else {
										break;
									}
								}
							}
							if ( $colspan > 0 ) {
								$event = $events[ $days_week[ $day_index ][ $line_index ] ];

								$short_title    = ( $event['short_title'] ) ? $event['short_title'] : $event['title'];
								$hsv            = XO_Color::get_hsv( XO_Color::get_rgb( $event['bg_color'] ) );
								$font_color     = $hsv['v'] > 0.8 ? '#333' : '#eee';
								$style          = "color:{$font_color}; background-color:{$event['bg_color']};";
								$category_class = empty( $event['category'] ) ? 'category-none' : 'category-' . $event['category'];

								$event_html = '<span class="month-event-title ' . esc_attr( $category_class ) . '" style="' . esc_attr( $style ) . '">' . $short_title . '</span>';
								if ( ! isset( $this->options['disable_event_link'] ) || ! $this->options['disable_event_link'] ) {
									$event_html = '<a href="' . esc_url( $event['permalink'] ) . '" title="' . esc_attr( $event['title'] ) . '">' . $event_html . '</a>';
								}

								/**
								 * Filters Event calendar event title HTML.
								 *
								 * @since 1.9.0
								 * @since 2.3.0 Added the `$args` parameter.
								 *
								 * @param string $event_html Event calendar event title HTML.
								 * @param array $event_post Event calendar event post object.
								 * @param array $options Option datas.
								 * @param array $args An array of arguments used to retrieve monthly calendar.
								 */
								$event_html = apply_filters( 'xo_event_calendar_event_title', $event_html, $event, $this->options, $args );

								$month_html .= "<td colspan=\"{$colspan}\">{$event_html}</td>";

								$day_index += $colspan - 1;
							}
						}
					}
					$month_html .= '</tr>';
					$month_html .= '</tbody>';
					$month_html .= '</table>';
				}
			}

			if ( 0 === $line_count ) {
				$month_html .= '<table class="month-event-space">';
				$month_html .= '<tbody><tr><td><div></div></td><td><div></div></td><td><div></div></td><td><div></div></td><td><div></div></td><td><div></div></td><td><div></div></td></tr></tbody>';
				$month_html .= '</table>';
			}

			$month_html .= '</td></tr>';
		}

		$m = strtotime( "{$args['year']}-{$args['month']}-1" );

		$prev_month = gmdate( 'Y-n', strtotime( '-1 month', $m ) );
		$next_month = gmdate( 'Y-n', strtotime( sprintf( '%d month', 2 - $month_index ), $m ) );

		if ( isset( $this->options['disable_dashicons'] ) && $this->options['disable_dashicons'] ) {
			$prev_text = '<span class="nav-prev">PREV</span>';
			$next_text = '<span class="nav-next">NEXT</span>';
		} else {
			$prev_text = '<span class="dashicons dashicons-arrow-left-alt2"></span>';
			$next_text = '<span class="dashicons dashicons-arrow-right-alt2"></span>';
		}

		if ( ! isset( $args['title_format'] ) || empty( $args['title_format'] ) ) {
			/* translators: 1: Month, 2: Year. */
			$calendar_caption = sprintf( _x( '%1$s %2$s', 'calendar caption', 'xo-event-calendar' ), $wp_locale->get_month( $args['month'] ), $args['year'] );
		} else {
			$t = strtotime( sprintf( '%04d-%02d', $args['year'], $args['month'] ) );
			if ( ! isset( $args['locale'] ) || $args['locale'] ) {
				$calendar_caption = date_i18n( $args['title_format'], $t );
			} else {
				$calendar_caption = gmdate( $args['title_format'], $t );
			}
		}

		/**
		 * Filters Event calendar month caption.
		 *
		 * @since 2.1.0
		 *
		 * @param string $calendar_caption Calendar month caption.
		 * @param array $args An array of arguments used to retrieve monthly calendar.
		 * @param int $month_index Calendar number.
		 */
		$calendar_caption = apply_filters( 'xo_event_calendar_month_caption', $calendar_caption, $args, $month_index );

		$html  = '<div class="calendar xo-month-wrap">';
		$html .= '<table class="xo-month">';
		$html .= '<caption>';
		$html .= '<div class="month-header">';
		if ( $args['navigation'] ) {
			if ( -1 === $args['prev_month_feed'] || $m > strtotime( "-{$args['prev_month_feed']} month", $base_month_time ) ) {
				/* translators: 1: Prev month, 2: Show event, 3: Categories string, 4: holidays string, 5: Prev month feed, 6: Next month feed, 7: Start of week, 8: Month count, 9: Navigation, 10: Title format, 11: Locale, 12: Columns, 13: Base month. */
				$onclick = sprintf(
					"this.disabled = true; xo_event_calendar_month(this,'%s',%d,'%s','%s',%d,%d,%d,%d,%d,'%s',%d,%d,'%s'); return false;",
					esc_js( $prev_month ),
					$args['show_event'],
					esc_js( $args['categories_string'] ),
					esc_js( $args['holidays_string'] ),
					$args['prev_month_feed'],
					$args['next_month_feed'],
					$args['start_of_week'],
					$args['months'],
					$args['navigation'],
					esc_js( isset( $args['title_format'] ) ? $args['title_format'] : '' ),
					isset( $args['locale'] ) ? $args['locale'] : 1,
					isset( $args['columns'] ) ? $args['columns'] : 1,
					esc_js( $base_month )
				);

				$html .= '<button type="button" class="month-prev" onclick="' . $onclick . '">' . $prev_text . '</button>';
			} else {
				$html .= '<button type="button" class="month-prev" disabled="disabled">' . $prev_text . '</button>';
			}
		}
		$html .= '<span class="calendar-caption">' . esc_html( $calendar_caption ) . '</span>';
		if ( $args['navigation'] ) {
			if ( -1 === $args['next_month_feed'] || $m < strtotime( "+{$args['next_month_feed']} month", $base_month_time ) ) {
				/* translators: 1: Prev month, 2: Show event, 3: Categories string, 4: holidays string, 5: Prev month feed, 6: Next month feed, 7: Start of week, 8: Month count, 9: Navigation, 10: Title format, 11: Locale, 12: Columns, 13: Base month. */
				$onclick = sprintf(
					"this.disabled = true; xo_event_calendar_month(this,'%s',%d,'%s','%s',%d,%d,%d,%d,%d,'%s',%d,%d,'%s'); return false;",
					esc_js( $next_month ),
					$args['show_event'],
					esc_js( $args['categories_string'] ),
					esc_js( $args['holidays_string'] ),
					$args['prev_month_feed'],
					$args['next_month_feed'],
					$args['start_of_week'],
					$args['months'],
					$args['navigation'],
					esc_js( isset( $args['title_format'] ) ? $args['title_format'] : '' ),
					isset( $args['locale'] ) ? $args['locale'] : 1,
					isset( $args['columns'] ) ? $args['columns'] : 1,
					esc_js( $base_month )
				);

				$html .= '<button type="button" class="month-next" onclick="' . $onclick . '">' . $next_text . '</button>';
			} else {
				$html .= '<button type="button" class="month-next" disabled="disabled" >' . $next_text . '</button>';
			}
		}
		$html .= '</div>';
		$html .= '</caption>';

		$html .= '<thead>';
		$html .= '<tr>';
		for ( $day_index = 0; $day_index < 7; $day_index++ ) {
			$weekday_number = ( $day_index + $args['start_of_week'] ) % 7;
			if ( ! isset( $args['locale'] ) || $args['locale'] ) {
				$weekday = $this->get_locale_weekday_initial( $weekday_number );
			} else {
				$weekday = strtoupper( $this->get_weekday_initial( $weekday_number ) );
			}
			$html .= '<th class="' . esc_attr( strtolower( $this->get_weekday( $weekday_number ) ) ) . '">' . esc_html( $weekday ) . '</th>';
		}
		$html .= '</tr>';
		$html .= '</thead>';
		$html .= '<tbody>';
		$html .= $month_html;
		$html .= '</tbody>';
		$html .= '</table>';
		$html .= '</div>' . "\n";

		/**
		 * Filters Event calendar month HTML.
		 *
		 * @since 1.9.0
		 *
		 * @param string $html Event calendar event title HTML.
		 * @param array $args An array of arguments used to retrieve monthly calendar.
		 * @param int $month_index Calendar number.
		 * @param array $event_posts Event calendar event posts.
		 */
		$html = apply_filters( 'xo_event_calendar_month', $html, $args, $month_index, $events );

		return $html;
	}

	/**
	 * Render the event calendar.
	 *
	 * @since 1.7.0
	 * @deprecated 3.0.0 Use get_event_calendar()
	 *
	 * @param array $args Argument array.
	 */
	public function get_calendar( $args ) {
		return $this->get_event_calendar( $args );
	}

	/**
	 * Render the event calendar.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args     An array of arguments used to retrieve monthly calendar.
	 * @param bool  $is_block Optional. Whether it is for a block or not.
	 * @return string HTML
	 */
	public function get_event_calendar( $args, $is_block = false ) {
		/**
		 * Filters Event calendar arguments.
		 *
		 * @since 1.5.0
		 *
		 * @param array $args Event calendar arguments.
		 */
		$args = apply_filters( 'xo_event_calendar_args', $args );

		if ( $args['month'] < 1 ) {
			$args['month'] = 1;
		} elseif ( $args['month'] > 12 ) {
			$args['month'] = 12;
		}

		$retour = '';

		if ( ! $is_block ) {
			$id      = isset( $args['id'] ) && ! empty( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '"' : '';
			$class   = isset( $args['class'] ) && ! empty( $args['class'] ) ? $args['class'] : 'xo-event-calendar';
			$retour .= '<div ' . $id . ' class="' . esc_attr( $class ) . '" >';
		}

		$class = 'calendars xo-months';
		if ( isset( $args['columns'] ) && 1 < $args['columns'] ) {
			$class .= " columns-{$args['columns']}";
		}
		$retour .= '<div class="' . esc_attr( $class ) . '" >';

		$retour .= $this->get_event_calendar_month( $args );

		$count = isset( $args['months'] ) ? $args['months'] : 1;

		$args['base_year']  = $args['year'];
		$args['base_month'] = $args['month'];

		for ( $i = 2; $i <= $count; $i++ ) {
			$next_time = strtotime( '+1 month', strtotime( "{$args['year']}-{$args['month']}-1" ) );

			$args['year']  = gmdate( 'Y', $next_time );
			$args['month'] = gmdate( 'n', $next_time );

			$retour .= $this->get_event_calendar_month( $args, $i );
		}
		$retour .= '</div>';

		$html = '<div class="holiday-titles" >';

		$holiday_settings = get_option( 'xo_event_calendar_holiday_settings' );
		if ( $holiday_settings ) {
			$holidays = explode( ',', (string) $args['holidays_string'] );
			foreach ( $holidays as $holiday ) {
				if ( array_key_exists( $holiday, $holiday_settings ) ) {
					$html .= "<p class=\"holiday-title\"><span style=\"background-color: {$holiday_settings[$holiday]['color']};\"></span>{$holiday_settings[$holiday]['title']}</p>";
				}
			}
		}

		$html .= '</div>';

		/**
		 * Filters Calendar footer HTML.
		 *
		 * @since 2.0.0
		 *
		 * @param array $html Calendar footer HTML.
		 * @param array $args Event calendar arguments.
		 */
		$retour .= apply_filters( 'xo_event_calendar_footer', $html, $args );

		$retour .= '<div class="loading-animation"></div>';

		if ( ! $is_block ) {
			$retour .= "</div>\n";
		}

		return $retour;
	}

	/**
	 * Builds the Event Calendar shortcode output.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attr An array of shortcode attributes.
	 */
	public function event_calendar_shortcode( $attr ) {
		$args = shortcode_atts(
			array(
				'id'            => 'xo-event-calendar-1',
				'year'          => $this->date( 'Y' ),
				'month'         => $this->date( 'n' ),
				'event'         => 'true',
				'categories'    => null,
				'holidays'      => null,
				'previous'      => -1,
				'next'          => -1,
				'start_of_week' => 0,
				'months'        => 1,
				'navigation'    => 'true',
				'title_format'  => '',
			),
			$attr,
			'xo_event_calendar'
		);

		if ( empty( $args['title_format'] ) && isset( $attr['month_format'] ) ) {
			$args['title_format'] = $attr['month_format'];
		}

		return $this->get_event_calendar(
			array(
				'id'                => esc_attr( $args['id'] ),
				'year'              => $args['year'],
				'month'             => $args['month'],
				'show_event'        => (bool) ( strtolower( $args['event'] ) === 'true' ),
				'categories_string' => $args['categories'],
				'holidays_string'   => $args['holidays'],
				'prev_month_feed'   => intval( $args['previous'] ),
				'next_month_feed'   => intval( $args['next'] ),
				'start_of_week'     => intval( ( -1 === intval( $args['start_of_week'] ) ) ? get_option( 'start_of_week' ) : $args['start_of_week'] ),
				'months'            => intval( $args['months'] ),
				'navigation'        => (bool) ( strtolower( $args['navigation'] ) === 'true' ),
				'title_format'      => $args['title_format'],
			)
		);
	}

	/**
	 * Register the widgets.
	 *
	 * @since 1.0.0
	 */
	public function register_widget() {
		register_widget( 'XO_Widget_Event_Calendar' );
	}

	/**
	 * Filters the path of the current template before including it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template The path of the template to include.
	 * @return string The path of the template to include.
	 */
	public function template_include( $template ) {
		$post_type = $this->get_post_type();
		if ( is_singular( $post_type ) ) {
			$file_name = "single-{$post_type}.php";
		}
		if ( isset( $file_name ) ) {
			$theme_file = locate_template( $file_name );
		}
		if ( isset( $theme_file ) && $theme_file ) {
			return $template;
		}
		if ( isset( $file_name ) && $file_name ) {
			// No template.
			add_filter( 'next_post_link', '__return_false' );
			add_filter( 'previous_post_link', '__return_false' );
			add_filter( 'the_content', array( $this, 'single_content' ) );
		}
		return $template;
	}

	/**
	 * Get the event date and time.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_ID The post ID.
	 * @param array $args    The arguments.
	 * @return string Returns the event date and time.
	 */
	public function get_event_date( $post_ID, $args = array() ) {
		$defaults = array(
			'date_format' => get_option( 'date_format' ),
			'time_format' => get_option( 'time_format' ),
			'delimiter'   => ' - ',
		);

		$args = wp_parse_args( $args, $defaults );

		$custom = get_post_custom( $post_ID );

		$all_day      = (bool) $custom['event_all_day'][0];
		$start_date   = $custom['event_start_date'][0];
		$start_hour   = (int) $custom['event_start_hour'][0];
		$start_minute = (int) $custom['event_start_minute'][0];
		$end_date     = ! empty( $custom['event_end_date'][0] ) ? $custom['event_end_date'][0] : $start_date;
		$end_hour     = (int) $custom['event_end_hour'][0];
		$end_minute   = (int) $custom['event_end_minute'][0];

		$date = date_i18n( $args['date_format'], strtotime( $start_date ) );
		if ( ! $all_day ) {
			$date .= ' ' . date_i18n( $args['time_format'], strtotime( $start_hour . ':' . $start_minute ) );
		}
		if ( $start_date !== $end_date ) {
			$date .= $args['delimiter'] . date_i18n( $args['date_format'], strtotime( $end_date ) );
			if ( ! $all_day ) {
				$date .= ' ' . date_i18n( $args['time_format'], strtotime( $end_hour . ':' . $end_minute ) );
			}
		} elseif ( ! $all_day && ( $start_hour * 60 + $start_minute ) < ( $end_hour * 60 + $end_minute ) ) {
			$date .= $args['delimiter'] . date_i18n( $args['time_format'], strtotime( $end_hour . ':' . $end_minute ) );
		}

		return $date;
	}

	/**
	 * Retrieves the single post content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The single post content.
	 * @return string Returns the single post content.
	 */
	public function single_content( $content ) {
		$post_ID  = get_the_ID();
		$taxonomy = $this->get_taxonomy_type();

		$details  = '<div class="xo-event-meta-details">';
		$details .= '<div class="xo-event-meta">';
		$details .= '<span class="xo-event-date">' . esc_html__( 'Event date:', 'xo-event-calendar' ) . ' ' . $this->get_event_date( $post_ID ) . '</span>';
		if ( get_the_terms( $post_ID, $taxonomy ) ) {
			$details .= '<span class="xo-event-category">' . esc_html__( 'Categories:', 'xo-event-calendar' ) . ' ' . get_the_term_list( $post_ID, $taxonomy, '', ', ', '' ) . '</span>';
		}
		$details .= '</div>';
		$details .= '</div>' . "\n";

		return $details . $content;
	}

	/**
	 * Retrieves event post categories.
	 *
	 * @since 2.2.9
	 *
	 * @param int $post_ID Optional. The event post ID. Defaults to current event post ID.
	 * @return object[] Array of event category objects
	 */
	public function get_the_category( $post_ID = false ) {
		if ( ! $post_ID ) {
			$post_ID = get_the_ID();
		}

		$categories    = array();
		$taxonomy_type = $this->get_taxonomy_type();
		$terms         = get_the_terms( $post_ID, $taxonomy_type );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $cat ) {
				$cat_data     = get_option( 'xo_event_calendar_cat_' . intval( $cat->term_id ) );
				$categories[] = (object) array(
					'id'    => $cat->term_id,
					'name'  => $cat->name,
					'slug'  => $cat->slug,
					'color' => ( $cat_data && $cat_data['category_color'] ) ? esc_html( $cat_data['category_color'] ) : '#ccc',
				);
			}
		}
		return $categories;
	}

	/**
	 * Registers the block type.
	 *
	 * @since 2.4.0
	 */
	public function register_block_type() {
		register_block_type(
			XO_EVENT_CALENDAR_DIR . 'build/event-calendar',
			array( 'render_callback' => array( $this, 'render_event_calendar_block' ) )
		);

		register_block_type(
			XO_EVENT_CALENDAR_DIR . 'build/simple-calendar',
			array( 'render_callback' => array( $this, 'render_simple_calendar_block' ) )
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'xo-event-calendar-event-calendar-editor-script', 'xo-event-calendar', XO_EVENT_CALENDAR_DIR . 'languages' );
		}
	}

	/**
	 * Registers a setting and its data.
	 *
	 * @since 2.4.0
	 */
	public function register_setting() {
		register_setting(
			'xo_event_calendar',
			'xo_event_calendar_holiday_settings',
			array(
				'type'         => 'object',
				'show_in_rest' => array(
					'schema' => array(
						'type'              => 'object',
						'properties'        => array(),
						'patternProperties' => array(
							'^[a-z0-9-]+$' => array(
								'type'       => 'object',
								'properties' => array(
									'title'           => array(
										'type' => 'string',
									),
									'dayofweek'       => array(
										'type'       => 'object',
										'properties' => array(
											'sun' => array( 'type' => 'boolean' ),
											'mon' => array( 'type' => 'boolean' ),
											'tue' => array( 'type' => 'boolean' ),
											'wed' => array( 'type' => 'boolean' ),
											'thu' => array( 'type' => 'boolean' ),
											'fri' => array( 'type' => 'boolean' ),
											'sat' => array( 'type' => 'boolean' ),
										),
									),
									'special_holiday' => array( 'type' => array( 'null', 'string' ) ),
									'non_holiday'     => array( 'type' => array( 'null', 'string' ) ),
									'color'           => array( 'type' => array( 'null', 'string' ) ),
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Renders the event calendar block.
	 *
	 * @since 3.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block.
	 */
	public function render_event_calendar_block( $attributes ) {
		$start_of_week = (int) ( $attributes['startOfWeek'] ?? -1 );
		if ( -1 === $start_of_week ) {
			$start_of_week = (int) get_option( 'start_of_week', 0 );
		}

		$event = $attributes['event'] ?? 'off';

		$categories = '';
		switch ( $event ) {
			case 'on':
				$show_event = true;
				break;
			case 'selected':
				$show_event = true;
				$categories = $show_event ? str_replace( ' ', ',', $attributes['categories'] ) : '';
				break;
			default:
				$show_event = false;
		}

		$holidays = isset( $attributes['holidays'] ) ? str_replace( ' ', ',', trim( $attributes['holidays'] ) ) : '';

		$y = $this->date( 'Y' );
		$m = $this->date( 'n' );
		if ( $attributes['selectedMonth'] ?? false ) {
			$y = (int) ( $attributes['year'] ?? $y );
			$m = (int) ( $attributes['month'] ?? $m );
		}

		$title_format = ( $attributes['defaultTitle'] ? '' : $attributes['titleFormat'] );

		$calendar = $this->get_event_calendar(
			array(
				'year'              => $y,
				'month'             => $m,
				'show_event'        => $show_event,
				'categories_string' => $categories,
				'holidays_string'   => $holidays,
				'prev_month_feed'   => (int) ( $attributes['prevMonths'] ?? -1 ),
				'next_month_feed'   => (int) ( $attributes['nextMonths'] ?? -1 ),
				'start_of_week'     => $start_of_week,
				'months'            => (int) ( $attributes['months'] ?? 1 ),
				'navigation'        => (bool) ( $attributes['navigation'] ?? true ),
				'title_format'      => $title_format,
				'columns'           => (int) ( $attributes['columns'] ?? 1 ),
				'locale'            => (bool) ( $attributes['localeTranslation'] ?? true ),
			),
			true
		);

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'xo-event-calendar' ) );
		$output             = sprintf(
			'<div %1$s>%2$s</div>',
			$wrapper_attributes,
			$calendar
		);

		return $output;
	}

	/**
	 * Renders the simple calendar block.
	 *
	 * @since 3.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block.
	 */
	public function render_simple_calendar_block( $attributes ) {
		$start_of_week = (int) ( $attributes['startOfWeek'] ?? -1 );
		if ( -1 === $start_of_week ) {
			$start_of_week = (int) get_option( 'start_of_week', 0 );
		}

		$holidays = isset( $attributes['holidays'] ) ? str_replace( ' ', ',', trim( $attributes['holidays'] ) ) : '';

		$y = $this->date( 'Y' );
		$m = $this->date( 'n' );
		if ( $attributes['selectedMonth'] ?? false ) {
			$y = (int) ( $attributes['year'] ?? $y );
			$m = (int) ( $attributes['month'] ?? $m );
		}

		$title_format = ( $attributes['defaultTitle'] ? '' : $attributes['titleFormat'] );

		$calendar = $this->get_simple_calendar(
			array(
				'year'            => $y,
				'month'           => $m,
				'holidays_string' => $holidays,
				'prev_month_feed' => (int) ( $attributes['prevMonths'] ?? -1 ),
				'next_month_feed' => (int) ( $attributes['nextMonths'] ?? -1 ),
				'start_of_week'   => $start_of_week,
				'months'          => (int) ( $attributes['months'] ?? 1 ),
				'navigation'      => (bool) ( $attributes['navigation'] ?? true ),
				'title_format'    => $title_format,
				'columns'         => (int) ( $attributes['columns'] ?? 1 ),
				'locale'          => (bool) ( $attributes['localeTranslation'] ?? true ),
				'caption_color'   => $attributes['captionTextColor'],
				'caption_bgcolor' => $attributes['captionBackgroundColor'],
			)
		);

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'xo-simple-calendar' ) );
		$output             = sprintf(
			'<div %1$s>%2$s</div>',
			$wrapper_attributes,
			$calendar
		);

		return $output;
	}

	/**
	 * Renders the month of the simple calendar.
	 *
	 * @param array $args        An array of arguments used to retrieve monthly calendar.
	 * @param int   $month_index Calendar number.
	 * @return string HTML.
	 */
	private function get_simple_calendar_month( $args, $month_index = 1 ) {
		global $wp_locale;

		$nums_table = array( 'first', 'second', 'third', 'fourth', 'fifth', 'sixth' );

		if ( $args['month'] < 1 ) {
			$args['month'] = 1;
		} elseif ( $args['month'] > 12 ) {
			$args['month'] = 12;
		}

		$time     = mktime( 0, 0, 0, $args['month'], 1, $args['year'] );
		$last_day = (int) gmdate( 't', $time );

		if ( ! empty( $args['holidays_string'] ) ) {
			$holidays         = explode( ',', $args['holidays_string'] );
			$holiday_settings = get_option( 'xo_event_calendar_holiday_settings' );
		}

		$html  = '<div class="calendar">';
		$html .= '<table class="month">';

		if ( ! isset( $args['title_format'] ) || empty( $args['title_format'] ) ) {
			/* translators: 1: Month, 2: Year. */
			$calendar_caption = sprintf( _x( '%1$s %2$s', 'calendar caption', 'xo-event-calendar' ), $wp_locale->get_month( $args['month'] ), $args['year'] );
		} else {
			$t = strtotime( sprintf( '%04d-%02d', $args['year'], $args['month'] ) );
			if ( ! isset( $args['locale'] ) || $args['locale'] ) {
				$calendar_caption = date_i18n( $args['title_format'], $t );
			} else {
				$calendar_caption = gmdate( $args['title_format'], $t );
			}
		}

		/**
		 * Filters Event calendar month caption.
		 *
		 * @since 3.0.0
		 *
		 * @param string $calendar_caption Calendar month caption.
		 * @param array $args An array of arguments used to retrieve monthly calendar.
		 * @param int $month_index Calendar number.
		 */
		$calendar_caption = apply_filters( 'xo_simple_calendar_month_caption', $calendar_caption, $args, $month_index );

		$style_color = '';
		if ( ! empty( $args['caption_color'] ) ) {
			$style_color .= 'color:' . $this->esc_color( $args['caption_color'] ) . ';';
		}
		if ( ! empty( $args['caption_bgcolor'] ) ) {
			$style_color .= 'background-color:' . $this->esc_color( $args['caption_bgcolor'] ) . ';';
		}

		$html .= '<caption style="' . $style_color . '">';
		$html .= '<div class="month-header">';
		if ( $args['navigation'] ) {
			if ( ! isset( $args['base_year'] ) ) {
				$args['base_year'] = $args['year'];
			}
			if ( ! isset( $args['base_month'] ) ) {
				$args['base_month'] = $args['month'];
			}

			$base_month_time = strtotime( "{$args['base_year']}-{$args['base_month']}-1" );
			$base_month      = gmdate( 'Y-n', $base_month_time );

			$m = strtotime( "{$args['year']}-{$args['month']}-1" );

			$prev_month = gmdate( 'Y-n', strtotime( '-1 month', $m ) );
			$next_month = gmdate( 'Y-n', strtotime( sprintf( '%d month', 2 - $month_index ), $m ) );

			if ( -1 === $args['prev_month_feed'] || $m > strtotime( "-{$args['prev_month_feed']} month", $base_month_time ) ) {
				/* translators: 1: Prev month, 2: holidays string, 3: Prev month feed, 4: Next month feed, 5: Start of week, 6: Month count, 7: Navigation, 8: Title format, 9: Locale, 10: Columns, 11: Base month. */
				$onclick = sprintf(
					"this.disabled = true; xo_simple_calendar_month(this,'%s','%s',%d,%d,%d,%d,%d,'%s',%d,%d,'%s','%s','%s'); return false;",
					esc_js( $prev_month ),
					esc_js( $args['holidays_string'] ),
					$args['prev_month_feed'],
					$args['next_month_feed'],
					$args['start_of_week'],
					$args['months'],
					$args['navigation'],
					esc_js( isset( $args['title_format'] ) ? $args['title_format'] : '' ),
					isset( $args['locale'] ) ? $args['locale'] : 1,
					isset( $args['columns'] ) ? $args['columns'] : 1,
					esc_js( isset( $args['caption_color'] ) ? $args['caption_color'] : '' ),
					esc_js( isset( $args['caption_bgcolor'] ) ? $args['caption_bgcolor'] : '' ),
					esc_js( $base_month )
				);

				$html .= '<button type="button" class="month-prev" onclick="' . $onclick . '">';
			} else {
				$html .= '<button type="button" class="month-prev" disabled="disabled">';
			}
			$html .= '<span style="' . esc_attr( $style_color ) . '">&lsaquo;</span></button>';

			$html .= '<span class="month-title">' . esc_html( $calendar_caption ) . '</span>';

			if ( -1 === $args['next_month_feed'] || $m < strtotime( "+{$args['next_month_feed']} month", $base_month_time ) ) {
				/* translators: 1: Prev month, 2: holidays string, 3: Prev month feed, 4: Next month feed, 5: Start of week, 6: Month count, 7: Navigation, 8: Title format, 9: Locale, 10: Columns, 11: Base month. */
				$onclick = sprintf(
					"this.disabled = true; xo_simple_calendar_month(this,'%s','%s',%d,%d,%d,%d,%d,'%s',%d,%d,'%s','%s','%s'); return false;",
					esc_js( $next_month ),
					esc_js( $args['holidays_string'] ),
					$args['prev_month_feed'],
					$args['next_month_feed'],
					$args['start_of_week'],
					$args['months'],
					$args['navigation'],
					esc_js( isset( $args['title_format'] ) ? $args['title_format'] : '' ),
					isset( $args['locale'] ) ? $args['locale'] : 1,
					isset( $args['columns'] ) ? $args['columns'] : 1,
					esc_js( isset( $args['caption_color'] ) ? $args['caption_color'] : '' ),
					esc_js( isset( $args['caption_bgcolor'] ) ? $args['caption_bgcolor'] : '' ),
					esc_js( $base_month )
				);

				$html .= '<button type="button" class="month-next" onclick="' . $onclick . '">';
			} else {
				$html .= '<button type="button" class="month-next" disabled="disabled">';
			}
			$html .= '<span style="' . esc_attr( $style_color ) . '">&rsaquo;</span></button>';

		} else {
			$html .= '<span class="title">' . esc_html( $calendar_caption ) . '</span>';
		}
		$html .= '</div>'; // .month-header
		$html .= '</caption>';

		$html .= '<thead>';
		$html .= '<tr class="week-days">';
		for ( $i = 0; $i <= 6; $i++ ) {
			$weekday_number = ( $i + $args['start_of_week'] ) % 7;
			if ( ! isset( $args['locale'] ) || $args['locale'] ) {
				$weekday = $this->get_locale_weekday_initial( $weekday_number );
			} else {
				$weekday = strtoupper( $this->get_weekday_initial( $weekday_number ) );
			}
			$html .= '<th class="' . esc_attr( strtolower( $this->get_weekday_initial( $weekday_number ) ) ) . '"><span>' . esc_html( $weekday ) . '</span></th>';
		}
		$html .= '</tr>';
		$html .= '</thead>';

		$week               = gmdate( 'w', mktime( 0, 0, 0, $args['month'], 1, $args['year'] ) );
		$weeks_in_month     = ceil( ( ( ( 7 + $week - $args['start_of_week'] ) % 7 ) + $last_day ) / 7 );
		$days_in_month      = (int) gmdate( 't', $time );
		$prev_days_in_month = (int) gmdate( 't', mktime( 0, 0, 0, ( 1 === $args['month'] ? 12 : $args['month'] - 1 ), 1, ( 1 === $args['year'] ? $args['year'] - 1 : $args['year'] ) ) );

		$index = ( $args['start_of_week'] - gmdate( 'w', $time ) ) + 1;
		if ( 1 < $index ) {
			$index -= 7;
		}

		$html .= '<tbody>';
		for ( $weeks = 0; $weeks < $weeks_in_month; $weeks++ ) {
			$html .= '<tr class="' . esc_attr( $nums_table[ $weeks ] ) . '">';
			for ( $i = 0; $i < 7; $i++ ) {
				$weekday_initial = strtolower( $this->get_weekday_initial( ( $i + $args['start_of_week'] ) % 7 ) );

				$class = "day $weekday_initial";
				if ( 0 >= $index ) {
					$class .= ' other';
					$day    = $prev_days_in_month + $index;
				} elseif ( $days_in_month < $index ) {
					$class .= ' other';
					$day    = $index - $days_in_month;
				} else {
					$day = $index;
				}

				$style = '';
				if ( isset( $holiday_settings ) ) {
					$date          = mktime( 0, 0, 0, $args['month'], $index, $args['year'] );
					$holiday_slugs = $this->get_holiday_slug( $holidays, $holiday_settings, $date );
					if ( count( $holiday_slugs ) ) {
						$holiday_slug  = end( $holiday_slugs );
						$style         = 'background-color: ' . $holiday_settings[ $holiday_slug ]['color'] . ';';
						$holiday_class = implode(
							' ',
							array_map(
								function ( $s ) {
									return "holiday-{$s}";
								},
								$holiday_slugs
							)
						);
						if ( ! empty( $holiday_class ) ) {
							$class .= ' holiday ' . $holiday_class;
						}
					}
				}

				$html .= '<td class="' . esc_attr( $class ) . '">';
				$html .= '<span style="' . esc_attr( $style ) . '">' . $day . '</span>';
				$html .= '</td>';

				++$index;
			}
			$html .= '</tr>';
		}
		$html .= '</tbody>';

		$html .= '</table>';
		$html .= '</div>'; // .xo-simple-calendar-table

		return $html;
	}

	/**
	 * Render the simple calendar.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args An array of arguments used to retrieve monthly calendar.
	 * @return string HTML
	 */
	public function get_simple_calendar( $args ) {
		$class = 'calendars';

		if ( isset( $args['columns'] ) && 1 < $args['columns'] ) {
			$class .= " columns-{$args['columns']}";
		}

		$html = '<div class="' . esc_attr( $class ) . '">';

		$html .= $this->get_simple_calendar_month( $args );

		$count = isset( $args['months'] ) ? $args['months'] : 1;

		$args['base_year']  = $args['year'];
		$args['base_month'] = $args['month'];

		for ( $i = 2; $i <= $count; $i++ ) {
			$next_time     = strtotime( '+1 month', strtotime( "{$args['year']}-{$args['month']}-1" ) );
			$args['year']  = gmdate( 'Y', $next_time );
			$args['month'] = gmdate( 'n', $next_time );
			$html         .= $this->get_simple_calendar_month( $args, $i );
		}

		$html .= '</div>'; // .calendars

		$html .= '<div class="calendars-footer">';
		$html .= '<ul class="holiday-titles">';

		$holiday_settings = get_option( 'xo_event_calendar_holiday_settings' );
		if ( $holiday_settings ) {
			$holidays = explode( ',', $args['holidays_string'] );
			foreach ( $holidays as $holiday ) {
				if ( array_key_exists( $holiday, $holiday_settings ) ) {
					$html .= '<li class="holiday-title">';
					$html .= '<span class="mark" style="background-color:' . esc_attr( $holiday_settings[ $holiday ]['color'] ) . '"></span> ';
					$html .= '<span class="title">' . esc_html( $holiday_settings[ $holiday ]['title'] ) . '</span>';
					$html .= '</li>';
				}
			}
		}

		$html .= '</ul>'; // .holiday-titles
		$html .= '</div>'; // .calendars-footer

		$html .= '<div class="calendar-loading-animation"></div>';

		return $html;
	}

	/**
	 * Builds the Event field shortcode output.
	 *
	 * @since 3.2.0
	 *
	 * @global WP_Post $post Global post object.
	 *
	 * @param array $attr An array of shortcode attributes.
	 */
	public function event_field_shortcode( $attr ) {
		global $post;

		$args = shortcode_atts(
			array(
				'field'       => 'date',
				'date_format' => null,
				'time_format' => null,
				'delimiter'   => ' - ',
			),
			$attr,
			'xo_event_field'
		);

		if ( empty( $post ) || $post->post_type !== $this->get_post_type() ) {
			return '';
		}

		return $this->get_event_field(
			$post->ID,
			array(
				'field'       => $args['field'],
				'date_format' => $args['date_format'],
				'time_format' => $args['time_format'],
				'delimiter'   => $args['delimiter'],
			)
		);
	}

	/**
	 * Render the event field.
	 *
	 * @since 3.2.0
	 *
	 * @param int   $post_ID The post ID.
	 * @param array $args An array of arguments used to retrieve monthly calendar.
	 * @return string HTML
	 */
	public function get_event_field( $post_ID, $args = array() ) {
		$defaults = array(
			'field'       => 'date',
			'date_format' => null,
			'time_format' => null,
			'delimiter'   => ' - ',
		);

		$args = wp_parse_args( $args, $defaults );

		$retour = '';

		$custom = get_post_custom( $post_ID );

		$all_day      = (bool) $custom['event_all_day'][0];
		$start_date   = $custom['event_start_date'][0];
		$start_hour   = (int) $custom['event_start_hour'][0];
		$start_minute = (int) $custom['event_start_minute'][0];
		$end_date     = ! empty( $custom['event_end_date'][0] ) ? $custom['event_end_date'][0] : $start_date;
		$end_hour     = (int) $custom['event_end_hour'][0];
		$end_minute   = (int) $custom['event_end_minute'][0];

		$start_datetime = $start_date . ' ' . $start_hour . ':' . $start_minute;
		$end_datetime   = $end_date . ' ' . $end_hour . ':' . $end_minute;

		$date_format = $args['date_format'] ?? get_option( 'date_format', '' );
		$time_format = $args['time_format'] ?? get_option( 'time_format', '' );

		$datetime_format = $date_format;
		if ( ! $all_day && ! empty( $time_format ) ) {
			$datetime_format .= ' ' . $time_format;
		}

		switch ( strtolower( $args['field'] ) ) {
			case 'date':
				$retour = date_i18n( $datetime_format, strtotime( $start_datetime ) );
				if ( $start_date !== $end_date ) {
					$retour .= $args['delimiter'] . date_i18n( $datetime_format, strtotime( $end_datetime ) );
				} elseif ( ! $all_day && ( $start_hour * 60 + $start_minute ) < ( $end_hour * 60 + $end_minute ) ) {
					$retour .= $args['delimiter'] . date_i18n( $time_format, strtotime( $end_datetime ) );
				}
				break;
			case 'start_date':
				$retour = date_i18n( $datetime_format, strtotime( $start_datetime ) );
				break;
			case 'end_date':
				$retour = date_i18n( $datetime_format, strtotime( $end_datetime ) );
				break;
			case 'start_time':
				$retour = date_i18n( $time_format, strtotime( $start_datetime ) );
				break;
			case 'end_time':
				$retour = date_i18n( $time_format, strtotime( $end_datetime ) );
				break;
		}

		return $retour;
	}
}
