<?php
/**
 * Plugin Name: Speaker Directory System
 * Description: Clean, Secure implementation of Speaker Directory with Live AJAX Filter and Booking Form.
 * Version: 3.0
 * Author: Xyron Technologies
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// 1. REGISTER CUSTOM POST TYPES SECURELY
add_action( 'init', 'xyron_safe_register_post_types' );
function xyron_safe_register_post_types() {
    // Register Speakers
    register_post_type( 'speaker', array(
        'labels' => array(
            'name'               => esc_html__( 'Speakers' ),
            'singular_name'      => esc_html__( 'Speaker' ),
            'add_new_item'       => esc_html__( 'Add New Speaker' ),
            'edit_item'          => esc_html__( 'Edit Speaker' ),
        ),
        'public'             => true,
        'has_archive'        => true,
        'supports'           => array( 'title', 'thumbnail' ),
        'show_in_rest'       => true,
    ));
    
    // Register Bookings Table/CPT
    register_post_type( 'speaker_booking', array(
        'labels' => array(
            'name'               => esc_html__( 'Bookings' ),
            'singular_name'      => esc_html__( 'Booking' ),
        ),
        'public'             => false,
        'show_ui'            => true,
        'supports'           => array( 'title', 'editor' ),
        'menu_icon'          => 'dashicons-calendar-alt',
    ));
}

// 2. ADD CUSTOM META BOXES FOR JOB TITLE & ORGANIZATION
add_action( 'add_meta_boxes', 'xyron_safe_add_meta_boxes' );
function xyron_safe_add_meta_boxes() {
    add_meta_box( 'speaker_details_box', esc_html__( 'Speaker Details' ), 'xyron_safe_meta_box_html', 'speaker', 'normal', 'high' );
}

function xyron_safe_meta_box_html( $post ) {
    $job_title = get_post_meta( $post->ID, '_speaker_job_title', true );
    $organization = get_post_meta( $post->ID, '_speaker_organization', true );
    wp_nonce_field( 'xyron_meta_save_nonce_action', 'xyron_meta_nonce_field' );
    ?>
    <p>
        <label for="speaker_job_title"><strong>Job Title:</strong></label>
        <input type="text" id="speaker_job_title" name="speaker_job_title" value="<?php echo esc_attr( $job_title ); ?>" class="widefat" />
    </p>
    <p>
        <label for="speaker_organization"><strong>Organization:</strong></label>
        <input type="text" id="speaker_organization" name="speaker_organization" value="<?php echo esc_attr( $organization ); ?>" class="widefat" />
    </p>
    <?php
}

// 3. SECURELY SAVE META DATA
add_action( 'save_post', 'xyron_safe_save_meta_data' );
function xyron_safe_save_meta_data( $post_id ) {
    if ( ! isset( $_POST['xyron_meta_nonce_field'] ) || ! wp_verify_nonce( $_POST['xyron_meta_nonce_field'], 'xyron_meta_save_nonce_action' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    
    if ( isset( $_POST['speaker_job_title'] ) ) {
        update_post_meta( $post_id, '_speaker_job_title', sanitize_text_field( $_POST['speaker_job_title'] ) );
    }
    if ( isset( $_POST['speaker_organization'] ) ) {
        update_post_meta( $post_id, '_speaker_organization', sanitize_text_field( $_POST['speaker_organization'] ) );
    }
}

// 4. SHORTCODE TO RENDER INTERFACE (FILTER + GRID)
add_shortcode( 'speaker_grid', 'xyron_safe_directory_shortcode' );
function xyron_safe_directory_shortcode() {
    global $wpdb;
    
    // Fetch unique organizations dynamically from database
    $organizations = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_speaker_organization' AND meta_value != ''");

    $output = '<style>
        .filter-container { margin: 30px 0; text-align: center; }
        .filter-select { padding: 12px 24px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 16px; min-width: 250px; background-color: #fff; color: #334155; }
        .speaker-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .speaker-card { border: 1px solid #e2e8f0; padding: 25px; border-radius: 16px; background: #fff; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); text-align: center; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; }
        .speaker-card:hover { transform: translateY(-6px); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); }
        .speaker-card img { border-radius: 12px; width: 100%; height: 260px; object-fit: cover; margin-bottom: 15px; }
        .speaker-name { font-size: 22px; font-weight: 700; margin: 10px 0 5px 0; color: #1e293b; }
        .speaker-job { font-size: 15px; color: #475569; font-weight: 600; margin-bottom: 4px; }
        .speaker-org { font-size: 14px; color: #0284c7; font-weight: 500; margin-bottom: 20px; }
        .book-btn { background: #0f172a; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; transition: background 0.2s; }
        .book-btn:hover { background: #1e293b; }
        .booking-form-pop { display: none; margin-top: 20px; padding-top: 15px; border-top: 1px solid #f1f5f9; text-align: left; }
        .booking-form-pop input { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .submit-booking-btn { background: #16a34a; color: #fff; border: none; padding: 10px 14px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 600; }
        .submit-booking-btn:hover { background: #15803d; }
        @media (max-width: 768px) { .speaker-grid { grid-template-columns: 1fr; } }
    </style>';

    // Filter UI
    $output .= '<div class="filter-container">';
    $output .= '<select id="org-filter" class="filter-select">';
    $output .= '<option value="">All Organizations</option>';
    if ( ! empty( $organizations ) ) {
        foreach ( $organizations as $org ) {
            $output .= '<option value="' . esc_attr($org) . '">' . esc_html($org) . '</option>';
        }
    }
    $output .= '</select>';
    $output .= '</div>';

    // Grid Area
    $output .= '<div id="speaker-ajax-grid">';
    $output .= xyron_safe_generate_cards_html();
    $output .= '</div>';

    // Pure JavaScript AJAX Handler (Theme Independent)
    $output .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Dropdown Dynamic Filter
        var filterDropdown = document.getElementById("org-filter");
        if (filterDropdown) {
            filterDropdown.addEventListener("change", function() {
                var org = this.value;
                var formData = new FormData();
                formData.append("action", "filter_speakers_action");
                formData.append("organization", org);

                fetch("' . admin_url('admin-ajax.php') . '", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.text())
                .then(data => {
                    document.getElementById("speaker-ajax-grid").innerHTML = data;
                });
            });
        }

        // Toggle Form Popups
        document.addEventListener("click", function(e) {
            if (e.target && e.target.classList.contains("book-btn")) {
                var formPop = e.target.nextElementSibling;
                formPop.style.display = (formPop.style.display === "block") ? "none" : "block";
            }
        });

        // Submit Booking AJAX
        document.addEventListener("submit", function(e) {
            if (e.target && e.target.classList.contains("speaker-booking-form")) {
                e.preventDefault();
                var form = e.target;
                var formData = new FormData(form);
                formData.append("action", "submit_booking_action");

                fetch("' . admin_url('admin-ajax.php') . '", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        form.innerHTML = "<p style=\'color:#16a34a; text-align:center; font-weight:bold; padding:10px;\'>" + res.data + "</p>";
                    } else {
                        alert(res.data);
                    }
                }).catch(err => alert("Submission failed."));
            }
        });
    });
    </script>';

    return $output;
}

// 5. HELPER TO RENDER CARDS
function xyron_safe_generate_cards_html( $org_filter = '' ) {
    $args = array(
        'post_type'      => 'speaker',
        'posts_per_page' => -1,
        'post_status'    => 'publish'
    );

    if ( ! empty( $org_filter ) ) {
        $args['meta_query'] = array(
            array(
                'key'     => '_speaker_organization',
                'value'   => $org_filter,
                'compare' => '='
            )
        );
    }

    $query = new WP_Query( $args );
    $html = '<div class="speaker-grid">';

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $speaker_id = get_the_ID();
            $job_title = get_post_meta( $speaker_id, '_speaker_job_title', true );
            $organization = get_post_meta( $speaker_id, '_speaker_organization', true );
            $image = get_the_post_thumbnail_url( $speaker_id, 'large' ) ? get_the_post_thumbnail_url( $speaker_id, 'large' ) : 'https://via.placeholder.com/300x260';

            $html .= '<div class="speaker-card">';
                $html .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( get_the_title() ) . '" />';
                $html .= '<h3 class="speaker-name">' . esc_html( get_the_title() ) . '</h3>';
                if ( $job_title ) $html .= '<p class="speaker-job">' . esc_html( $job_title ) . '</p>';
                if ( $organization ) $html .= '<p class="speaker-org">' . esc_html( $organization ) . '</p>';
                
                // Form Section
                $html .= '<button class="book-btn">Book a Meeting</button>';
                $html .= '<div class="booking-form-pop">';
                    $html .= '<form class="speaker-booking-form">';
                        $html .= wp_nonce_field( 'xyron_booking_nonce_action', 'booking_security_field', true, false );
                        $html .= '<input type="hidden" name="speaker_id" value="' . esc_attr($speaker_id) . '" />';
                        $html .= '<input type="text" name="visitor_name" placeholder="Your Name" required />';
                        $html .= '<input type="email" name="visitor_email" placeholder="Your Email" required />';
                        $html .= '<button type="submit" class="submit-booking-btn">Submit Booking</button>';
                    $html .= '</form>';
                $html .= '</div>';
            $html .= '</div>';
        }
        wp_reset_postdata();
    } else {
        $html .= '<p style="grid-column: 1/-1; text-align:center; color:#64748b;">No speakers found.</p>';
    }
    
    $html .= '</div>';
    return $html;
}

// 6. FILTER AJAX ENDPOINT
add_action( 'wp_ajax_filter_speakers_action', 'xyron_filter_ajax_handler' );
add_action( 'wp_ajax_nopriv_filter_speakers_action', 'xyron_filter_ajax_handler' );
function xyron_filter_ajax_handler() {
    $org = isset($_POST['organization']) ? sanitize_text_field($_POST['organization']) : '';
    echo xyron_safe_generate_cards_html($org);
    wp_die();
}

// 7. BOOKING SUBMISSION AJAX ENDPOINT
add_action( 'wp_ajax_submit_booking_action', 'xyron_booking_ajax_handler' );
add_action( 'wp_ajax_nopriv_submit_booking_action', 'xyron_booking_ajax_handler' );
function xyron_booking_ajax_handler() {
    if ( ! isset( $_POST['booking_security_field'] ) || ! wp_verify_nonce( $_POST['booking_security_field'], 'xyron_booking_nonce_action' ) ) {
        wp_send_json_error( 'Security check failed. Please refresh.' );
    }

    $speaker_id   = isset($_POST['speaker_id']) ? intval($_POST['speaker_id']) : 0;
    $visitor_name  = isset($_POST['visitor_name']) ? sanitize_text_field($_POST['visitor_name']) : '';
    $visitor_email = isset($_POST['visitor_email']) ? sanitize_email($_POST['visitor_email']) : '';

    if ( ! is_email( $visitor_email ) ) {
        wp_send_json_error( 'Please enter a valid email address.' );
    }

    $speaker_name = get_the_title($speaker_id);

    // Save as Post to 'speaker_booking'
    $booking_id = wp_insert_post( array(
        'post_type'    => 'speaker_booking',
        'post_title'   => sanitize_text_field("Booking: " . $visitor_name . " for " . $speaker_name),
        'post_content' => sanitize_textarea_field("Visitor Email: " . $visitor_email . "\nDate/Time: " . current_time('mysql')),
        'post_status'  => 'publish'
    ));

    if ( $booking_id ) {
        wp_send_json_success( 'Meeting booked successfully!' );
    } else {
        wp_send_json_error( 'Could not process booking. Try again.' );
    }
}