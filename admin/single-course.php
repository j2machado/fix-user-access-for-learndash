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
			$this->setting_option_fields = [
				'fix_user_access_placeholder' => [
					'name'      => 'fix_user_access_placeholder',
					'label'     => esc_html__( 'Status', 'fix-user-access-for-learndash' ),
					'type'      => 'text',
					'value'     => '',
					'default'   => '',
					'help_text' => esc_html__( 'Custom logic will be added here soon.', 'fix-user-access-for-learndash' ),
					'attrs'     => [
						'readonly' => 'readonly',
						'placeholder' => esc_html__( 'Coming soon...', 'fix-user-access-for-learndash' ),
					],
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

			// Custom saving logic will be added here later.
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
