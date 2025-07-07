<?php
/**
 * HIDE ADMIN BAR FOR AUDITORS SNIPPET
 * ------------------------------------
 * PURPOSE: Hides the top WordPress admin bar for any user with the 'Auditor' role.
 * This does not affect Administrators or other user roles.
 * TYPE: PHP
 */

add_action('after_setup_theme', 'hide_admin_bar_for_auditors');

function hide_admin_bar_for_auditors() {
    // Wait until the user is fully logged in and initialized
    if (!is_user_logged_in()) {
        return;
    }

    // Check if the current user has the specific role of 'auditor'
    $user = wp_get_current_user();
    if (in_array('auditor', (array) $user->roles)) {
        // If they do, turn off the admin bar
        add_filter('show_admin_bar', '__return_false');
    }
}
