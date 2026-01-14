<?php

/*
Plugin Name: LD Fix User Access - Fixed Persistence
Description: Fixed persistence for users with fixed access.
Version: 1.0
Author: Obi Juan
Author URI: https://obijuan.dev
License: GPL2
*/

// Load course metabox
add_action( 'learndash_settings_sections_init', function() {
	if ( file_exists( __DIR__ . '/admin/single-course.php' ) ) {
		require_once __DIR__ . '/admin/single-course.php';
	}
} );

// ENQUEUE SELECT2
add_action('admin_enqueue_scripts', function($hook) {
    // Enqueue for the global users page.
    if ($hook === 'users_page_ld-fix-user-access') {
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
        return;
    }

    // Enqueue for course edit screen.
    global $post;
    if (($hook === 'post.php' || $hook === 'post-new.php') && $post && $post->post_type === 'sfwd-courses') {
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    }
});

// ADMIN MENU (unchanged)
add_action('admin_menu', function() {
    add_users_page('Fix LearnDash Access', 'Fix LD Access', 'manage_options', 'ld-fix-user-access', 'ld_fix_user_access_page_callback');
});

function ld_fix_user_access_page_callback() {
    // Save handler (unchanged)
    if (isset($_POST['ld_fix_user_access_nonce']) && wp_verify_nonce($_POST['ld_fix_user_access_nonce'], 'save_ld_fix_user_access')) {
        $selected = isset($_POST['fix_user_access']) && is_array($_POST['fix_user_access'])
            ? array_map('intval', $_POST['fix_user_access'])
            : [];
        update_option('ld_fix_user_access', $selected, false);
        echo '<div class="notice notice-success is-dismissible"><p><strong>Saved!</strong> Your changes have been saved.</p></div>';
    }

    $saved_users = (array) get_option('ld_fix_user_access', []);
    $saved_users = array_map('intval', $saved_users);

    $users = get_users([
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'number'  => 2000,
        'fields'  => ['ID', 'display_name', 'user_login'],
    ]);
    ?>
    <div class="wrap">
        <h1>Users with fixed access – Permanent LearnDash Access</h1>
        <p>Search/select users. Pills appear and stay visible in the list for easy editing. Selections now persist after save/reload.</p>

        <form method="post">
            <?php wp_nonce_field('save_ld_fix_user_access', 'ld_fix_user_access_nonce'); ?>

            <select name="fix_user_access[]" id="fix-user-access-select" multiple style="width:100%;">
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo esc_attr($user->ID); ?>"
                        <?php echo in_array($user->ID, $saved_users, true) ? 'selected' : ''; ?>>
                        <?php echo esc_html($user->display_name); ?> 
                        (<?php echo esc_html($user->user_login); ?> – ID: <?php echo $user->ID; ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <p class="submit">
                <input type="submit" class="button-primary" value="Save Users Access">
            </p>
        </form>

        <hr>
        <h3>Current users with access fixed (<?php echo count($saved_users); ?>)</h3>
        <?php if (!empty($saved_users)): ?>
            <ul style="columns: 3; column-gap: 2em; list-style: disc inside;">
                <?php foreach ($saved_users as $uid):
                    $u = get_user_by('id', $uid);
                    if ($u): ?>
                        <li><?php echo esc_html($u->display_name); ?> (ID: <?php echo $uid; ?>)</li>
                    <?php endif;
                endforeach; ?>
            </ul>
        <?php else: ?>
            <p><em>None selected yet.</em></p>
        <?php endif; ?>

        <style>
    .select2-container--default .select2-selection--multiple {
        min-height: 38px;                 /* single line height when few pills */
        max-height: 180px;                /* scroll after ~4-5 lines */
        overflow-y: auto;                 /* enable vertical scroll */
        border: 1px solid #8c8f94;
        padding: 4px;
        box-sizing: border-box;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: block;                   /* normal block for scrolling */
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        margin: 4px 4px 4px 0;
    }
    /* Make search field look like it's after pills */
    .select2-search--inline .select2-search__field {
        margin: 0 !important;
        padding: 0 4px !important;
        height: 28px !important;
        line-height: 28px !important;
    }
</style>

<script>
jQuery(document).ready(function($) {
    var $select = $('#fix-user-access-select');

    $select.select2({
        placeholder: "Search users by name, login or ID…",
        allowClear: true,
        closeOnSelect: false,
        width: '100%'
    });

    // Pre-select saved values
    var savedIds = <?php echo json_encode(array_map('strval', $saved_users)); ?>;
    if (savedIds.length > 0) {
        $select.val(savedIds).trigger('change');
    }

    // Helper: scroll pills container to bottom
    function scrollToBottom() {
        var $container = $select.next('.select2-container').find('.select2-selection--multiple');
        var $rendered  = $container.find('.select2-selection__rendered');
        if ($rendered.length) {
            $rendered.scrollTop($rendered[0].scrollHeight);
        }
    }

    // Scroll + focus events
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

    // On direct click/focus inside the field
    $select.next('.select2-container').on('focusin', '.select2-search__field', function() {
        setTimeout(scrollToBottom, 50);
    });

    // Initial scroll if many items already
    setTimeout(scrollToBottom, 150);
});
</script>
    </div>
    <?php
}

// ADD COLUMN TO COURSE LIST
add_filter('manage_sfwd-courses_posts_columns', function($columns) {
    // Insert the column after the title column.
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['fix_user_access_count'] = __('Fixed Access Users', 'fix-user-access-for-learndash');
        }
    }
    return $new_columns;
});

// POPULATE THE COLUMN
add_action('manage_sfwd-courses_posts_custom_column', function($column, $post_id) {
    if ($column === 'fix_user_access_count') {
        $course_users = get_post_meta($post_id, '_fix_user_access_users', true);
        $count = is_array($course_users) ? count($course_users) : 0;
        
        if ($count > 0) {
            echo '<span style="display: inline-block; background: #2271b1; color: #fff; padding: 3px 8px; border-radius: 3px; font-weight: 600;">' . esc_html($count) . '</span>';
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}, 10, 2);

// MAKE THE COLUMN SORTABLE
add_filter('manage_edit-sfwd-courses_sortable_columns', function($columns) {
    $columns['fix_user_access_count'] = 'fix_user_access_count';
    return $columns;
});

// HANDLE SORTING
add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if ($query->get('orderby') === 'fix_user_access_count') {
        $query->set('meta_key', '_fix_user_access_users');
        $query->set('orderby', 'meta_value_num');
    }
});

// AJAX HANDLER FOR COPYING FIXED ACCESS SETTINGS
add_action('wp_ajax_fix_user_access_copy_settings', function() {
    // Verify nonce.
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fix_user_access_copy_nonce')) {
        wp_send_json_error(__('Security check failed.', 'fix-user-access-for-learndash'));
    }

    // Check permissions.
    if (!current_user_can('edit_courses')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'fix-user-access-for-learndash'));
    }

    $from_course_id = isset($_POST['from_course_id']) ? intval($_POST['from_course_id']) : 0;
    $to_course_id = isset($_POST['to_course_id']) ? intval($_POST['to_course_id']) : 0;

    if (!$from_course_id || !$to_course_id) {
        wp_send_json_error(__('Invalid course IDs.', 'fix-user-access-for-learndash'));
    }

    // Verify both courses exist.
    if (get_post_type($from_course_id) !== 'sfwd-courses' || get_post_type($to_course_id) !== 'sfwd-courses') {
        wp_send_json_error(__('Invalid courses.', 'fix-user-access-for-learndash'));
    }

    // Get users from source course.
    $users = get_post_meta($from_course_id, '_fix_user_access_users', true);
    
    if (!is_array($users) || empty($users)) {
        wp_send_json_error(__('No users found in the source course.', 'fix-user-access-for-learndash'));
    }

    // Copy to destination course.
    update_post_meta($to_course_id, '_fix_user_access_users', $users);

    wp_send_json_success([
        'message' => sprintf(
            __('Successfully copied %d users to this course.', 'fix-user-access-for-learndash'),
            count($users)
        ),
    ]);
});

// FILTER - Check both global and course-specific fixed access
add_filter('sfwd_lms_has_access', function($has_access, $post_id, $user_id) {
    if (empty($user_id)) {
        $user_id = get_current_user_id();
    }
    
    // Check global fixed access first (highest priority).
    $global_fix_user_access = (array) get_option('ld_fix_user_access', []);
    if (in_array((int)$user_id, $global_fix_user_access, true)) {
        return true;
    }
    
    // Check course-specific fixed access.
    if (!empty($post_id)) {
        // Get the course ID - handle lessons, topics, quizzes that belong to a course.
        $course_id = $post_id;
        $post_type = get_post_type($post_id);
        
        // If it's a lesson, topic, or quiz, get the parent course.
        if (in_array($post_type, ['sfwd-lessons', 'sfwd-topic', 'sfwd-quiz'], true)) {
            $course_id = learndash_get_course_id($post_id);
        }
        
        if (!empty($course_id)) {
            $course_fix_users = get_post_meta($course_id, '_fix_user_access_users', true);
            if (is_array($course_fix_users) && in_array((int)$user_id, $course_fix_users, true)) {
                return true;
            }
        }
    }
    
    return $has_access;
}, 20, 3);