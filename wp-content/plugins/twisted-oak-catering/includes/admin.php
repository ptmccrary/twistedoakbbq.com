<?php
/**
 * wp-admin: booking status/schedule box, list filters + bulk status actions,
 * month calendar, and the Availability settings page (Catering menu).
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/* ---------- Shared admin CSS ---------- */

add_action(
	'admin_head',
	static function (): void {
		$screen = get_current_screen();
		if ( ! $screen || TWISTEDOAK_CATERING_CPT !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			.tobbq-status{display:inline-block;padding:2px 8px;border-radius:2px;font-size:12px;line-height:1.6;background:#dcdcde;color:#1d2327;white-space:nowrap}
			.tobbq-status--held{background:#fcf0c3;color:#614a00}
			.tobbq-status--confirmed{background:#c6e1c6;color:#1e4620}
			.tobbq-status--completed{background:#c5d9ed;color:#123a5c}
			.tobbq-status--declined,.tobbq-status--cancelled{background:#f2d1d1;color:#6b1414;text-decoration:line-through}
			.tobbq-cal{table-layout:fixed;width:100%;border-collapse:collapse;background:#fff}
			.tobbq-cal th{padding:6px;text-align:left;background:#f6f7f7;border:1px solid #dcdcde}
			.tobbq-cal td{vertical-align:top;height:110px;padding:4px 6px;border:1px solid #dcdcde}
			.tobbq-cal td.is-other{background:#fafafa;color:#a7aaad}
			.tobbq-cal td.is-today{box-shadow:inset 0 0 0 2px #2271b1}
			.tobbq-cal td.d-closed,.tobbq-cal td.d-past{background:#f6f7f7}
			.tobbq-cal td.d-blackout{background:repeating-linear-gradient(45deg,#f6f7f7,#f6f7f7 6px,#ececec 6px,#ececec 12px)}
			.tobbq-cal td.d-full{background:#fbeaea}
			.tobbq-cal td.d-limited{background:#fff8e5}
			.tobbq-cal td.d-available{background:#f0f8f0}
			.tobbq-cal__head{display:flex;justify-content:space-between;gap:4px;font-size:12px}
			.tobbq-cal__num{font-weight:600;font-size:13px}
			.tobbq-cal__day-status{color:#50575e}
			.tobbq-cal ul{margin:4px 0 0;padding:0;list-style:none}
			.tobbq-cal li{margin:0 0 3px;font-size:12px;line-height:1.35}
			.tobbq-cal li a{text-decoration:none}
			.tobbq-cal-nav{display:flex;align-items:center;gap:8px;margin:12px 0}
			.tobbq-cal-legend{display:flex;flex-wrap:wrap;gap:12px;margin:12px 0;font-size:12px}
			.tobbq-cal-legend span{display:inline-block;width:14px;height:14px;vertical-align:middle;margin-right:4px;border:1px solid #dcdcde}
			.tobbq-booking-box label{display:block;font-weight:600;margin:8px 0 2px}
			.tobbq-booking-box input,.tobbq-booking-box select{width:100%}
			.tobbq-booking-box .tobbq-row{display:flex;gap:8px}
			.tobbq-booking-box .tobbq-row>div{flex:1}
			.tobbq-schedule textarea{font-family:monospace}
			.tobbq-schedule .tobbq-weekday-row td{vertical-align:top}
		</style>
		<?php
	}
);

/* ---------- Booking edit box ---------- */

add_action(
	'add_meta_boxes_' . TWISTEDOAK_CATERING_CPT,
	static function (): void {
		add_meta_box( 'tobbq-catering-booking', __( 'Booking status & schedule', 'twisted-oak-catering' ), 'twistedoak_catering_render_booking_box', TWISTEDOAK_CATERING_CPT, 'side', 'high' );
	},
	20
);

function twistedoak_catering_render_booking_box( WP_Post $post ): void {
	$b = twistedoak_catering_booking( $post->ID );
	wp_nonce_field( 'tobbq_catering_booking_' . $post->ID, 'tobbq_catering_booking_nonce' );
	if ( 'publish' === $post->post_status && twistedoak_catering_booking_conflicts( $post->ID ) && in_array( $b['status'], twistedoak_catering_schedule()['consuming_statuses'], true ) ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Heads up: this booking overlaps a full day or slot (overbooked) or falls on a closed day.', 'twisted-oak-catering' ) . '</p></div>';
	}
	?>
	<div class="tobbq-booking-box">
		<input type="hidden" name="post_status" value="publish">
		<p><strong><?php echo esc_html( (string) $b['reference'] ); ?></strong> <span class="tobbq-status tobbq-status--<?php echo esc_attr( $b['status'] ); ?>"><?php echo esc_html( twistedoak_catering_lead_value( $post->ID, 'status' ) ); ?></span></p>

		<label for="tobbq-status"><?php esc_html_e( 'Status', 'twisted-oak-catering' ); ?></label>
		<select id="tobbq-status" name="tobbq_booking[status]">
			<?php foreach ( twistedoak_catering_statuses() as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $b['status'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>

		<label for="tobbq-date"><?php esc_html_e( 'Event date', 'twisted-oak-catering' ); ?></label>
		<input type="date" id="tobbq-date" name="tobbq_booking[event_date]" value="<?php echo esc_attr( (string) $b['event_date'] ); ?>">

		<div class="tobbq-row">
			<div>
				<label for="tobbq-time"><?php esc_html_e( 'Start', 'twisted-oak-catering' ); ?></label>
				<input type="time" id="tobbq-time" name="tobbq_booking[start_time]" value="<?php echo esc_attr( (string) $b['start_time'] ); ?>" step="900">
			</div>
			<div>
				<label for="tobbq-duration"><?php esc_html_e( 'Window (min)', 'twisted-oak-catering' ); ?></label>
				<input type="number" id="tobbq-duration" name="tobbq_booking[duration]" value="<?php echo esc_attr( (string) $b['duration'] ); ?>" min="0" max="720" step="15">
			</div>
		</div>

		<div class="tobbq-row">
			<div>
				<label for="tobbq-guests"><?php esc_html_e( 'Guests', 'twisted-oak-catering' ); ?></label>
				<input type="number" id="tobbq-guests" name="tobbq_booking[guests]" value="<?php echo esc_attr( (string) $b['guests'] ); ?>" min="0" max="10000">
			</div>
			<div>
				<label for="tobbq-style"><?php esc_html_e( 'Service style', 'twisted-oak-catering' ); ?></label>
				<select id="tobbq-style" name="tobbq_booking[service_style]">
					<option value=""><?php esc_html_e( '—', 'twisted-oak-catering' ); ?></option>
					<?php foreach ( array_unique( array_filter( array_merge( twistedoak_catering_service_styles(), array( (string) $b['service_style'] ) ) ) ) as $style ) : ?>
						<option value="<?php echo esc_attr( $style ); ?>" <?php selected( $b['service_style'], $style ); ?>><?php echo esc_html( $style ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<label for="tobbq-package"><?php esc_html_e( 'Package', 'twisted-oak-catering' ); ?></label>
		<select id="tobbq-package" name="tobbq_booking[package]">
			<?php foreach ( array_unique( array_filter( array_merge( twistedoak_catering_packages(), array( (string) $b['package'] ) ) ) ) as $pkg ) : ?>
				<option value="<?php echo esc_attr( $pkg ); ?>" <?php selected( $b['package'], $pkg ); ?>><?php echo esc_html( $pkg ); ?></option>
			<?php endforeach; ?>
		</select>

		<label for="tobbq-note"><?php esc_html_e( 'Note to customer (optional)', 'twisted-oak-catering' ); ?></label>
		<textarea id="tobbq-note" name="tobbq_booking[note]" rows="3" style="width:100%"></textarea>

		<p><label style="font-weight:400"><input type="checkbox" name="tobbq_booking[notify]" value="1" checked style="width:auto"> <?php esc_html_e( 'Email customer when confirmed / declined / cancelled', 'twisted-oak-catering' ); ?></label></p>
		<p><button type="submit" class="button button-primary" name="save" value="save"><?php esc_html_e( 'Update booking', 'twisted-oak-catering' ); ?></button></p>
		<?php
		$log = get_post_meta( $post->ID, '_tobbq_lead_status_log', true );
		if ( is_array( $log ) && $log ) :
			?>
			<details><summary><?php esc_html_e( 'History', 'twisted-oak-catering' ); ?></summary><ul>
				<?php foreach ( array_reverse( $log ) as $entry ) : ?>
					<li>
						<?php
						$user = ! empty( $entry['user'] ) ? get_userdata( (int) $entry['user'] ) : false;
						echo esc_html(
							wp_date( 'M j, g:i a', (int) $entry['time'] ) . ' — ' . ( twistedoak_catering_statuses()[ $entry['to'] ] ?? $entry['to'] )
							. ( $user ? ' (' . $user->display_name . ')' : '' )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul></details>
		<?php endif; ?>
	</div>
	<?php
}

add_action(
	'save_post_' . TWISTEDOAK_CATERING_CPT,
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['tobbq_catering_booking_nonce'], $_POST['tobbq_booking'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tobbq_catering_booking_nonce'] ) ), 'tobbq_catering_booking_' . $post_id )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| wp_is_post_revision( $post_id )
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}
		$in = wp_unslash( $_POST['tobbq_booking'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$in = is_array( $in ) ? $in : array();

		$date = sanitize_text_field( (string) ( $in['event_date'] ?? '' ) );
		update_post_meta( $post_id, '_tobbq_lead_event_date', twistedoak_catering_is_date( $date ) ? $date : '' );
		update_post_meta( $post_id, '_tobbq_lead_start_time', twistedoak_catering_normalise_time( sanitize_text_field( (string) ( $in['start_time'] ?? '' ) ) ) );
		update_post_meta( $post_id, '_tobbq_lead_duration', (string) min( 720, absint( $in['duration'] ?? 0 ) ) );
		update_post_meta( $post_id, '_tobbq_lead_guests', (string) min( 10000, absint( $in['guests'] ?? 0 ) ) );
		update_post_meta( $post_id, '_tobbq_lead_service_style', mb_substr( sanitize_text_field( (string) ( $in['service_style'] ?? '' ) ), 0, 80 ) );
		update_post_meta( $post_id, '_tobbq_lead_package', mb_substr( sanitize_text_field( (string) ( $in['package'] ?? '' ) ), 0, 200 ) );

		$status = sanitize_key( (string) ( $in['status'] ?? '' ) );
		if ( array_key_exists( $status, twistedoak_catering_statuses() ) ) {
			twistedoak_catering_set_status( $post_id, $status, ! empty( $in['notify'] ), sanitize_textarea_field( (string) ( $in['note'] ?? '' ) ) );
		}
	}
);

/* ---------- List table: status filter + bulk status actions ---------- */

add_action(
	'restrict_manage_posts',
	static function ( string $post_type ): void {
		if ( TWISTEDOAK_CATERING_CPT !== $post_type ) {
			return;
		}
		$current = isset( $_GET['tobbq_status'] ) ? sanitize_key( wp_unslash( $_GET['tobbq_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<label class="screen-reader-text" for="tobbq-status-filter">' . esc_html__( 'Filter by status', 'twisted-oak-catering' ) . '</label>';
		echo '<select name="tobbq_status" id="tobbq-status-filter"><option value="">' . esc_html__( 'All statuses', 'twisted-oak-catering' ) . '</option>';
		foreach ( twistedoak_catering_statuses() as $slug => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $current, $slug, false ), esc_html( $label ) );
		}
		echo '</select>';
	}
);

add_action(
	'pre_get_posts',
	static function ( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || TWISTEDOAK_CATERING_CPT !== $query->get( 'post_type' ) ) {
			return;
		}
		$status = isset( $_GET['tobbq_status'] ) ? sanitize_key( wp_unslash( $_GET['tobbq_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $status && array_key_exists( $status, twistedoak_catering_statuses() ) ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => '_tobbq_lead_status',
						'value' => $status,
					),
				)
			);
		}
	}
);

add_filter(
	'bulk_actions-edit-' . TWISTEDOAK_CATERING_CPT,
	static function ( array $actions ): array {
		foreach ( twistedoak_catering_statuses() as $slug => $label ) {
			/* translators: %s: status label */
			$actions[ 'tobbq_status_' . $slug ] = sprintf( __( 'Mark as %s', 'twisted-oak-catering' ), $label );
		}
		return $actions;
	},
	20
);

add_filter(
	'handle_bulk_actions-edit-' . TWISTEDOAK_CATERING_CPT,
	static function ( string $redirect, string $action, array $ids ): string {
		if ( ! str_starts_with( $action, 'tobbq_status_' ) ) {
			return $redirect;
		}
		// Core has already verified the bulk-posts nonce.
		$status  = substr( $action, 13 );
		$changed = 0;
		foreach ( array_map( 'intval', $ids ) as $id ) {
			if ( current_user_can( 'edit_post', $id ) && twistedoak_catering_set_status( $id, $status, true ) ) {
				++$changed;
			}
		}
		return add_query_arg( 'tobbq_status_changed', $changed, $redirect );
	},
	10,
	3
);

add_action(
	'admin_notices',
	static function (): void {
		if ( ! isset( $_GET['tobbq_status_changed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$n = absint( $_GET['tobbq_status_changed'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		/* translators: %d: count */
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( '%d booking updated.', '%d bookings updated.', $n, 'twisted-oak-catering' ), $n ) ) );
	}
);

/* ---------- Menu: Calendar + Availability ---------- */

add_action(
	'admin_menu',
	static function (): void {
		$parent = 'edit.php?post_type=' . TWISTEDOAK_CATERING_CPT;
		add_submenu_page( $parent, __( 'Catering Calendar', 'twisted-oak-catering' ), __( 'Calendar', 'twisted-oak-catering' ), 'edit_posts', 'twistedoak-catering-calendar', 'twistedoak_catering_render_calendar_page', 1 );
		add_submenu_page( $parent, __( 'Catering Availability', 'twisted-oak-catering' ), __( 'Availability', 'twisted-oak-catering' ), 'manage_options', 'twistedoak-catering-availability', 'twistedoak_catering_render_availability_page', 2 );
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'twistedoak_catering_schedule',
			TWISTEDOAK_CATERING_SCHEDULE_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'twistedoak_catering_sanitize_schedule',
				'default'           => twistedoak_catering_schedule_defaults(),
			)
		);
	}
);

/**
 * Month grid of bookings + day availability.
 */
function twistedoak_catering_render_calendar_page(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$first = preg_match( '/^\d{4}-\d{2}$/', $month ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', wp_timezone() ) : false;
	$first = $first ? $first : twistedoak_catering_today()->modify( 'first day of this month' );
	$last  = $first->modify( 'last day of this month' );

	$start_of_week = 1; // Monday-first grid.
	$lead          = ( (int) $first->format( 'N' ) - $start_of_week + 7 ) % 7;
	$grid_start    = $first->modify( '-' . $lead . ' days' );
	$trail         = ( 7 - ( ( $lead + (int) $last->format( 'j' ) ) % 7 ) ) % 7;
	$grid_end      = $last->modify( '+' . $trail . ' days' );

	$all_statuses = array_keys( twistedoak_catering_statuses() );
	$bookings     = twistedoak_catering_bookings_between( $grid_start->format( 'Y-m-d' ), $grid_end->format( 'Y-m-d' ), $all_statuses );
	$public       = array();
	foreach ( twistedoak_catering_availability( $grid_start->format( 'Y-m-d' ), $grid_end->format( 'Y-m-d' ) ) as $d ) {
		$public[ $d['date'] ] = $d;
	}
	$labels = twistedoak_catering_public_config( false )['dayStatuses'];
	$today  = twistedoak_catering_today()->format( 'Y-m-d' );
	$base   = admin_url( 'edit.php?post_type=' . TWISTEDOAK_CATERING_CPT . '&page=twistedoak-catering-calendar' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Catering Calendar', 'twisted-oak-catering' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TWISTEDOAK_CATERING_CPT . '&page=twistedoak-catering-availability' ) ); ?>"><?php esc_html_e( 'Availability rules', 'twisted-oak-catering' ); ?></a>
		<hr class="wp-header-end">

		<nav class="tobbq-cal-nav" aria-label="<?php esc_attr_e( 'Month', 'twisted-oak-catering' ); ?>">
			<a class="button" href="<?php echo esc_url( add_query_arg( 'month', $first->modify( '-1 month' )->format( 'Y-m' ), $base ) ); ?>">&larr; <?php echo esc_html( wp_date( 'F', $first->modify( '-1 month' )->getTimestamp() ) ); ?></a>
			<h2 style="margin:0 8px"><?php echo esc_html( wp_date( 'F Y', $first->getTimestamp() ) ); ?></h2>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'month', $first->modify( '+1 month' )->format( 'Y-m' ), $base ) ); ?>"><?php echo esc_html( wp_date( 'F', $first->modify( '+1 month' )->getTimestamp() ) ); ?> &rarr;</a>
			<a class="button-link" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Today', 'twisted-oak-catering' ); ?></a>
		</nav>

		<table class="tobbq-cal">
			<thead><tr>
				<?php for ( $i = 0; $i < 7; $i++ ) : ?>
					<th scope="col"><?php echo esc_html( wp_date( 'D', $grid_start->modify( '+' . $i . ' days' )->getTimestamp() ) ); ?></th>
				<?php endfor; ?>
			</tr></thead>
			<tbody>
			<?php
			$cursor = $grid_start;
			while ( $cursor <= $grid_end ) :
				echo '<tr>';
				for ( $i = 0; $i < 7; $i++, $cursor = $cursor->modify( '+1 day' ) ) :
					$ymd     = $cursor->format( 'Y-m-d' );
					$day     = $public[ $ymd ] ?? null;
					$dstatus = $day ? $day['status'] : 'closed';
					$classes = array( 'd-' . $dstatus );
					if ( $cursor->format( 'm' ) !== $first->format( 'm' ) ) {
						$classes[] = 'is-other';
					}
					if ( $ymd === $today ) {
						$classes[] = 'is-today';
					}
					?>
					<td class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
						<div class="tobbq-cal__head">
							<span class="tobbq-cal__num"><?php echo esc_html( $cursor->format( 'j' ) ); ?></span>
							<span class="tobbq-cal__day-status">
								<?php
								echo esc_html( $labels[ $dstatus ] ?? $dstatus );
								if ( $day && in_array( $dstatus, array( 'available', 'limited', 'full' ), true ) ) {
									echo esc_html( ' · ' . ( $day['capacity'] - $day['remaining'] ) . '/' . $day['capacity'] );
								}
								?>
							</span>
						</div>
						<?php
						$rules = twistedoak_catering_day_rules( $ymd, true );
						$note  = trim( ( $day['note'] ?? '' ) . ' ' . $rules['internal'] );
						if ( '' !== $note ) :
							?>
							<div class="tobbq-cal__day-status"><em><?php echo esc_html( $note ); ?></em></div>
						<?php endif; ?>
						<?php if ( ! empty( $bookings[ $ymd ] ) ) : ?>
							<ul>
								<?php
								$list = $bookings[ $ymd ];
								usort( $list, static fn( array $a, array $b ): int => strcmp( $a['start'], $b['start'] ) );
								foreach ( $list as $bk ) :
									$name = (string) get_post_meta( $bk['id'], '_tobbq_lead_name', true );
									?>
									<li>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $bk['id'] ) ); ?>">
											<span class="tobbq-status tobbq-status--<?php echo esc_attr( '' !== $bk['status'] ? $bk['status'] : 'pending' ); ?>"><?php echo esc_html( '' !== $bk['start'] ? twistedoak_catering_format_time( $bk['start'] ) : __( 'TBD', 'twisted-oak-catering' ) ); ?></span>
											<?php echo esc_html( $name . ( $bk['guests'] ? ' · ' . $bk['guests'] : '' ) ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
					<?php
				endfor;
				echo '</tr>';
			endwhile;
			?>
			</tbody>
		</table>

		<div class="tobbq-cal-legend">
			<?php foreach ( array( 'available', 'limited', 'full', 'blackout', 'closed' ) as $st ) : ?>
				<div><span class="tobbq-cal d-<?php echo esc_attr( $st ); ?>" style="background:<?php echo esc_attr( array( 'available' => '#f0f8f0', 'limited' => '#fff8e5', 'full' => '#fbeaea', 'blackout' => '#ececec', 'closed' => '#f6f7f7' )[ $st ] ); ?>"></span><?php echo esc_html( $labels[ $st ] ); ?></div>
			<?php endforeach; ?>
			<div><?php esc_html_e( 'Day status is what customers see today (lead time and horizon applied). n/m = capacity used.', 'twisted-oak-catering' ); ?></div>
		</div>
	</div>
	<?php
}

/**
 * Availability rules form.
 */
function twistedoak_catering_render_availability_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s    = twistedoak_catering_schedule();
	$name = TWISTEDOAK_CATERING_SCHEDULE_OPTION;
	$num  = static function ( string $key, string $label, string $help, int $min = 0, int $max = 1000 ) use ( $s, $name ): void {
		printf(
			'<tr><th scope="row"><label for="tobbq-%1$s">%2$s</label></th><td><input type="number" class="small-text" id="tobbq-%1$s" name="%3$s[%1$s]" value="%4$d" min="%5$d" max="%6$d"> <p class="description">%7$s</p></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $name ),
			(int) $s[ $key ],
			(int) $min,
			(int) $max,
			esc_html( $help )
		);
	};
	$blackouts = implode(
		"\n",
		array_map( static fn( array $b ): string => trim( $b['start'] . ( $b['end'] !== $b['start'] ? '..' . $b['end'] : '' ) . ' ' . $b['label'] ), $s['blackouts'] )
	);
	$overrides = array();
	foreach ( $s['overrides'] as $date => $o ) {
		$line = $date . ( 'custom' !== $o['mode'] ? ' ' . $o['mode'] : '' );
		if ( null !== $o['capacity'] ) {
			$line .= ' capacity=' . $o['capacity'];
		}
		if ( $o['slots'] ) {
			$line .= ' slots=' . implode( ',', array_map( static fn( array $sl ): string => $sl['time'] . '/' . $sl['duration'], $o['slots'] ) );
		}
		$overrides[] = trim( $line . ' ' . $o['note'] );
	}
	?>
	<div class="wrap tobbq-schedule">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><?php esc_html_e( 'These rules drive the date/time picker on the catering form and the public availability API. Times use the site timezone:', 'twisted-oak-catering' ); ?> <code><?php echo esc_html( wp_timezone_string() ); ?></code></p>
		<form action="options.php" method="post">
			<?php settings_fields( 'twistedoak_catering_schedule' ); ?>

			<h2><?php esc_html_e( 'Weekly schedule', 'twisted-oak-catering' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Tick the bookable days. Start times: one per line as "HH:MM minutes", e.g. "11:00 180" = 11 am start, 3-hour service window.', 'twisted-oak-catering' ); ?></p>
			<table class="form-table" role="presentation"><tbody>
				<?php for ( $d = 1; $d <= 7; $d++ ) : ?>
					<?php $dn = wp_date( 'l', strtotime( 'monday this week +' . ( $d - 1 ) . ' days' ) ); ?>
					<tr class="tobbq-weekday-row">
						<th scope="row"><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[weekdays][]" value="<?php echo (int) $d; ?>" <?php checked( in_array( $d, $s['weekdays'], true ) ); ?>> <?php echo esc_html( $dn ); ?></label></th>
						<td><textarea name="<?php echo esc_attr( $name ); ?>[slots][<?php echo (int) $d; ?>]" rows="2" cols="24" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: weekday */ __( '%s start times', 'twisted-oak-catering' ), $dn ) ); ?>"><?php echo esc_textarea( implode( "\n", array_map( static fn( array $sl ): string => $sl['time'] . ' ' . $sl['duration'], $s['slots'][ $d ] ?? array() ) ) ); ?></textarea></td>
					</tr>
				<?php endfor; ?>
			</tbody></table>

			<h2><?php esc_html_e( 'Capacity & booking window', 'twisted-oak-catering' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php
				$num( 'capacity', __( 'Max events per day', 'twisted-oak-catering' ), __( '0 closes every day.', 'twisted-oak-catering' ), 0, 50 );
				$num( 'slot_capacity', __( 'Max events per time slot', 'twisted-oak-catering' ), __( 'Bookings whose service windows overlap a slot count against it.', 'twisted-oak-catering' ), 1, 50 );
				$num( 'max_guests_per_day', __( 'Max guests per day', 'twisted-oak-catering' ), __( 'Total across all events that day. 0 = no limit.', 'twisted-oak-catering' ), 0, 100000 );
				$num( 'min_guests', __( 'Minimum guests per booking', 'twisted-oak-catering' ), '', 1, 10000 );
				$num( 'max_guests', __( 'Maximum guests per booking', 'twisted-oak-catering' ), '', 1, 10000 );
				$num( 'lead_days', __( 'Minimum lead time (days)', 'twisted-oak-catering' ), __( 'Earliest bookable date = today + this many days.', 'twisted-oak-catering' ), 0, 365 );
				$num( 'horizon_months', __( 'Booking horizon (months)', 'twisted-oak-catering' ), __( 'How far ahead customers can book.', 'twisted-oak-catering' ), 1, 36 );
				$num( 'limited_threshold', __( '"Limited" threshold', 'twisted-oak-catering' ), __( 'A partly booked day shows as limited when this many events or fewer remain.', 'twisted-oak-catering' ), 0, 50 );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Statuses that use capacity', 'twisted-oak-catering' ); ?></th>
					<td>
						<?php foreach ( twistedoak_catering_statuses() as $slug => $label ) : ?>
							<label style="margin-right:12px"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[consuming_statuses][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $s['consuming_statuses'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-hold', 'twisted-oak-catering' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[auto_hold]" value="1" <?php checked( $s['auto_hold'] ); ?>> <?php esc_html_e( 'New web requests for an available slot start as "Tentative hold" (blocks the slot) instead of "Pending".', 'twisted-oak-catering' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="tobbq-styles"><?php esc_html_e( 'Service styles', 'twisted-oak-catering' ); ?></label></th>
					<td><textarea id="tobbq-styles" name="<?php echo esc_attr( $name ); ?>[service_styles]" rows="4" class="regular-text"><?php echo esc_textarea( implode( "\n", $s['service_styles'] ) ); ?></textarea><p class="description"><?php esc_html_e( 'One per line.', 'twisted-oak-catering' ); ?></p></td>
				</tr>
			</tbody></table>

			<h2><?php esc_html_e( 'Blackout dates', 'twisted-oak-catering' ); ?></h2>
			<p class="description"><?php esc_html_e( 'One per line: "2026-11-26 Thanksgiving" or a range "2026-12-24..2026-12-27 Holiday break".', 'twisted-oak-catering' ); ?></p>
			<textarea name="<?php echo esc_attr( $name ); ?>[blackouts]" rows="6" class="large-text" aria-label="<?php esc_attr_e( 'Blackout dates', 'twisted-oak-catering' ); ?>"><?php echo esc_textarea( $blackouts ); ?></textarea>

			<h2><?php esc_html_e( 'Date overrides', 'twisted-oak-catering' ); ?></h2>
			<p class="description"><?php esc_html_e( 'One per line. "2026-10-21 open" opens a normally closed day (overrides blackouts). "2026-10-23 closed Private party" closes a day. "2026-10-31 capacity=3" changes that day\'s capacity. Optional "slots=11:00/180,17:00/240" sets custom start times. Anything else on the line is an internal note.', 'twisted-oak-catering' ); ?></p>
			<textarea name="<?php echo esc_attr( $name ); ?>[overrides]" rows="6" class="large-text" aria-label="<?php esc_attr_e( 'Date overrides', 'twisted-oak-catering' ); ?>"><?php echo esc_textarea( implode( "\n", $overrides ) ); ?></textarea>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
