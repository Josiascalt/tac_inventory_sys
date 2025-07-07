<?php
/**
 * USER AVATAR SHORTCODE
 * ---------------------
 * PURPOSE: Provides a shortcode [current_user_avatar] to display
 * the logged-in user's avatar.
 * It accepts an optional 'size' attribute, e.g., [current_user_avatar size="150"]
 */

add_shortcode('current_user_avatar', 'render_current_user_avatar_shortcode');

function render_current_user_avatar_shortcode($atts) {
    // Set up default attributes
    $atts = shortcode_atts(
        array(
            'size' => '50', // Default size in pixels
        ),
        $atts,
        'current_user_avatar'
    );

    // Get the ID of the currently logged-in user
    $user_id = get_current_user_id();

    // If no user is logged in, return nothing.
    if ($user_id == 0) {
        return '';
    }

    // Use the built-in WordPress function to get the complete <img> tag for the avatar
    $avatar_html = get_avatar($user_id, $atts['size']);

    // Return the HTML
    return $avatar_html;
}