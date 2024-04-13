<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * XO Event Calendar admin.
 *
 * @package xo-event-calendar
 * @since 1.0.0
 */

/**
 * XO Event Calendar admin class.
 */
class XO_Event_Calendar_Admin {
	/**
	 * XO_Event_Calendar instance.
	 *
	 * @var XO_Event_Calendar
	 */
	private $parent;

	/**
	 * Construction.
	 *
	 * @since 1.0.0
	 *
	 * @param XO_Event_Calendar $parent_object Parent object.
	 */
	public function __construct( $parent_object ) {
		$this->parent = $parent_object;
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Runs on plugins_loaded hook.
	 *
	 * @since 1.0.0
	 */
	public function plugins_loaded() {
		$post_type_name = XO_Event_Calendar::get_post_type();
		$taxonomy_name  = XO_Event_Calendar::get_taxonomy_type();

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );

		add_filter( "manage_edit-{$taxonomy_name}_columns", array( $this, 'category_columns' ) );
		add_filter( "manage_{$taxonomy_name}_custom_column", array( $this, 'category_custom_column' ), 10, 3 );
		add_action( "{$taxonomy_name}_add_form_fields", array( $this, 'category_add_form_fields' ) );
		add_action( "{$taxonomy_name}_edit_form_fields", array( $this, 'category_edit_form_fields' ) );
		add_action( "edited_{$taxonomy_name}", array( $this, 'save_category' ) );
		add_action( "created_{$taxonomy_name}", array( $this, 'save_category' ) );
		add_action( "delete_{$taxonomy_name}", array( $this, 'delete_category' ) );

		add_filter( "manage_edit-{$post_type_name}_columns", array( $this, 'event_columns' ) );
		add_filter( "manage_edit-{$post_type_name}_sortable_columns", array( $this, 'event_sortable_columns' ) );
		add_filter( 'posts_clauses', array( $this, 'posts_clauses' ), 10, 2 );
		add_action( "manage_{$post_type_name}_posts_custom_column", array( $this, 'event_custom_column' ), 10, 2 );

		add_action( "add_meta_boxes_{$post_type_name}", array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ) );

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_option_settings' ) );
	}

	/**
	 * Enqueue a script in the WordPress admin.
	 *
	 * @since 1.0.0
	 *
	 * @param int $hook Hook suffix for the current admin page.
	 */
	public function admin_enqueue_scripts( $hook ) {
		wp_enqueue_style( 'xo-event-calendar-admin', XO_EVENT_CALENDAR_URL . 'css/admin.css', array(), XO_EVENT_CALENDAR_VERSION );

		if ( 'xo_event_page_xo-event-holiday-settings' === $hook || 'edit-tags.php' === $hook || 'term.php' === $hook ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'xo-event-calendar-admin', XO_EVENT_CALENDAR_URL . 'js/admin.js', array( 'wp-color-picker' ), XO_EVENT_CALENDAR_VERSION, true );
		}
	}

	/**
	 * Filters the post updated messages.
	 *
	 * @since 1.0.0
	 *
	 * @param array $messages Post updated messages. For defaults see `$messages` declarations above.
	 */
	public function post_updated_messages( $messages ) {
		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );
		$post_type_name   = XO_Event_Calendar::get_post_type();

		$messages[ $post_type_name ] = array(
			0  => '',
			1  => __( 'Event updated.', 'xo-event-calendar' ),
			2  => __( 'Custom field updated.', 'xo-event-calendar' ),
			3  => __( 'Custom field deleted.', 'xo-event-calendar' ),
			4  => __( 'Event updated.', 'xo-event-calendar' ),
			/* translators: %s: Retrieves formatted date timestamp. */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Event restored to revision from %s.', 'xo-event-calendar' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Copying core message handling.
			6  => __( 'Event published.', 'xo-event-calendar' ),
			7  => __( 'Event saved.', 'xo-event-calendar' ),
			8  => __( 'Event submitted.', 'xo-event-calendar' ),
			/* translators: 1: Publishing time. */
			9  => sprintf( __( 'Event scheduled for: <strong>%1$s</strong>.', 'xo-event-calendar' ), date_i18n( __( 'M j, Y @ G:i', 'xo-event-calendar' ), strtotime( $post->post_date ) ) ),
			10 => __( 'Event draft updated.', 'xo-event-calendar' ),
		);

		return $messages;
	}

	/**
	 * Returns columns of event categories.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of columns.
	 * @return array $columns.
	 */
	public function category_columns( $columns ) {
		$offset = array_search( 'posts', array_keys( $columns ), true );
		return array_merge(
			array_slice( $columns, 0, $offset ),
			array( 'color' => __( 'Color', 'xo-event-calendar' ) ),
			array_slice( $columns, $offset, null )
		);
	}

	/**
	 * Filters the displayed columns in the terms list table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html    Custom column output. Default empty.
	 * @param string $column  Name of the column.
	 * @param int    $term_id Term ID.
	 * @return string Custom column output.
	 */
	public function category_custom_column( $html, $column, $term_id ) {
		if ( 'color' === $column ) {
			$cat_options = get_option( 'xo_event_calendar_cat_' . $term_id );

			$color = ! empty( $cat_options['category_color'] ) ? $cat_options['category_color'] : '#ffffff';
			$hsv   = XO_Color::get_hsv( XO_Color::get_rgb( $color ) );

			$html = sprintf(
				'<p><div style="width: 4.0rem; color: %s; border: 1px solid #ddd; background-color: %s; text-align: center; padding: 2px;"><span>%s</span></div></p>',
				( $hsv['v'] > 0.9 ? '#333' : '#eee' ),
				esc_attr( $color ),
				esc_html( $color )
			);
		}
		return $html;
	}

	/**
	 * Fires after the Add Term form fields.
	 *
	 * @since 1.0.0
	 */
	public function category_add_form_fields() {
		$color = sprintf( '#%06x', wp_rand( 0x000000, 0xFFFFFF ) );

		echo '<div class="form-field term-category-color-wrap">';
		echo '<label for="tag-category-color">' . esc_html__( 'Color', 'xo-event-calendar' ) . '</label>';
		echo '<input id="category_color" class="c-picker" type="text" name="cat_meta[category_color]" value="' . esc_attr( $color ) . '" />';
		echo '</div>' . "\n";

		wp_nonce_field( 'xo_event_calendar_category_action', 'xo_event_calendar_category_nonce' );
	}

	/**
	 * Fires after the Edit Term form fields are displayed.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Term $term Current taxonomy term object.
	 */
	public function category_edit_form_fields( $term ) {
		$term_id     = $term->term_id;
		$cat_options = get_option( 'xo_event_calendar_cat_' . $term_id );
		$color       = ( $cat_options && isset( $cat_options['category_color'] ) ) ? $cat_options['category_color'] : '';

		echo '<tr class="form-field term-category-color-wrap">';
		echo '<th scope="row"><label for="category_color">' . esc_html__( 'Color', 'xo-event-calendar' ) . '</label></th>';
		echo '<td><input id="category_color" class="c-picker" type="text" name="cat_meta[category_color]" value="' . esc_attr( $color ) . '" />';
		echo '</tr>' . "\n";

		wp_nonce_field( 'xo_event_calendar_category_action', 'xo_event_calendar_category_nonce' );
	}

	/**
	 * Save an event category.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_category( $term_id ) {
		if ( ! isset( $_POST['xo_event_calendar_category_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['xo_event_calendar_category_nonce'], 'xo_event_calendar_category_action' ) ) { // phpcs:ignore
			return;
		}

		if ( isset( $_POST['cat_meta'] ) && isset( $_POST['cat_meta']['category_color'] ) ) {
			$cat_meta = get_option( "xo_event_calendar_cat_{$term_id}", array() );

			$cat_meta['category_color'] = sanitize_hex_color( wp_unslash( $_POST['cat_meta']['category_color'] ) );

			update_option( "xo_event_calendar_cat_{$term_id}", $cat_meta );
		}
	}

	/**
	 * Delete an event category.
	 *
	 * @param int $term_id Term ID.
	 */
	public function delete_category( $term_id ) {
		delete_option( 'xo_event_calendar_cat_' . $term_id );
	}

	/**
	 * Returns columns of event popsts.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of event posts.
	 * @return array $columns.
	 */
	public function event_columns( $columns ) {
		if ( is_array( $columns ) ) {
			$index = array_search( 'title', array_keys( $columns ), true );
			if ( false !== $index ) {
				$before  = array_slice( $columns, 0, $index + 1 );
				$after   = array_splice( $columns, $index + 1, count( $columns ) );
				$columns = $before + array(
					'author'    => __( 'Author', 'xo-event-calendar' ),
					'datestart' => __( 'Start Date', 'xo-event-calendar' ),
					'dateend'   => __( 'End Date', 'xo-event-calendar' ),
					'category'  => __( 'Category', 'xo-event-calendar' ),
				) + $after;
			}
		}
		return $columns;
	}

	/**
	 * Returns columns of event popsts.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of event posts.
	 * @return array $columns.
	 */
	public function event_sortable_columns( $columns ) {
		$columns['category']  = 'category';
		$columns['datestart'] = 'datestart';
		$columns['dateend']   = 'dateend';
		return $columns;
	}

	/**
	 * Filters all query clauses at once, for convenience.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $clauses An array including WHERE, GROUP BY, JOIN, ORDER BY, DISTINCT, fields (SELECT), and LIMITS clauses.
	 * @param WP_Query $wp_query   The WP_Query instance.
	 * @return string[] The modified array of clauses.
	 */
	public function posts_clauses( $clauses, $wp_query ) {
		global $wpdb, $pagenow, $typenow;

		if ( is_admin() && 'edit.php' === $pagenow && 'xo_event' === $typenow ) {
			if ( isset( $wp_query->query['orderby'] ) ) {
				$order = ( 'ASC' === strtoupper( $wp_query->get( 'order' ) ) ) ? 'ASC' : 'DESC';
				if ( 'category' === $wp_query->query['orderby'] ) {
					$clauses['join']   .= " LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id";
					$clauses['join']   .= " LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)";
					$clauses['join']   .= " LEFT OUTER JOIN {$wpdb->terms} USING (term_id)";
					$clauses['where']  .= " AND (taxonomy = 'xo_event_cat' OR taxonomy IS NULL)";
					$clauses['groupby'] = 'object_id';
					$clauses['orderby'] = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC) $order";
				} elseif ( 'datestart' === $wp_query->query['orderby'] ) {
					$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} ON post_id = ID";
					$clauses['where']  .= " AND meta_key = 'event_start_date'";
					$clauses['orderby'] = "CAST(meta_value AS DATE) $order";
				} elseif ( 'dateend' === $wp_query->query['orderby'] ) {
					$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} ON post_id = ID";
					$clauses['where']  .= " AND meta_key = 'event_end_date'";
					$clauses['orderby'] = "CAST(meta_value AS DATE) $order";
				}
			}
		}
		return $clauses;
	}

	/**
	 * Fires for each custom column of a specific post type in the Posts list table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column_name The name of the column to display.
	 * @param int    $post_id     The current post ID.
	 */
	public function event_custom_column( $column_name, $post_id ) {
		if ( 'datestart' === $column_name ) {
			$d = get_post_meta( $post_id, 'event_start_date', true );
			if ( ! empty( $d ) ) {
				$d      = gmdate( get_option( 'date_format' ), strtotime( $d ) );
				$allday = (bool) get_post_meta( $post_id, 'event_all_day', true );
				if ( ! $allday ) {
					$h = get_post_meta( $post_id, 'event_start_hour', true );
					$m = get_post_meta( $post_id, 'event_start_minute', true );
					echo esc_html( sprintf( '%s %d:%02d', $d, $h, $m ) );
				} else {
					echo esc_html( $d );
				}
			}
		} elseif ( 'dateend' === $column_name ) {
			$d = get_post_meta( $post_id, 'event_end_date', true );
			if ( ! empty( $d ) ) {
				$d      = gmdate( get_option( 'date_format' ), strtotime( $d ) );
				$allday = (bool) get_post_meta( $post_id, 'event_all_day', true );
				if ( ! $allday ) {
					$h = get_post_meta( $post_id, 'event_end_hour', true );
					$m = get_post_meta( $post_id, 'event_end_minute', true );
					echo esc_html( sprintf( '%s %d:%02d', $d, $h, $m ) );
				} else {
					echo esc_html( $d );
				}
			}
		} elseif ( 'category' === $column_name ) {
			$cats = get_the_terms( $post_id, XO_Event_Calendar::get_taxonomy_type() );
			if ( $cats && count( $cats ) > 0 ) {
				echo esc_html( $cats[0]->name );
			}
		}
	}

	/**
	 * Add an event metabox.
	 *
	 * @since 1.0.0
	 */
	public function add_meta_boxes() {
		add_meta_box( 'xo-event-meta-box', __( 'Event Details', 'xo-event-calendar' ), array( $this, 'event_meta_box' ), XO_Event_Calendar::get_post_type(), 'advanced' );
	}

	/**
	 * Displays the event metabox.
	 *
	 * @since 1.0.0
	 */
	public function event_meta_box() {
		global $post;

		$custom = get_post_custom( $post->ID );
		if ( empty( $custom ) ) {
			$start_date   = '';
			$start_hour   = '';
			$start_minute = '';
			$end_date     = '';
			$end_hour     = '';
			$end_minute   = '';
			$all_day      = true;
			$short_title  = '';
		} else {
			$start_date   = isset( $custom['event_start_date'][0] ) ? gmdate( 'Y-m-d', strtotime( $custom['event_start_date'][0] ) ) : date_i18n( 'Y-m-d' );
			$start_hour   = isset( $custom['event_start_hour'][0] ) ? $custom['event_start_hour'][0] : '';
			$start_minute = isset( $custom['event_start_minute'][0] ) ? $custom['event_start_minute'][0] : '';
			$end_date     = isset( $custom['event_end_date'][0] ) ? gmdate( 'Y-m-d', strtotime( $custom['event_end_date'][0] ) ) : '';
			$end_hour     = isset( $custom['event_end_hour'][0] ) ? $custom['event_end_hour'][0] : '';
			$end_minute   = isset( $custom['event_end_minute'][0] ) ? $custom['event_end_minute'][0] : '';
			$all_day      = isset( $custom['event_all_day'][0] ) ? $custom['event_all_day'][0] : true;
			$short_title  = isset( $custom['short_title'][0] ) ? $custom['short_title'][0] : '';
		}

		wp_nonce_field( 'xo_event_calendar_meta_box_data', 'xo_event_calendar_meta_box_nonce' );
		?>
		<table class="xo-event-calendar-meta-box-table">
			<tr>
				<th nowrap><?php esc_html_e( 'Start Date/Time', 'xo-event-calendar' ); ?></th>
				<td>
					<input id="event_start_date" name="event_start_date" class="datepicker" type="date" value="<?php echo esc_attr( $start_date ); ?>" /> @
					<select id="event_start_hour" name="event_start_hour">
					<?php
					for ( $i = 0; $i < 24; $i++ ) {
						printf( '<option %s value="%d">%d</option>', ( $i === (int) $start_hour ? 'selected' : '' ), (int) $i, (int) $i ); // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					}
					?>
					</select>
					<select id="event_start_minute" name="event_start_minute">
					<?php
					for ( $i = 0; $i < 60; $i += 5 ) {
						printf( '<option %s value="%d">%02d</option>', ( $i === (int) $start_minute ? 'selected' : '' ), (int) $i, (int) $i ); // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					}
					?>
					</select>
					<input id="event_all_day" name="event_all_day" type="checkbox" value="1"<?php echo ( $all_day ? ' checked' : '' ); ?> /><label for="event_all_day"><?php esc_html_e( 'All Day', 'xo-event-calendar' ); ?></label>
				</td>
			</tr>
			<tr>
				<th nowrap><?php esc_html_e( 'End Date/Time', 'xo-event-calendar' ); ?></th>
				<td>
					<input id="event_end_date" name="event_end_date" class="datepicker" type="date" value="<?php echo esc_attr( $end_date ); ?>" /> @
					<select id="event_end_hour" name="event_end_hour">
					<?php
					for ( $i = 0; $i < 24; $i++ ) {
						printf( '<option %s value="%d">%d</option>', ( $i === (int) $end_hour ? 'selected' : '' ), (int) $i, (int) $i ); // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					}
					?>
					</select>
					<select id="event_end_minute" name="event_end_minute">
					<?php
					for ( $i = 0; $i < 60; $i += 5 ) {
						printf( '<option %s value="%d">%02d</option>', ( $i === (int) $end_minute ? 'selected' : '' ), (int) $i, (int) $i ); // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					}
					?>
					</select>
				</td>
			</tr>
			<tr>
				<th nowrap><?php esc_html_e( 'Short Title', 'xo-event-calendar' ); ?></th>
				<td><input id="short_title" name="short_title" type="text" size="20" value="<?php echo esc_attr( $short_title ); ?>" /></td>
			</tr>
		</table>
		<?php

		// 日付セレクト コントロールの幅が狭くなる不具合(?)対策.
		echo '<style type="text/css">.media-frame select.attachment-filters { min-width: 102px; }</style>';
	}

	/**
	 * Save the value of the event metabox.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The current post ID.
	 */
	public function save_post( $post_id ) {
		// 対象のフォームのから送られてきたかどうかチェックする.
		if ( ! isset( $_POST['xo_event_calendar_meta_box_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['xo_event_calendar_meta_box_nonce'], 'xo_event_calendar_meta_box_data' ) ) { // phpcs:ignore
			return $post_id;
		}
		// 自動保存ルーチンかどうかチェック。そうだった場合はフォームを送信しない（何もしない）.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		$start_date   = isset( $_POST['event_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_start_date'] ) ) : null;
		$start_hour   = isset( $_POST['event_start_hour'] ) ? sanitize_text_field( wp_unslash( $_POST['event_start_hour'] ) ) : null;
		$start_minute = isset( $_POST['event_start_minute'] ) ? sanitize_text_field( wp_unslash( $_POST['event_start_minute'] ) ) : null;
		$end_date     = isset( $_POST['event_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_end_date'] ) ) : $start_date;
		$end_hour     = isset( $_POST['event_end_hour'] ) ? sanitize_text_field( wp_unslash( $_POST['event_end_hour'] ) ) : null;
		$end_minute   = isset( $_POST['event_end_minute'] ) ? sanitize_text_field( wp_unslash( $_POST['event_end_minute'] ) ) : null;
		$all_day      = isset( $_POST['event_all_day'] );
		$short_title  = isset( $_POST['short_title'] ) ? sanitize_text_field( wp_unslash( $_POST['short_title'] ) ) : null;

		if ( empty( $end_date ) ) {
			$end_date = $start_date;
		}

		update_post_meta( $post_id, 'event_start_date', $start_date );
		update_post_meta( $post_id, 'event_start_hour', $start_hour );
		update_post_meta( $post_id, 'event_start_minute', $start_minute );
		update_post_meta( $post_id, 'event_end_date', $end_date );
		update_post_meta( $post_id, 'event_end_hour', $end_hour );
		update_post_meta( $post_id, 'event_end_minute', $end_minute );
		update_post_meta( $post_id, 'event_all_day', $all_day );
		update_post_meta( $post_id, 'short_title', $short_title );
	}

	/**
	 * Add a menu.
	 *
	 * @since 1.0.0
	 */
	public function add_menu() {
		$post_type_name = XO_Event_Calendar::get_post_type();

		$holiday_settings_page = add_submenu_page(
			"edit.php?post_type={$post_type_name}",
			'Holiday Settings',
			__( 'Holiday Settings', 'xo-event-calendar' ),
			XO_EVENT_CALENDAR_HOLIDAY_SETTING_CAPABILITY,
			'xo-event-holiday-settings',
			array( $this, 'holiday_settings_page' )
		);
		add_action( "load-{$holiday_settings_page}", array( $this, 'add_holiday_settings_page_tabs' ) );

		$settings_page = add_submenu_page(
			"edit.php?post_type={$post_type_name}",
			'Settings',
			__( 'Settings', 'xo-event-calendar' ),
			'manage_options',
			'xo-event-settings',
			array( $this, 'settings_page' )
		);
		add_action( "load-{$settings_page}", array( $this, 'add_settings_page_tabs' ) );
	}

	/**
	 * Add tabs to the holiday settings page.
	 *
	 * @since 1.0.0
	 */
	public function add_holiday_settings_page_tabs() {
		$screen = get_current_screen();
		$screen->add_help_tab(
			array(
				'id'      => 'holiday-settings-help',
				'title'   => __( 'Overview', 'xo-event-calendar' ),
				'content' => '<p>' . __( 'This screen is used to manage the holiday.', 'xo-event-calendar' ) . '</p>',
			)
		);
	}

	/**
	 * Add tabs to the settings page.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page_tabs() {
		$screen = get_current_screen();
		$screen->add_help_tab(
			array(
				'id'      => 'option-settings-help',
				'title'   => __( 'Overview', 'xo-event-calendar' ),
				'content' => '<p>' . __( 'This screen is used to set options.', 'xo-event-calendar' ) . '</p>',
			)
		);
	}

	/**
	 * Sanitize the date list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $date_list The date list.
	 * @return array Sanitize the date list.
	 */
	private function sanitize_date_list( $date_list ) {
		$date_list = trim( $date_list );

		$datas = explode( "\n", $date_list );

		$result = '';
		foreach ( $datas as $data ) {
			$time = strtotime( $data );
			if ( $time ) {
				$result .= gmdate( 'Y-m-d', $time ) . "\n";
			}
		}
		return $result;
	}

	/**
	 * Display holiday setting page.
	 *
	 * @since 1.0.0
	 */
	public function holiday_settings_page() {
		if ( isset( $_REQUEST['action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
			if ( ! in_array( $action, array( 'new', 'select', 'delete', 'append', 'update' ), true ) ) {
				$action = 'select';
			}
		} else {
			$action = 'select';
		}

		$selected_name = isset( $_REQUEST['selected-name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['selected-name'] ) ) : null;

		$holiday_settings = get_option( 'xo_event_calendar_holiday_settings' );
		if ( ! is_array( $holiday_settings ) ) {
			$holiday_settings = array();
		}

		$messages = array();

		switch ( $action ) {
			case 'new':
				$selected_name = null;
				break;
			case 'select':
				if ( empty( $selected_name ) ) {
					$selected_name = key( array_slice( $holiday_settings, 0, 1 ) );
				}
				break;
			case 'delete':
				unset( $holiday_settings[ $selected_name ] );
				check_admin_referer( 'delete-history' );
				update_option( 'xo_event_calendar_holiday_settings', $holiday_settings );
				$messages[] = '<div id="message" class="updated notice is-dismissible"><p>' . __( 'The holiday item has been successfully deleted.', 'xo-event-calendar' ) . '</p></div>';
				if ( count( $holiday_settings ) >= 1 ) {
					reset( $holiday_settings );
					$selected_name = key( $holiday_settings );
				} else {
					$selected_name = null;
				}
				break;
			case 'append':
				$name = isset( $_REQUEST['name'] ) ? preg_replace( '/[^a-z0-9\-]/', '', strtolower( sanitize_text_field( wp_unslash( $_REQUEST['name'] ) ) ) ) : null;
				if ( empty( $name ) ) {
					$messages[] = '<div id="message" class="error notice is-dismissible"><p>' . __( 'Please enter a valid holiday name.', 'xo-event-calendar' ) . '</p></div>';
				} else {
					$holiday_settings[ $name ] = array(
						'title'           => __( 'Regular holiday', 'xo-event-calendar' ),
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
						'color'           => sprintf( '#%06x', wp_rand( 0x000000, 0xFFFFFF ) ),
					);

					$selected_name = $name;

					check_admin_referer( 'xo_event_calendar_holiday_settings' );
					update_option( 'xo_event_calendar_holiday_settings', $holiday_settings );
				}
				break;
			case 'update':
				$name            = isset( $_REQUEST['name'] ) ? preg_replace( '/[^a-z0-9\-]/', '', strtolower( sanitize_text_field( wp_unslash( $_REQUEST['name'] ) ) ) ) : null;
				$title           = isset( $_REQUEST['title'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['title'] ) ) : null;
				$dayofweek       = isset( $_REQUEST['dayofweek'] ) ? array_map( 'intval', $_REQUEST['dayofweek'] ) : array();
				$non_holiday     = isset( $_REQUEST['non-holiday'] ) ? $this->sanitize_date_list( sanitize_textarea_field( wp_unslash( $_REQUEST['non-holiday'] ) ) ) : null;
				$special_holiday = isset( $_REQUEST['special-holiday'] ) ? $this->sanitize_date_list( sanitize_textarea_field( wp_unslash( $_REQUEST['special-holiday'] ) ) ) : null;
				$color           = isset( $_REQUEST['color'] ) ? sanitize_hex_color( wp_unslash( $_REQUEST['color'] ) ) : null;

				if ( empty( $name ) ) {
					$messages[] = '<div id="message" class="error notice is-dismissible"><p>' . __( 'Please enter a valid holiday name.', 'xo-event-calendar' ) . '</p></div>';
				} else {
					if ( $selected_name !== $name ) {
						unset( $holiday_settings[ $selected_name ] );
						$selected_name = $name;
					}
					$holiday_settings[ $name ] = array(
						'title'           => $title,
						'dayofweek'       => array(
							'sun' => isset( $dayofweek['sun'] ),
							'mon' => isset( $dayofweek['mon'] ),
							'tue' => isset( $dayofweek['tue'] ),
							'wed' => isset( $dayofweek['wed'] ),
							'thu' => isset( $dayofweek['thu'] ),
							'fri' => isset( $dayofweek['fri'] ),
							'sat' => isset( $dayofweek['sat'] ),
						),
						'non_holiday'     => $non_holiday,
						'special_holiday' => $special_holiday,
						'color'           => $color,
					);
					check_admin_referer( 'xo_event_calendar_holiday_settings' );
					update_option( 'xo_event_calendar_holiday_settings', $holiday_settings );
					$messages[] = '<div id="message" class="updated notice is-dismissible"><p>' . __( 'Save Holiday', 'xo-event-calendar' ) . '</p></div>';
				}
				break;
		}

		if ( ! empty( $selected_name ) ) {
			$title           = isset( $holiday_settings[ $selected_name ]['title'] ) ? $holiday_settings[ $selected_name ]['title'] : null;
			$dayofweek       = isset( $holiday_settings[ $selected_name ]['dayofweek'] ) ? $holiday_settings[ $selected_name ]['dayofweek'] : array();
			$non_holiday     = isset( $holiday_settings[ $selected_name ]['non_holiday'] ) ? $holiday_settings[ $selected_name ]['non_holiday'] : null;
			$special_holiday = isset( $holiday_settings[ $selected_name ]['special_holiday'] ) ? $holiday_settings[ $selected_name ]['special_holiday'] : null;
			$color           = isset( $holiday_settings[ $selected_name ]['color'] ) ? $holiday_settings[ $selected_name ]['color'] : null;
		}
		$add_new = empty( $selected_name ) ? true : false;

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Holiday Settings', 'xo-event-calendar' ) . '</h1>';

		$allowed_html = array(
			'div' => array(
				'id'    => true,
				'class' => true,
			),
			'p'   => array(),
		);
		foreach ( $messages as $_message ) {
			echo wp_kses( $_message, $allowed_html );
		}
		?>
			<div id="xo-event-name">
				<?php if ( count( $holiday_settings ) < 2 ) : ?>
					<span class="add-edit-action">
					<?php
						printf(
							wp_kses(
								/* translators: %s: URL. */
								__( 'Edit your holiday below, or <a href="%s">create a new holiday</a>.', 'xo-event-calendar' ),
								array(
									'a' => array(
										'href'   => true,
										'target' => true,
										'rel'    => true,
									),
								)
							),
							esc_url( add_query_arg( array( 'action' => 'new' ) ) )
						);
					?>
					</span>
				<?php else : ?>
					<form method="get">
						<input type="hidden" name="post_type" value="<?php echo esc_attr( XO_Event_Calendar::get_post_type() ); ?>" />
						<input type="hidden" name="page" value="xo-event-holiday-settings" />
						<input type="hidden" name="action" value="select" >
						<label for="select-name-to-edit" class="select-name-label"><?php esc_html_e( 'Select a holiday to edit:', 'xo-event-calendar' ); ?></label>
						<select name="selected-name" id="select-name-to-edit">
						<?php if ( $add_new ) : ?>
							<option value="0" selected="selected"><?php esc_html_e( '&mdash; Select &mdash;', 'xo-event-calendar' ); ?></option>
						<?php endif; ?>
						<?php foreach ( (array) $holiday_settings as $key => $val ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $selected_name ); ?>>
								<?php echo esc_html( ! empty( $val['label'] ) ? $val['label'] : $key ); ?>
							</option>
						<?php endforeach; ?>
						</select>
						<span class="submit-btn"><input type="submit" class="button-secondary" value="<?php esc_attr_e( 'Select', 'xo-event-calendar' ); ?>"></span>
						<span class="add-new-name-action">
						<?php
							/* translators: %s: URL. */
							printf(
								wp_kses(
									/* translators: %s: URL. */
									__( 'or <a href="%s">create a new holiday</a>.', 'xo-event-calendar' ),
									array(
										'a' => array(
											'href'   => true,
											'target' => true,
											'rel'    => true,
										),
									)
								),
								esc_url( add_query_arg( array( 'action' => 'new' ) ) )
							);
						?>
						</span>
					</form>
				<?php endif; ?>
			</div>
			<div id="xo-event-holiday-setting">
				<form id="update-holiday" method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'xo_event_calendar_holiday_settings' ); ?>
					<input type="hidden" name="selected-name" value="<?php echo esc_attr( $selected_name ); ?>" />
					<input type="hidden" name="action" value="<?php echo $add_new ? 'append' : 'update'; ?>" />
					<div class="holiday-edit">
						<div id="xo-event-holiday-setting-header">
							<div class="major-publishing-actions">
								<label class="name-label" for="name">
									<span><?php esc_html_e( 'Holiday Name', 'xo-event-calendar' ); ?></span>
									<input name="name" id="name" type="text" class="regular-text name-input" title="<?php esc_attr_e( 'Enter holiday name here', 'xo-event-calendar' ); ?>" value="<?php echo esc_attr( $selected_name ); ?>" maxlength="20" />
								</label>
								<span class="name-description"><?php esc_html_e( '&#8251; Lower case letters, digits, and hyphens only', 'xo-event-calendar' ); ?></span>
								<div class="publishing-action">
								<?php submit_button( empty( $selected_name ) ? esc_html__( 'Create Holiday', 'xo-event-calendar' ) : esc_html__( 'Save Holiday', 'xo-event-calendar' ), 'button-primary', 'submit', false, array( 'id' => 'submit-holiday' ) ); ?>
								</div>
							</div>
						</div>
						<div id="xo-event-holiday-setting-body">
						<?php if ( $add_new ) : ?>
							<p class="holiday-body-plain"><?php esc_html_e( 'Give your holiday a name above, then click Create Holiday.', 'xo-event-calendar' ); ?></p>
						<?php else : ?>
							<h3><?php esc_html_e( 'Holiday item', 'xo-event-calendar' ); ?></h3>
							<dl>
								<dt><?php esc_html_e( 'Title', 'xo-event-calendar' ); ?></dt>
								<dd>
									<input name="title" id="title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" type="text">
								</dd>
							</dl>
							<dl>
								<dt><?php esc_html_e( 'Regular weekly', 'xo-event-calendar' ); ?></dt>
								<dd>
									<label for="dayofweek[sun]"><input type="checkbox" id="dayofweek[sun]" name="dayofweek[sun]" value="1"<?php checked( $dayofweek['sun'] ); ?>><?php esc_html_e( 'Sunday', 'xo-event-calendar' ); ?></label>
									<label for="dayofweek[mon]"><input type="checkbox" id="dayofweek[mon]" name="dayofweek[mon]" value="1"<?php checked( $dayofweek['mon'] ); ?>><?php esc_html_e( 'Monday', 'xo-event-calendar' ); ?></label>
									<label for="dayofweek[tue]"><input type="checkbox" id="dayofweek[tue]" name="dayofweek[tue]" value="1"<?php checked( $dayofweek['tue'] ); ?>><?php esc_html_e( 'Tuesday', 'xo-event-calendar' ); ?></label>
									<label for="dayofweek[wed]"><input type="checkbox" id="dayofweek[wed]" name="dayofweek[wed]" value="1"<?php checked( $dayofweek['wed'] ); ?>><?php esc_html_e( 'Wednesday', 'xo-event-calendar' ); ?></label>
									<label for="dayofweek[thu]"><input type="checkbox" id="dayofweek[thu]" name="dayofweek[thu]" value="1"<?php checked( $dayofweek['thu'] ); ?>><?php esc_html_e( 'Thursday', 'xo-event-calendar' ); ?></label>
									<label for="dayofweek[fri]"><input type="checkbox" id="dayofweek[fri]" name="dayofweek[fri]" value="1"<?php checked( $dayofweek['fri'] ); ?>><?php esc_html_e( 'Friday', 'xo-event-calendar' ); ?></label>
									<label for="dayofweek[sat]"><input type="checkbox" id="dayofweek[sat]" name="dayofweek[sat]" value="1"<?php checked( $dayofweek['sat'] ); ?>><?php esc_html_e( 'Saturday', 'xo-event-calendar' ); ?></label>
								</dd>
							</dl>
							<dl>
								<dt><?php esc_html_e( 'Extraordinary dates', 'xo-event-calendar' ); ?></dt>
								<dd>
									<textarea name="special-holiday" id="special-holiday" rows="6" cols="20"><?php echo esc_textarea( (string) $special_holiday ); ?></textarea>
									<p class="description"><?php esc_html_e( 'One date on one line.', 'xo-event-calendar' ); ?></p>
								</dd>
							</dl>
							<dl>
								<dt><?php esc_html_e( 'Cancel dates', 'xo-event-calendar' ); ?></dt>
								<dd>
									<textarea name="non-holiday" id="non-holiday" rows="6" cols="20"><?php echo esc_textarea( (string) $non_holiday ); ?></textarea>
									<p class="description"><?php esc_html_e( 'One date on one line.', 'xo-event-calendar' ); ?></p>
								</dd>
							</dl>
							<dl>
								<dt><?php esc_html_e( 'Color', 'xo-event-calendar' ); ?></dt>
								<dd>
									<input id="color" class="c-picker" type="text" name="color" value="<?php echo esc_html( $color ); ?>" />
								</dd>
							</dl>
						<?php endif; ?>
						</div>
						<div id="xo-event-holiday-setting-footer">
							<div class="major-publishing-actions">
							<?php if ( 0 !== count( $holiday_settings ) && ! $add_new ) : ?>
								<span class="delete-action">
									<?php
										$href = wp_nonce_url(
											add_query_arg(
												array(
													'action' => 'delete',
													'selected-name' => $selected_name,
													'page' => 'xo-event-holiday-settings',
													'post_type' => XO_Event_Calendar::get_post_type(),
												)
											),
											'delete-history'
										);
									?>
									<a class="submitdelete deletion" href="<?php echo esc_url( $href ); ?>" onclick="if(confirm('<?php echo esc_html( __( "You are about to permanently delete this holiday.\\n \'Cancel\' to stop, \'OK\' to delete.", 'xo-event-calendar' ) ); ?>')){ return true; } return false;"><?php esc_html_e( 'Delete Holiday', 'xo-event-calendar' ); ?></a>
								</span>
							<?php endif; ?>
								<div class="publishing-action">
								<?php submit_button( empty( $selected_name ) ? esc_html__( 'Create Holiday', 'xo-event-calendar' ) : esc_html__( 'Save Holiday', 'xo-event-calendar' ), 'button-primary', 'submit', false, array( 'id' => 'submit-holiday' ) ); ?>
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		<?php
	}

	/**
	 * Display settings page.
	 *
	 * @since 1.8.0
	 */
	public function settings_page() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Settings', 'xo-event-calendar' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ignoring since we are just displaying that the settings have been saved and not making  any other changes to the site.
		if ( isset( $_GET['settings-updated'] ) ) {
			echo '<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible">';
			echo '<p><strong>' . esc_html__( 'Settings saved.', 'xo-event-calendar' ) . '</strong></p>';
			echo '</div>';
		}

		echo '<div id="xo-event-settings">';
		echo '<form method="post" action="options.php">';
		settings_fields( 'xo_event_calendar_option_group' );
		do_settings_sections( 'xo_event_calendar_option_group' );
		submit_button();
		echo '</form>';
		echo '</div>';

		echo "</div>\n"; // <!-- .wrap -->
	}

	/**
	 * Register option settings.
	 *
	 * @since 1.8.0
	 */
	public function register_option_settings() {
		register_setting( 'xo_event_calendar_option_group', 'xo_event_calendar_options', array( $this, 'sanitize_option_settings' ) );

		add_settings_section( 'xo_event_calendar_option_generic_section', '', '__return_empty_string', 'xo_event_calendar_option_group' );
		add_settings_field( 'disable_calendar', __( 'Event calendar', 'xo-event-calendar' ), array( $this, 'field_calendar' ), 'xo_event_calendar_option_group', 'xo_event_calendar_option_generic_section' );
		add_settings_field( 'delete_settings', __( 'Processing when deleting plugin', 'xo-event-calendar' ), array( $this, 'field_delete_settings' ), 'xo_event_calendar_option_group', 'xo_event_calendar_option_generic_section' );
	}

	/**
	 * Register calendar field.
	 *
	 * @since 1.8.0
	 */
	public function field_calendar() {
		$disable_dashicons  = isset( $this->parent->options['disable_dashicons'] ) ? $this->parent->options['disable_dashicons'] : false;
		$disable_event_link = isset( $this->parent->options['disable_event_link'] ) ? $this->parent->options['disable_event_link'] : false;

		echo '<fieldset>';
		echo '<label for="disable_dashicons"><input id="disable_dashicons" name="xo_event_calendar_options[disable_dashicons]" type="checkbox" value="1" class="code" ' . checked( 1, $disable_dashicons, false ) . ' /> ' . esc_html__( 'Do not use Dashicons font', 'xo-event-calendar' ) . '</label>';
		echo '<br />';
		echo '<label for="disable_event_link"><input id="disable_event_link" name="xo_event_calendar_options[disable_event_link]" type="checkbox" value="1" class="code" ' . checked( 1, $disable_event_link, false ) . ' /> ' . esc_html__( 'Disable event link', 'xo-event-calendar' ) . '</label>';
		echo '</fieldset>';
	}

	/**
	 * Register delete data field.
	 *
	 * @since 3.1.0
	 */
	public function field_delete_settings() {
		$value = isset( $this->parent->options['delete_data'] ) ? $this->parent->options['delete_data'] : false;

		echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html__( 'Delete plugin data', 'xo-event-calendar' ) . '</span></legend>';
		echo '<label><input name="xo_event_calendar_options[delete_data]" type="checkbox" value="1" ' . checked( true, $value, false ) . '> ' . esc_html__( 'Delete plugin data', 'xo-event-calendar' ) . '</label><br>';
		echo '</fieldset>';
	}

	/**
	 * Sanitize option settings.
	 *
	 * @since 1.8.0
	 *
	 * @param array $input Input data.
	 */
	public function sanitize_option_settings( $input ) {
		$input['disable_dashicons']  = isset( $input['disable_dashicons'] );
		$input['disable_event_link'] = isset( $input['disable_event_link'] );
		$input['delete_data']        = isset( $input['delete_data'] );
		return $input;
	}
}
