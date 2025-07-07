
/**
 * CUSTOM LOGIN PAGE STYLER SNIPPET
 * --------------------------------
 * PURPOSE: Adds custom branding and styles to the default WordPress login page.
 * TYPE: PHP
 */

add_action('login_enqueue_scripts', 'customize_my_login_page');

function customize_my_login_page() {
    /**
     * CUSTOMIZATION INSTRUCTIONS:
     * 1. Change the logo URL in the CSS below to point to your college's logo.
     * You can upload your logo to the WordPress Media Library and copy its URL.
     * 2. Adjust the colors (like #007cba) and fonts to match your brand.
     * 3. For the background, you can use a solid color or uncomment the 'background-image' line
     * and provide a URL to a background image.
     */
    ?>
    <style type="text/css">
        body.login {
            background-color: inherit; 
        }

        #login h1 a, .login h1 a {
            /* --- IMPORTANT: Change this URL to your logo --- */
            background-image: url('https://s3.us-east-005.dream.io/invpics/items/2025/07/logo_inventory_sys-scaled.png'); 
            height: 100px; /* Adjust to your logo's height */
            width: 300px; /* Adjust to your logo's width */
            background-size: contain;
            background-repeat: no-repeat;
            padding-bottom: 30px;
        }

		#login {
			padding: 25px !important;
			margin-top: 5% !important;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
        }


        .login label {
            font-weight: 600;
            color: #444;
        }

        .login input[type="text"],
        .login input[type="password"] {
            border-radius: 4px;
            border-color: #ccc;
            box-shadow: none;
        }

        .login input[type="text"]:focus,
        .login input[type="password"]:focus {
            border-color: #007cba; /* A highlight color, change to your brand's primary color */
            box-shadow: 0 0 0 1px #007cba;
        }

        .wp-core-ui .button-primary {
            background: #007cba; /* Your brand's primary color */
            border-color: #007cba;
            border-radius: 4px;
            text-shadow: none;
            transition: background-color 0.2s ease-in-out;
        }

        .wp-core-ui .button-primary:hover {
            background: #005a87; /* A darker shade for hover */
            border-color: #005a87;
        }

        .login #nav,
        .login #backtoblog {
            padding: 16px 0;
        }

        .login #nav a,
        .login #backtoblog a {
            color: #555;
            transition: color 0.2s ease-in-out;
        }

        .login #nav a:hover,
        .login #backtoblog a:hover {
            color: #007cba; /* Your brand's primary color */
        }
    </style>
    <?php
}

// These two filters change the logo's destination URL and title text.
// This makes the logo link to your site's homepage instead of wordpress.org.
add_filter('login_headerurl', 'my_login_logo_url');
function my_login_logo_url() {
    return home_url();
}

add_filter('login_headertext', 'my_login_logo_url_title');
function my_login_logo_url_title() {
    return get_bloginfo('name');
}
