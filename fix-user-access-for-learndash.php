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

// ENQUEUE SELECT2 (unchanged)
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'users_page_ld-fix-user-access') return;

    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
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

// FILTER (unchanged)
add_filter('sfwd_lms_has_access', function($has_access, $post_id, $user_id) {
    if (empty($user_id)) {
        $user_id = get_current_user_id();
    }
    $fix_user_access = (array) get_option('ld_fix_user_access', []);
    if (in_array((int)$user_id, $fix_user_access, true)) {
        return true;
    }
    return $has_access;
}, 20, 3);