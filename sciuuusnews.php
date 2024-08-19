<?php
/**
 * @package SciuuusNewsPlugin
 */

/*
Plugin Name: Sciuuus News
Plugin URI: https://test-sciuuuskids.com
Description: Plugin to do custom News on website.
Version: 1.1
Author: Damian Hippisley
License: GPLv2 or later
Text Domain: hellowp
*/

// Constant variable defined by wp.
defined('ABSPATH') or die('No access for you!');

class SciuuusNewsForm {

    public function __construct() {
        // User the constructor to make things happen when the plugin is loaded.

        // Add settings.
        add_action('admin_init', array($this, 'sciuuus_news_register_settings'));
        add_action('admin_menu', array($this, 'sciuuus_news_settings_page'));

        add_action('admin_post_download_sciuuus_news_subscriptions', array($this, 'download_sciuuus_news_subscriptions'));
        
        // Create custom post types.
        add_action('init', array($this, 'create_custom_post_type_sciuuus_news'));

        // Add assets on slug page only.
        add_action('wp_enqueue_scripts', array($this, 'load_assets'));

        // Creates short code.
        add_shortcode('sciuuus-news-form', array($this, 'sciuuus_news_form_shortcode'));

        // Handles form in shortcode.
        add_action('template_redirect', array($this, 'handle_sciuuus_news_form_submission'));

        // Add filter.
        add_filter('manage_sciuuus_news_posts_columns', array($this, 'custom_sciuuus_news_columns'));
        add_action('manage_sciuuus_news_posts_custom_column', array($this, 'fill_sciuuus_news_columns'), 10, 2);


        // Register the unsubscribe endpoint.
        add_action('init', array($this, 'add_unsubscribe_rewrite_rule'));

        // Handle the unsubscribe request.
        add_action('template_redirect', array($this, 'handle_unsubscribe_request'));

        // Add the query var for unsubscribe.
        add_filter('query_vars', array($this, 'add_unsubscribe_query_var'));

    }


    public function sciuuus_news_register_settings() {
        register_setting('sciuuus_news_options_group', 'sciuuus_news_turnstile_site_key');
        register_setting('sciuuus_news_options_group', 'sciuuus_news_turnstile_secret_key');
    }

    public function sciuuus_news_settings_page() {
        //add_options_page('Newsletter Settings', 'Sciuuus Turnstile', 'manage_options', 'sciuuus-news-settings', array($this, 'sciuuus_news_settings_page_html'));
        add_submenu_page(
                'edit.php?post_type=sciuuus_news', // Parent slug
                'Sciuuus News Settings', // Page title
                'Settings', // Menu title
                'manage_options', // Capability
                'sciuuus-news-settings', // Menu slug
                array($this, 'sciuuus_news_settings_page_html') // Callback function
        );

        add_submenu_page(
                'edit.php?post_type=sciuuus_news', // Parent slug
                'Sciuuus Newsletter Subscriptions Downloads', // Page title
                'Download Subscriptions', // Menu title
                'manage_options', // Capability
                'sciuuus-news-download-settings', // Menu slug (changed)
                array($this, 'sciuuus_news_download_settings_page_html') // Callback function
        );
    }

     public function sciuuus_news_settings_page_html() {
        ?>
        <div class="wrap">
            <h1>Sciuuus Newsletter Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('sciuuus_news_options_group');
                do_settings_sections('sciuuus_news_options_group');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Turnstile Site Key</th>
                        <td><input type="text" name="sciuuus_news_turnstile_site_key" value="<?php echo esc_attr(get_option('sciuuus_news_turnstile_site_key')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Turnstile Secret Key</th>
                        <td><input type="text" name="sciuuus_news_turnstile_secret_key" value="<?php echo esc_attr(get_option('sciuuus_news_turnstile_secret_key')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function sciuuus_news_download_settings_page_html() {
        ?>
        <div class="wrap">
            <h1>Sciuuus Newsletter Subscriptions Downloads</h1>
            <p>Click the button below to download the list of subscriptions.</p>
            <p><a href="<?php echo esc_url(admin_url('admin-post.php?action=download_sciuuus_news_subscriptions')); ?>" class="button">Download Subscriptions</a></p>
        </div>
        <?php
    }

    public function create_custom_post_type_sciuuus_news() {
        // How to find out which arguments are necessary? Is there an API docs sheet?
        // https://developer.wordpress.org/reference/functions/register_post_type/
        $args =  array(
                'public' => true,
                'has_archive' => true,
                'supports' => array('title', 'editor', 'custom-fields'),
                'exclude_from_search' => true,
                'publicly_queryable' => false,
                'capabilities' => array(
                        'edit_post' => 'edit_submissions', // Example capability for editing posts
                        'read_post' => 'read_submissions', // Example capability for reading posts
                        'delete_post' => 'delete_submissions', // Capability for deleting posts
                        'create_posts' => 'do_not_allow',
                ),
                'labels' => array(
                        'name' => 'Sciuus Newsletter',
                        'singular_name' => 'Sciuuus Newsletter',
                ),
                'menu_icon' => 'dashicons-media-text',
                'map_meta_cap' => true,
        );
        register_post_type('sciuuus_news', $args);
    }

    function fill_sciuuus_news_columns($column, $post_id) {
        switch ($column) {
            case 'firstname':
                echo esc_html(get_post_meta($post_id, 'firstname', true));
                break;
            case 'lastname':
                echo esc_html(get_the_title($post_id));
                break;
            case 'email':
                echo esc_html(get_post_meta($post_id, 'email', true));
                break;
            case 'post_status':
                $post_status = get_post_status($post_id);
                echo ucfirst($post_status);
                break;
        }
    }

    function custom_sciuuus_news_columns($columns) {
        $new_columns = array(
                'cb' => $columns['cb'],
                'firstname' => __('First Name', 'news-plugin'),
                'lastname' => __('Last Name', 'news-plugin'),
                'email' => __('Email', 'news-plugin'),
                'post_status' => __('Status', 'news-plugin')
        );
        return $new_columns;
    }


    public function load_assets() {
        global $post;

        // Check if the current post contains the shortcode.
        if (isset($post->post_content) && has_shortcode($post->post_content, 'sciuuus-news-form')) {
            wp_enqueue_style(
                        'bootstrap-css',
                        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                        array(), // No dependencies
                        '5.3.3'
                );
                // Add integrity and crossorigin attributes
                $integrity = 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH';
                $crossorigin = 'anonymous';
                wp_style_add_data('bootstrap-css', 'integrity', $integrity);
                wp_style_add_data('bootstrap-css', 'crossorigin', $crossorigin);

                wp_enqueue_style(
                        'simple-contact-form',
                        plugin_dir_url(__FILE__) . 'css/sciuuus-news-form.css',
                        array(),
                        1,
                        'all'
                );
        }
    }

    /**
     * Creates the form for the short code.
     *
     * @return false|string
     */
    public function sciuuus_news_form_shortcode() {
        ob_start();

        if (isset($_GET['submitted']) && $_GET['submitted'] == 'true') {
            echo '<p>Grazie per la tua iscrizione!</p>';
        } else {
            $turnstile_site_key = get_option('sciuuus_news_turnstile_site_key');
            ?>
            <div class="sciuuus-news-form">
                <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" id="sciuuus-news-form__form">
                    <div class="row g-3">
                        <div class="form-group col-md-6">
                            <label for="firstnameinput" class="form-label">Nome</label>
                            <input name="firstname" type="text" class="form-control" id="firstnameinput">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="lastnameinput" class="form-label">Cognome</label>
                            <input name="lastname" type="text" class="form-control" id="lastnameinput">
                        </div>
                        <div class="form-group col-12">
                            <label for="inputEmail4" class="form-label">Email</label>
                            <input name="email" type="email" class="form-control" id="inputEmail4">
                        </div>
                        <input type="hidden" name="sciuuus_news_form_nonce"
                               value="<?php echo wp_create_nonce('sciuuus_news_form_nonce'); ?>">
                        <!-- Cloudflare Turnstile widget -->
                        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($turnstile_site_key); ?>"></div>
                        <div class="form-group col-12">
                            <button type="submit" name="sciuuus_news_form_submit" class="btn btn-primary">Invia</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Cloudflare Turnstile script -->
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <?php
        }

        return ob_get_clean();
    }


    /**
     * Submits data to back end and sends email.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     *
     */
    public function handle_sciuuus_news_form_submission(){

        if (isset($_POST['sciuuus_news_form_submit'])) {
            // Check nonce
            if (!isset($_POST['sciuuus_news_form_nonce']) || !wp_verify_nonce($_POST['sciuuus_news_form_nonce'], 'sciuuus_news_form_nonce')) {
                die('Il controllo di sicurezza non è riuscito');
            }

            // Sanitize and validate form data
            $firstname = sanitize_text_field($_POST['firstname']);
            $lastname = sanitize_text_field($_POST['lastname']);
            $email = sanitize_email($_POST['email']);

            // Insert the post
            $postarr = array(
                    'post_title'   => $lastname,
                    'post_content' => 'Email: ' . $email,
                    'meta_input' => [
                            'email' => $email,
                            'firstname' => $firstname
                    ],
                    'post_status'  => 'publish',
                    'post_type'    => 'sciuuus_news', // Change this to your desired post type
            );

            $post_id = wp_insert_post($postarr);

            // Check if the post was created
            if ($post_id) {
                // Send email.
                $this->send_subscription_email([
                        'nome' => $firstname,
                        'cognome' => $lastname,
                        'email' =>   $email
                ]);

                // Redirect or display a success message
                wp_redirect(add_query_arg('submitted', 'true', get_permalink()));
                exit;
            } else {
                // Handle error
                echo 'Si è verificato un errore durante l\'invio del modulo. Per favore riprova.';
            }
        }
    }


    public function send_subscription_email($params) {
        // Get site values.
        $admin_email = get_bloginfo('admin_email');
        $admin_name = get_bloginfo('name');
        $noreplyemailaddress = 'no-reply@sciuuuskids.com';

        // Create unsubscribe link
        $unsubscribe_url = add_query_arg([
                'action' => 'unsubscribe',
                'email' => urlencode($params['email'])
        ], home_url('/sciuuusnewsletter/unsubscribe/'));

        // Set up headers.
        // Set up headers.
        $headers = [
                "From: {$admin_name} <{$noreplyemailaddress}>",
                "Reply-To: {$noreplyemailaddress}",
                "Content-Type: text/html; charset=UTF-8",
                "List-Unsubscribe: <{$unsubscribe_url}>"
        ];

        // Structure message and subject for email.
        $subject = "Iscrizione alla Newsletter Sciuuus Kids";
        $message = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body {
                        font-family: Quicksand, sans-serif;
                        margin: 0;
                        padding: 20px;
                    }
                    .email-container {
                        max-width: 600px;
                        color: #000000;
                        margin: auto;
                        padding: 20px;
                        border: 1px solid #ddd;
                        border-radius: 10px;
                        background-color: #f9f9f9;
                    }
                    .logo {
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    .content {
                        text-align: left;
                    }
                    .footer {
                        margin-top: 20px;
                        text-align: center;
                        font-size: 12px;
                    }
                </style>
            </head>
            <body>
                <div class="email-container">';

        $message = "<div class=\"logo\"><img src=\"https://sciuuuskids.it/wp-content/uploads/2024/07/logo-500-email.jpg\" alt=\"Sciuuus Logo\" width=\"220\"></div>";
        $message .= "<p>Ciao {$params['nome']}! Siamo contenti che anche tu abbia deciso di ricevere le nostre newsletter.</p>";
        $message .= "<p>Ti aggiorneremo sulle novità del nostro assortimento, sugli sconti e sul meraviglioso mondo delle scarpe barefoot.</p>";
        $message .= "<ul style=\"text-decoration: none;\">";
        foreach ($params as $label => $value) {
            $message .= '<li>
                         <strong>' . ucfirst(esc_html($label)) . ':</strong> ' . nl2br(esc_html($value)) .
                        '</li>';
        }
        $message .= "</ul>";
        $message .= "<p>A presto</p>";
        $message .= "<p>Damian e Rossella</p><br>";

        $message .= "<p>Utilizza questo link per annullare l'iscrizione: <a href='{$unsubscribe_url}'>Annulla iscrizione</a>.</p>";
        $message .= "</div></body></html>";


        // Send email and error check.
        if (!wp_mail($params['email'], $subject, $message, $headers)) {
            return 'Messaggio inviato, ma l\'email non è riuscita a inviare.';
        }

        return 'Messaggio inviato con successo!';
    }

    public function add_unsubscribe_rewrite_rule() {
        add_rewrite_rule('sciuuusnewsletter/unsubscribe/?$', 'index.php?sciuuusnewsletter_unsubscribe=1', 'top');
    }

    public function handle_unsubscribe_request() {
        if (get_query_var('sciuuusnewsletter_unsubscribe')) {
            $this->handle_unsubscribe();
        }
    }

    public function add_unsubscribe_query_var($query_vars) {
        $query_vars[] = 'sciuuusnewsletter_unsubscribe';
        return $query_vars;
    }

    public function handle_unsubscribe() {
        if (isset($_GET['action']) && $_GET['action'] === 'unsubscribe' && isset($_GET['email'])) {
            $email = sanitize_email($_GET['email']);

            // Query to find the post with the given email
            $args = [
                    'post_type' => 'sciuuus_news',
                    'meta_query' => [
                            [
                                    'key' => 'email',
                                    'value' => $email,
                                    'compare' => '='
                            ]
                    ]
            ];

            $posts = get_posts($args);

            if (!empty($posts)) {
                foreach ($posts as $post) {
                    wp_delete_post($post->ID, true);
                }
                echo 'Hai annullato l\'iscrizione con successo.';
            } else {
                echo 'L\'indirizzo email non è stato trovato.';
            }
        } else {
            echo 'Richiesta non valida.';
        }
        exit;
    }



    public function download_sciuuus_news_subscriptions() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        // Query to retrieve posts of 'sciuuus_news' type
        $args = array(
                'post_type' => 'sciuuus_news',
                'posts_per_page' => -1, // Get all posts
        );

        $posts = get_posts($args);

        // Prepare CSV content
        $csv_data = array();
        $csv_data[] = array('First Name', 'Last Name', 'Email Address');

        foreach ($posts as $post) {
            $first_name = get_post_meta($post->ID, 'firstname', true);
            $last_name = get_the_title($post->ID);
            $email = get_post_meta($post->ID, 'email', true);

            $csv_data[] = array($first_name, $last_name, $email);
        }

        // Output CSV headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sciuuus_news_subscriptions.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Write CSV data
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }

        // Close the stream
        fclose($output);

        exit;
    }


}

new SciuuusNewsForm();
