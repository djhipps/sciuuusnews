<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate form data
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $rating = intval($_POST['rating']);
    $review = sanitize_textarea_field($_POST['review']);

    // Save data to your custom table (use $wpdb or any other database interaction method)
    // ...

    // Redirect back to the review page or show a success message
    wp_redirect(get_permalink(123)); // Replace 123 with your review page ID
    exit;
}