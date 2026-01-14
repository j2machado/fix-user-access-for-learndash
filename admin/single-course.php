<?php
/**
 * LearnDash Settings Metabox for Fix User Access.
 *
 * @since 1.0.0
 *
 * @package FixUserAccessForLearnDash
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	class_exists( 'LearnDash_Settings_Metabox' )
	&& ! class_exists( 'LearnDash_Settings_Metabox_Course_Fix_User_Access' )
) {
	/**
	 * Class LearnDash Settings Metabox for Fix User Access.
	 *
	 * @since 1.0.0
	 */
	class LearnDash_Settings_Metabox_Course_Fix_User_Access extends LearnDash_Settings_Metabox {
		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			// What screen ID are we showing on.
			$this->settings_screen_id = LDLMS_Post_Types::get_post_type_slug( LDLMS_Post_Types::COURSE );

			// Used within the Settings API to uniquely identify this section.
			$this->settings_metabox_key = 'learndash-course-fix-user-access';

			// Section label/header.
			$this->settings_section_label = esc_html__( 'Fix User Access', 'fix-user-access-for-learndash' );

			// Description shown below the section label.
			$this->settings_section_description = esc_html__( 'Manage fixed user access settings for this course.', 'fix-user-access-for-learndash' );

			// Display as a tab instead of a metabox.
			$this->settings_tab_priority = 60;

			$this->settings_fields_map = [];

			parent::__construct();
		}

		/**
		 * Initializes the metabox settings fields.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function load_settings_fields() {
			$course_id = get_the_ID();
			$saved_users = get_post_meta( $course_id, '_fix_user_access_users', true );
			$saved_users = is_array( $saved_users ) ? array_map( 'intval', $saved_users ) : [];

			$this->setting_option_fields = [
				'fix_user_access_copy' => [
					'name'             => 'fix_user_access_copy',
					'label'            => esc_html__( 'Copy Fixed Access Settings', 'fix-user-access-for-learndash' ),
					'type'             => 'custom',
					'value'            => null,
					'display_callback' => function () use ( $course_id ): void {
						// Get all courses except the current one.
						$courses = get_posts( [
							'post_type'      => 'sfwd-courses',
							'posts_per_page' => -1,
							'post_status'    => 'any',
							'exclude'        => [ $course_id ],
							'orderby'        => 'title',
							'order'          => 'ASC',
						] );

						// Filter courses that have fixed access users.
						$courses_with_access = [];
						foreach ( $courses as $course ) {
							$users = get_post_meta( $course->ID, '_fix_user_access_users', true );
							if ( is_array( $users ) && ! empty( $users ) ) {
								$courses_with_access[] = [
									'id'    => $course->ID,
									'title' => $course->post_title,
									'count' => count( $users ),
								];
							}
						}

						if ( empty( $courses_with_access ) ) {
							echo '<p><em>' . esc_html__( 'No other courses have fixed access users configured.', 'fix-user-access-for-learndash' ) . '</em></p>';
							return;
						}
						?>
						<div class="fix-user-access-copy-wrapper">
							<p class="description">
								<?php esc_html_e( 'Copy fixed access user settings from another course. This will replace the current settings.', 'fix-user-access-for-learndash' ); ?>
							</p>
							<select name="fix_user_access_copy_from" id="fix-user-access-copy-from" style="max-width: 400px;">
								<option value=""><?php esc_html_e( '— Select a course —', 'fix-user-access-for-learndash' ); ?></option>
								<?php foreach ( $courses_with_access as $course_data ) : ?>
									<option value="<?php echo esc_attr( $course_data['id'] ); ?>">
										<?php echo esc_html( $course_data['title'] ); ?> 
										(<?php echo esc_html( sprintf( _n( '%d user', '%d users', $course_data['count'], 'fix-user-access-for-learndash' ), $course_data['count'] ) ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<button type="button" id="fix-user-access-copy-btn" class="button" style="margin-left: 10px;">
								<?php esc_html_e( 'Copy Settings', 'fix-user-access-for-learndash' ); ?>
							</button>
							<span id="fix-user-access-copy-status" style="margin-left: 10px; display: none;"></span>

							<script>
							jQuery(document).ready(function($) {
								$('#fix-user-access-copy-btn').on('click', function() {
									var fromCourseId = $('#fix-user-access-copy-from').val();
									if (!fromCourseId) {
										alert('<?php esc_attr_e( 'Please select a course to copy from.', 'fix-user-access-for-learndash' ); ?>');
										return;
									}

									if (!confirm('<?php esc_attr_e( 'This will replace the current fixed access users. Are you sure?', 'fix-user-access-for-learndash' ); ?>')) {
										return;
									}

									var $btn = $(this);
									var $status = $('#fix-user-access-copy-status');
									$btn.prop('disabled', true).text('<?php esc_attr_e( 'Copying...', 'fix-user-access-for-learndash' ); ?>');

									$.ajax({
										url: ajaxurl,
										type: 'POST',
										data: {
											action: 'fix_user_access_copy_settings',
											from_course_id: fromCourseId,
											to_course_id: <?php echo (int) $course_id; ?>,
											nonce: '<?php echo esc_js( wp_create_nonce( 'fix_user_access_copy_nonce' ) ); ?>'
										},
										success: function(response) {
											if (response.success) {
												$status.css('color', 'green').text('<?php esc_attr_e( 'Settings copied! Reloading...', 'fix-user-access-for-learndash' ); ?>').show();
												setTimeout(function() {
													location.reload();
												}, 1000);
											} else {
												$status.css('color', 'red').text(response.data || '<?php esc_attr_e( 'Error copying settings.', 'fix-user-access-for-learndash' ); ?>').show();
												$btn.prop('disabled', false).text('<?php esc_attr_e( 'Copy Settings', 'fix-user-access-for-learndash' ); ?>');
											}
										},
										error: function() {
											$status.css('color', 'red').text('<?php esc_attr_e( 'Error copying settings.', 'fix-user-access-for-learndash' ); ?>').show();
											$btn.prop('disabled', false).text('<?php esc_attr_e( 'Copy Settings', 'fix-user-access-for-learndash' ); ?>');
										}
									});
								});
							});
							</script>
						</div>
						<?php
					},
				],
				'fix_user_access_users' => [
					'name'             => 'fix_user_access_users',
					'label'            => esc_html__( 'Users with Fixed Access', 'fix-user-access-for-learndash' ),
					'type'             => 'custom',
					'value'            => null,
					'display_callback' => function () use ( $saved_users, $course_id ): void {
						$users = get_users( [
							'orderby' => 'display_name',
							'order'   => 'ASC',
							'number'  => 2000,
							'fields'  => [ 'ID', 'display_name', 'user_login' ],
						] );

						// Get global fixed access users for reference.
						$global_fixed_users = (array) get_option( 'ld_fix_user_access', [] );
						$global_fixed_users = array_map( 'intval', $global_fixed_users );

						?>
						<div class="fix-user-access-course-wrapper">
							<p class="description">
								<?php esc_html_e( 'Select users who should have permanent access to this course. This supplements the global fixed access settings.', 'fix-user-access-for-learndash' ); ?>
							</p>

							<select name="fix_user_access_course_users[]" id="fix-user-access-course-select" multiple style="width:100%;">
								<?php foreach ( $users as $user ) : ?>
									<option value="<?php echo esc_attr( $user->ID ); ?>"
										<?php echo in_array( $user->ID, $saved_users, true ) ? 'selected' : ''; ?>>
										<?php echo esc_html( $user->display_name ); ?> 
										(<?php echo esc_html( $user->user_login ); ?> – ID: <?php echo esc_attr( $user->ID ); ?>)
									</option>
								<?php endforeach; ?>
							</select>

							<div class="fix-user-access-summary" style="margin-top: 20px;">
								<h4><?php esc_html_e( 'Current Fixed Access Users for This Course', 'fix-user-access-for-learndash' ); ?> (<?php echo count( $saved_users ); ?>)</h4>
								<?php if ( ! empty( $saved_users ) ) : ?>
									<ul style="columns: 2; column-gap: 2em; list-style: disc inside;">
										<?php
										foreach ( $saved_users as $uid ) :
											$u = get_user_by( 'id', $uid );
											if ( $u ) :
												?>
												<li><?php echo esc_html( $u->display_name ); ?> (ID: <?php echo esc_attr( $uid ); ?>)</li>
											<?php endif; ?>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<p><em><?php esc_html_e( 'No course-specific fixed access users selected yet.', 'fix-user-access-for-learndash' ); ?></em></p>
								<?php endif; ?>

								<?php if ( ! empty( $global_fixed_users ) ) : ?>
									<p style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
										<strong><?php esc_html_e( 'Note:', 'fix-user-access-for-learndash' ); ?></strong>
										<?php
										printf(
											// translators: %d is the number of users.
											esc_html( _n( '%d user has global fixed access to all courses.', '%d users have global fixed access to all courses.', count( $global_fixed_users ), 'fix-user-access-for-learndash' ) ),
											count( $global_fixed_users )
										);
										?>
									</p>
								<?php endif; ?>
							</div>

							<style>
								.fix-user-access-course-wrapper .select2-container--default .select2-selection--multiple {
									min-height: 38px;
									max-height: 180px;
									overflow-y: auto;
									border: 1px solid #8c8f94;
									padding: 4px;
									box-sizing: border-box;
								}
								.fix-user-access-course-wrapper .select2-container--default .select2-selection--multiple .select2-selection__rendered {
									display: block;
								}
								.fix-user-access-course-wrapper .select2-container--default .select2-selection--multiple .select2-selection__choice {
									margin: 4px 4px 4px 0;
								}
								.fix-user-access-course-wrapper .select2-search--inline .select2-search__field {
									margin: 0 !important;
									padding: 0 4px !important;
									height: 28px !important;
									line-height: 28px !important;
								}
							</style>

							<script>
							jQuery(document).ready(function($) {
								var $select = $('#fix-user-access-course-select');

								if ($select.length && typeof $.fn.select2 !== 'undefined') {
									$select.select2({
										placeholder: "<?php esc_attr_e( 'Search users by name, login or ID…', 'fix-user-access-for-learndash' ); ?>",
										allowClear: true,
										closeOnSelect: false,
										width: '100%'
									});

									// Pre-select saved values.
									var savedIds = <?php echo wp_json_encode( array_map( 'strval', $saved_users ) ); ?>;
									if (savedIds.length > 0) {
										$select.val(savedIds).trigger('change');
									}

									// Helper: scroll pills container to bottom.
									function scrollToBottom() {
										var $container = $select.next('.select2-container').find('.select2-selection--multiple');
										var $rendered  = $container.find('.select2-selection__rendered');
										if ($rendered.length) {
											$rendered.scrollTop($rendered[0].scrollHeight);
										}
									}

									// Scroll + focus events.
									$select.on('select2:open', function() {
										setTimeout(function() {
											scrollToBottom();
											$select.next().find('.select2-search__field').focus();
										}, 50);
									});

									$select.on('select2:select select2:unselect', function() {
										setTimeout(function() {
											scrollToBottom();
											$select.next().find('.select2-search__field').focus();
										}, 50);
									});

									// On direct click/focus inside the field.
									$select.next('.select2-container').on('focusin', '.select2-search__field', function() {
										setTimeout(scrollToBottom, 50);
									});

									// Initial scroll if many items already.
									setTimeout(scrollToBottom, 150);
								}
							});
							</script>
						</div>
						<?php
					},
				],
			];

			/** This filter is documented in includes/settings/settings-metaboxes/class-ld-settings-metabox-course-access-settings.php */
			$this->setting_option_fields = apply_filters( 'learndash_settings_fields', $this->setting_option_fields, $this->settings_metabox_key );

			parent::load_settings_fields();
		}

		/**
		 * Save Settings Metabox
		 *
		 * @since 1.0.0
		 *
		 * @param int          $post_id                Post ID being saved.
		 * @param WP_Post|null $saved_post             WP_Post object being saved.
		 * @param bool|null    $update                 If update true, otherwise false.
		 * @param mixed[]|null $settings_field_updates array of settings fields to update.
		 *
		 * @return void
		 */
		public function save_post_meta_box( $post_id = 0, $saved_post = null, $update = null, $settings_field_updates = null ) {
			if (
				! $post_id
				|| ! $saved_post
			) {
				return;
			}

			// Verify nonce.
			if (
				! isset( $_POST['learndash-course-fix-user-access']['nonce'] )
				|| ! wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['learndash-course-fix-user-access']['nonce'] ) ),
					'learndash-course-fix-user-access'
				)
			) {
				return;
			}

			// Save course-specific fixed access users.
			if ( isset( $_POST['fix_user_access_course_users'] ) && is_array( $_POST['fix_user_access_course_users'] ) ) {
				$user_ids = array_map( 'intval', $_POST['fix_user_access_course_users'] );
				update_post_meta( $post_id, '_fix_user_access_users', $user_ids );
			} else {
				// If no users selected, delete the meta.
				delete_post_meta( $post_id, '_fix_user_access_users' );
			}
		}
	}

	add_filter(
		'learndash_post_settings_metaboxes_init_' . learndash_get_post_type_slug( LDLMS_Post_Types::COURSE ),
		function ( $metaboxes = [] ) {
			if (
				! isset( $metaboxes['LearnDash_Settings_Metabox_Course_Fix_User_Access'] )
				&& class_exists( 'LearnDash_Settings_Metabox_Course_Fix_User_Access' )
			) {
				$metaboxes['LearnDash_Settings_Metabox_Course_Fix_User_Access'] = LearnDash_Settings_Metabox_Course_Fix_User_Access::add_metabox_instance();
			}

			return $metaboxes;
		},
		50
	);

	/**
	 * Add Fix User Access tab to course edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @param array $header_data Header data array.
	 *
	 * @return array Modified header data.
	 */
	add_filter(
		'learndash_header_data',
		function ( $header_data ) {
			$screen = get_current_screen();

			// Only add tab on course edit screen.
			if ( ! $screen || $screen->id !== learndash_get_post_type_slug( LDLMS_Post_Types::COURSE ) ) {
				return $header_data;
			}

			// Only add if we're editing a post (not on the list view).
			if ( ! isset( $header_data['tabs'] ) || empty( $header_data['tabs'] ) ) {
				return $header_data;
			}

			// Find the position to insert our tab (after Extend Access, before Settings).
			$new_tab = [
				'id'                  => 'learndash_course_fix_user_access',
				'name'                => esc_html__( 'Fix User Access', 'fix-user-access-for-learndash' ),
				'metaboxes'           => [ 'learndash-course-fix-user-access' ],
				'showDocumentSidebar' => 'false',
			];

			// Insert the tab after "Extend Access" tab.
			$tabs        = $header_data['tabs'];
			$insert_pos  = 0;
			$found_index = false;

			foreach ( $tabs as $index => $tab ) {
				if ( isset( $tab['id'] ) && $tab['id'] === 'learndash_course_access_extending' ) {
					$insert_pos  = $index + 1;
					$found_index = true;
					break;
				}
			}

			// If we didn't find the Extend Access tab, insert before Settings tab.
			if ( ! $found_index ) {
				foreach ( $tabs as $index => $tab ) {
					if ( isset( $tab['id'] ) && $tab['id'] === 'sfwd-courses-settings' ) {
						$insert_pos = $index;
						break;
					}
				}
			}

			// Insert the new tab at the calculated position.
			array_splice( $tabs, $insert_pos, 0, [ $new_tab ] );
			$header_data['tabs'] = $tabs;

			return $header_data;
		},
		10
	);
}
