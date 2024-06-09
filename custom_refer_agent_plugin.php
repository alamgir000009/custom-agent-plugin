<?php
/*
Plugin Name: Custom Refer Agent Plugin
Description: A plugin to add new users with pending status and show referred agents by the current logged-in user.
Version: 1.0
Author: Alamgir Khan Armani
Author URI: https://alamgirarmani.vercel.app/
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Activation hook to add custom role
register_activation_hook(__FILE__, 'custom_agent_plugin_activate');

function custom_agent_plugin_activate()
{
    add_role(
        'agent',
        'Agent',
        array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
            'edit_pages' => true,
            'edit_others_posts' => true,
            'edit_published_posts' => true,
            'delete_others_posts' => true,
            'delete_published_posts' => true,
            'edit_published_pages' => true,
            'delete_published_pages' => true,
            'manage_categories' => true,
        )
    );
}

// Enqueue plugin styles
add_action('wp_enqueue_scripts', 'custom_agent_plugin_enqueue_styles');

function custom_agent_plugin_enqueue_styles()
{
    // Only enqueue the styles on pages where the shortcodes are used
    if (has_shortcode(get_post()->post_content, 'custom_agent_form') || has_shortcode(get_post()->post_content, 'custom_agent_table')) {
        wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css');
        wp_enqueue_style('custom-agent-plugin-styles', plugin_dir_url(__FILE__) . 'assets/style.css');
    }
}

// Filter to handle custom avatars
add_filter('get_avatar', 'custom_agent_custom_avatar', 10, 5);

function custom_agent_custom_avatar($avatar, $id_or_email, $size, $default, $alt)
{
    $user = false;

    if (is_numeric($id_or_email)) {
        $id = (int) $id_or_email;
        $user = get_user_by('id', $id);
    } elseif (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            $id = (int) $id_or_email->user_id;
            $user = get_user_by('id', $id);
        }
    } else {
        $user = get_user_by('email', $id_or_email);
    }

    if ($user && is_object($user)) {

        $profile_picture = get_user_meta($user->ID, 'profile_picture', true);
        if ($profile_picture) {
            if (is_numeric($profile_picture)) {
                $profile_picture_url = wp_get_attachment_url($profile_picture);
            } else {
                $profile_picture_url = $profile_picture;
            }
            if ($profile_picture_url) {
                $avatar = '<img src="' . esc_url($profile_picture_url) . '" alt="' . esc_attr($alt) . '" width="' . (int) $size . '" height="' . (int) $size . '" />';
            }
        }
    }

    return $avatar;
}

// Deactivation hook to remove custom role
register_deactivation_hook(__FILE__, 'custom_agent_plugin_deactivate');

function custom_agent_plugin_deactivate()
{
    remove_role('agent');
}

// Add the custom field to the user profile page
function custom_agent_show_extra_profile_fields($user)
{
    if (!current_user_can('edit_users')) {
        return;
    }

    $agent_status = get_user_meta($user->ID, 'agent_status', true);
    ?>
    <h3>Referred Agent Status</h3>
    <table class="form-table">
        <tr>
            <th><label for="agent_status">Agent Status</label></th>
            <td>
                <select name="agent_status" id="agent_status">
                    <option value="pending" <?php selected($agent_status, 'pending'); ?>>Pending</option>
                    <option value="approved" <?php selected($agent_status, 'approved'); ?>>Approved</option>
                    <option value="rejected" <?php selected($agent_status, 'rejected'); ?>>Rejected</option>
                </select>
                <span class="description">Select the agent status.</span>
            </td>
        </tr>
    </table>
    <?php
}

add_action('show_user_profile', 'custom_agent_show_extra_profile_fields');
add_action('edit_user_profile', 'custom_agent_show_extra_profile_fields');

// Save the custom field value
function custom_agent_save_extra_profile_fields($user_id)
{
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    $new_status = sanitize_text_field($_POST['agent_status']);
    $old_status = get_user_meta($user_id, 'agent_status', true);

    if ($new_status !== $old_status) {
        update_user_meta($user_id, 'agent_status', $new_status);

        $user_info = get_userdata($user_id);
        $user_email = $user_info->user_email;
        $created_by = get_user_meta($user_id, 'referred_by', true);
        $created_by_user = get_userdata($created_by);
        $created_by_email = $created_by_user->user_email;

        if ($new_status === 'approved') {
            $reset_key = get_password_reset_key($user_info);
            $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user_info->user_login), 'login');

            $subject = 'Your Agent Account Has Been Approved';
            $message = "Hi {$user_info->first_name},\n\nYour agent account has been approved. You can set your password using the following link:\n\n$reset_url\n\nThank you.";
            wp_mail($user_email, $subject, $message);
        }

        if ($new_status === 'rejected') {
            // Notify the user who created the agent
            $subject = 'Agent Account Application Rejected';
            $message = "Hi,\n\nThe agent account for {$user_info->first_name} {$user_info->last_name} has been rejected.\n\nThank you.";
            wp_mail($created_by_email, $subject, $message);
        }
    }
}

add_action('personal_options_update', 'custom_agent_save_extra_profile_fields');
add_action('edit_user_profile_update', 'custom_agent_save_extra_profile_fields');

function custom_agent_start_session()
{
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'custom_agent_start_session', 1);



// Shortcode for the form
add_shortcode('custom_agent_form', 'custom_agent_form_shortcode');

function custom_agent_form_shortcode()
{
    ob_start();
    ?>
    <div class="col-md-12">
        <form method="post" action="" enctype="multipart/form-data">
            <div class="card shadow-lg px-md-3">
                <div class="card-header bg-white">
                    <h2>Refer Agent</h2>
                    <?php
                    if (isset($_SESSION['agent_form_msg'])) {
                        echo $_SESSION['agent_form_msg'];
                        unset($_SESSION['agent_form_msg']);
                    }
                    ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="user_login">Username</label>
                            <input placeholder="Enter Username" type="text" name="user_login" id="user_login"
                                class="form-control" required>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="full_name">Full Name</label>
                            <input placeholder="Enter Full Name" type="text" name="full_name" id="full_name"
                                class="form-control" required>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="user_email">Email</label>
                            <input placeholder="Enter Email" type="email" name="user_email" id="user_email"
                                class="form-control" required>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="phone">Phone</label>
                            <input placeholder="Enter Phone" type="text" name="phone" id="phone" class="form-control"
                                required>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="brokerage">Brokerage</label>
                            <input placeholder="Enter Brokerage" type="text" name="brokerage" id="brokerage"
                                class="form-control" required>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="website">Website</label>
                            <input placeholder="Enter Website" type="url" name="website" id="website" class="form-control"
                                required>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="city">City</label>
                            <input placeholder="Enter City" type="text" name="city" id="city" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="dob">Date of Birth</label>
                            <input type="date" name="dob" id="dob" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="gender">Gender</label>
                            <select name="gender" class="form-control form-select" id="gender" required>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            </td>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="designation">Designation</label>
                            <select name="designation" id="designation" class="form-control form-select" required>
                                <option value="Broker">Broker</option>
                                <option value="Salesperson">Salesperson</option>
                                <option value="Broker of record">Broker of record</option>
                                <option value="Broker of Manager">Broker of Manager</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="profile_picture">Profile Picture</label>
                            <input type="file" name="profile_picture" class="form-control" id="profile_picture"
                                accept="image/*" required>
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="agent_resume">Agent Resume</label>
                            <input type="file" name="agent_resume" class="form-control" id="agent_resume"
                                accept=".png,.jpg,.jpeg,.pdf,.doc,.docx" required>
                        </div>
                        <input type="hidden" name="custom_agent_form" value="1">
                        <?php wp_nonce_field('custom_agent_form_action', 'custom_agent_form_nonce'); ?>
                        <div class="form-group col-md-12 mt-3">
                            <input type="submit" name="submit" value="Add Agent" class="btn btn-success">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// Handle form submission
add_action('init', 'custom_agent_handle_form_submission');

function custom_agent_handle_form_submission()
{
    if (isset($_POST['custom_agent_form']) && $_POST['custom_agent_form'] == '1') {
        // Check nonce
        if (!isset($_POST['custom_agent_form_nonce']) || !wp_verify_nonce($_POST['custom_agent_form_nonce'], 'custom_agent_form_action')) {
            $_SESSION['agent_form_msg'] = '<div class="alert alert-danger"><p>Nonce verification failed. Please try again.</p></div>';
            wp_redirect($_SERVER['REQUEST_URI']);
            exit;
        }

        // Sanitize and validate input fields
        $errors = [];
        $user_login = sanitize_text_field($_POST['user_login']);
        $full_name = sanitize_text_field($_POST['full_name']);
        $user_email = sanitize_email($_POST['user_email']);
        $phone = sanitize_text_field($_POST['phone']);
        $brokerage = sanitize_text_field($_POST['brokerage']);
        $website = esc_url($_POST['website']);
        $city = sanitize_text_field($_POST['city']);
        $dob = sanitize_text_field($_POST['dob']);
        $gender = sanitize_text_field($_POST['gender']);
        $designation = sanitize_text_field($_POST['designation']);
        $current_user = wp_get_current_user();
        $current_user_name = $current_user->display_name;

        // Required fields
        if (empty($user_login)) {
            $errors[] = 'Username is required.';
        }
        if (empty($full_name)) {
            $errors[] = 'Full name is required.';
        }
        if (empty($user_email) || !is_email($user_email)) {
            $errors[] = 'A valid email is required.';
        } elseif (email_exists($user_email) || custom_agent_email_exists_in_post_meta($user_email)) {
            $errors[] = 'This email is already in use.';
        }
        if (empty($gender)) {
            $errors[] = 'Gender is required.';
        }

        // File uploads
        $profile_picture = custom_agent_handle_file_upload('profile_picture');
        $agent_resume = custom_agent_handle_file_upload('agent_resume');

        if (empty($profile_picture)) {
            $errors[] = 'Profile picture is required.';
        }
        if (empty($agent_resume)) {
            $errors[] = 'Agent Resume is required.';
        }

        if (is_wp_error($profile_picture)) {
            $errors[] = 'Profile picture upload failed: ' . $profile_picture->get_error_message();
        }

        if (is_wp_error($agent_resume)) {
            $errors[] = 'Agent resume upload failed: ' . $agent_resume->get_error_message();
        }

        // Check if there are errors
        if (!empty($errors)) {
            $_SESSION['agent_form_msg'] = '<div class="alert alert-danger"><p>' . implode('<br>', $errors) . '</p></div>';
            wp_redirect($_SERVER['REQUEST_URI']);
            exit;
        }

        // Create agent post
        $agent_post = array(
            'post_title' => $full_name,
            'post_content' => '',
            'post_status' => 'pending',
            'post_author' => $current_user->ID,
            'post_type' => 'agent'
        );

        $post_id = wp_insert_post($agent_post);

        if ($post_id) {
            update_post_meta($post_id, 'user_login', $user_login);
            update_post_meta($post_id, 'full_name', $full_name);
            update_post_meta($post_id, 'user_email', $user_email);
            update_post_meta($post_id, 'created_by', $current_user->ID);
            update_post_meta($post_id, 'agent_status', 'pending');
            update_post_meta($post_id, 'phone', $phone);
            update_post_meta($post_id, 'brokerage', $brokerage);
            update_post_meta($post_id, 'website', $website);
            update_post_meta($post_id, 'city', $city);
            update_post_meta($post_id, 'dob', $dob);
            update_post_meta($post_id, 'gender', $gender);
            update_post_meta($post_id, 'designation', $designation);
            if ($profile_picture) {
                update_post_meta($post_id, 'profile_picture', $profile_picture);
            }
            if ($agent_resume) {
                update_post_meta($post_id, 'agent_resume', $agent_resume);
            }

            $admin_email = get_option('admin_email');
            $post_url = admin_url('edit.php?post_type=agent');

            $subject = __('New Agent Referred');
            $message = '<html><body>';
            $message .= '<p>' . __('Hello Admin,') . '</p>';
            $message .= '<p>' . __('A new agent has been referred and is pending your approval.') . '</p>';
            $message .= '<p>' . __('Agent Name: ') . esc_html($full_name) . '</p>';
            $message .= '<p>' . __('Referred By: ') . esc_html($current_user_name) . '</p>';
            $message .= '<p>' . __('You can view and approve the agent by clicking the button below:') . '</p>';
            $message .= '<p><a href="' . esc_url($post_url) . '" style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; background-color: #0073aa; text-decoration: none; border-radius: 5px;">' . __('View Agent') . '</a></p>';
            $message .= '</body></html>';

            // Set the content type to HTML
            add_filter('wp_mail_content_type', function () {
                return 'text/html';
            });

            // Send the email
            wp_mail($admin_email, $subject, $message);

            // Remove the content type filter to avoid conflicts
            remove_filter('wp_mail_content_type', function () {
                return 'text/html';
            });

            $_SESSION['agent_form_msg'] = '<div class="alert alert-success"><p>Agent data stored successfully wait for admin approval.</p></div>';
        } else {
            $_SESSION['agent_form_msg'] = '<div class="alert alert-danger"><p>Error creating agent post.</p></div>';
        }

        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }
}
add_action('admin_post_custom_agent_handle_form_submission', 'custom_agent_handle_form_submission');

function custom_agent_email_exists_in_post_meta($email)
{
    global $wpdb;
    $query = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'user_email' AND meta_value = %s", $email);
    $results = $wpdb->get_results($query);

    return !empty($results);
}


// Handle file uploads
function custom_agent_handle_file_upload($file_key)
{
    if (!function_exists('wp_handle_upload')) {
        require_once (ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploadedfile = $_FILES[$file_key];
    $upload_overrides = array('test_form' => false);

    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        return $movefile['url'];
    } else {
        return '';
    }
}


// Shortcode for the table
add_shortcode('custom_approved_agent_table', 'custom_approved_agent_table_shortcode');
function custom_approved_agent_table_shortcode()
{
    ob_start();
    $current_user_id = get_current_user_id();
    $args = array(
        'meta_key' => 'created_by',
        'meta_value' => $current_user_id,
        'meta_compare' => '=',
        'role' => 'agent'
    );
    $user_query = new WP_User_Query($args);

    echo '
    <div class="col-md-12">
    <div class="card shadow-lg px-md-3">
    <div class="card-header bg-white">
    <h2>My Approved agents list</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-hover table-stripped">
        <thead>
        <tr>
        <th id="username" class="text-nowrap">Username</th>
        <th id="email" class="text-nowrap">Email</th>
        <th id="full_name" class="text-nowrap">Full Name</th>
        <th id="phone" class="text-nowrap">Phone</th>
        <th id="brokerage" class="text-nowrap">Brokerage</th>
        <th id="website" class="text-nowrap">Website</th>
        <th id="city" class="text-nowrap">City</th>
        <th id="dob" class="text-nowrap">Date of Birth</th>
        <th id="gender" class="text-nowrap">Gender</th>
        <th id="designation" class="text-nowrap">Designation</th>
        <th id="profile_picture" class="text-nowrap">Profile Picture</th>
        <th id="agent_resume" class="text-nowrap">Agent Resume</th>
        </tr>
        </thead>
                        <tbody>';
    if ($user_query->get_results()) {
        foreach ($user_query->get_results() as $user) {

            $profile_picture_meta = get_user_meta($user->ID, 'profile_picture', true);
            if (is_numeric($profile_picture_meta)) {
                $profile_picture_id = intval($profile_picture_meta);
                $profile_picture_url = wp_get_attachment_url($profile_picture_id);
            } else {
                $profile_picture_url = esc_url($profile_picture_meta);
            }

            $agent_resume_meta = get_user_meta($user->ID, 'agent_resume', true);
            if (is_numeric($agent_resume_meta)) {
                $agent_resume_id = intval($agent_resume_meta);
                $agent_resume_url = wp_get_attachment_url($agent_resume_id);
            } else {
                $agent_resume_url = esc_url($agent_resume_meta);
            }
            echo '<tr>
                                <td class="text-nowrap">' . esc_html($user->user_login) . '</td>
                                <td class="text-nowrap">' . esc_html($user->user_email) . '</td>
                                <td class="text-nowrap">' . esc_html(get_user_meta($user->ID, 'full_name', true)) . '</td>
                                <td class="text-nowrap">' . esc_html(get_user_meta($user->ID, 'phone', true)) . '</td>
                                <td class="text-nowrap">' . esc_html(get_user_meta($user->ID, 'brokerage', true)) . '</td>
                                <td class="text-nowrap">' . esc_html(get_user_meta($user->ID, 'website', true)) . '</td>
                                <td class="text-nowrap">' . esc_html(get_user_meta($user->ID, 'city', true)) . '</td>
                                <td class="text-nowrap">' . esc_html(get_user_meta($user->ID, 'dob', true)) . '</td>
                                <td class="text-nowrap">' . esc_html(get_user_meta($user->ID, 'gender', true)) . '</td>
                                <td class="text-nowrap">' . esc_html(get_user_meta($user->ID, 'designation', true)) . '</td>
                                <td><img src="' . esc_url($profile_picture_url) . '" width="50" height="50" /></td>
            <td>' . ($agent_resume_url ? '<a href="' . esc_url($agent_resume_url) . '">Download</a>' : 'No resume available') . '</td>
          </tr>';
        }
    } else {
        echo '<tr>
        <td colspan="12">No Approved Agents Found</td>
        </tr>';
    }
    echo '</tbody></table></div></div></div></div>';
    wp_reset_postdata();
    return ob_get_clean();
}

// Display Unapproved agents table
add_shortcode('custom_unapproved_agent_table', 'custom_unapproved_agent_table_shortcode');
function custom_unapproved_agent_table_shortcode()
{
    ob_start();
    $current_user_id = get_current_user_id();
    // Query for pending/rejected agents (custom post type)
    $pending_rejected_args = array(
        'post_type' => 'agent',
        'post_status' => 'any',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'created_by',
                'value' => $current_user_id,
                'compare' => '='
            ),
            array(
                'key' => 'agent_status',
                'value' => array('pending', 'rejected'),
                'compare' => 'IN'
            )
        ),
        'posts_per_page' => -1 // Ensure all posts are retrieved
    );
    $user_query = new WP_Query($pending_rejected_args);

    echo '
    <div class="col-md-12">
    <div class="card shadow-lg px-md-3">
    <div class="card-header bg-white">
    <h2>My Pending or Rejected Agents List</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-hover table-stripped">
        <thead>
        <tr>
        <th id="full_name" class="text-nowrap">Full Name</th>
        <th id="email" class="text-nowrap">Email</th>
        <th id="phone" class="text-nowrap">Phone</th>
        <th id="brokerage" class="text-nowrap">Brokerage</th>
        <th id="website" class="text-nowrap">Website</th>
        <th id="city" class="text-nowrap">City</th>
        <th id="dob" class="text-nowrap">Date of Birth</th>
        <th id="gender" class="text-nowrap">Gender</th>
        <th id="designation" class="text-nowrap">Designation</th>
        <th id="profile_picture" class="text-nowrap">Profile Picture</th>
        <th id="agent_resume" class="text-nowrap">Agent Resume</th>
        <th id="status" class="text-nowrap">Status</th>
        </tr>
        </thead>
        <tbody>';

    if ($user_query->have_posts()) {
        while ($user_query->have_posts()) {
            $user_query->the_post();
            $post_id = get_the_ID();
            $status = get_post_meta($post_id, 'agent_status', true);

            echo '<tr>
                    <td class="text-nowrap">' . esc_html(get_post_meta($post_id, 'full_name', true)) . '</td>
                    <td class="text-nowrap">' . esc_html(get_post_meta($post_id, 'user_email', true)) . '</td>
                    <td class="text-nowrap">' . esc_html(get_post_meta($post_id, 'phone', true)) . '</td>
                    <td class="text-nowrap">' . esc_html(get_post_meta($post_id, 'brokerage', true)) . '</td>
                    <td class="text-nowrap">' . esc_html(get_post_meta($post_id, 'website', true)) . '</td>
                    <td class="text-nowrap">' . esc_html(get_post_meta($post_id, 'city', true)) . '</td>
                    <td class="text-nowrap">' . esc_html(get_post_meta($post_id, 'dob', true)) . '</td>
                    <td class="text-nowrap">' . esc_html(get_post_meta($post_id, 'gender', true)) . '</td>
                    <td class="text-nowrap">' . esc_html(get_post_meta($post_id, 'designation', true)) . '</td>
                    <td class="text-nowrap"><img src="' . esc_url(get_post_meta($post_id, 'profile_picture', true)) . '" width="50" height="50" /></td>
                    <td class="text-nowrap"><a href="' . esc_url(get_post_meta($post_id, 'agent_resume', true)) . '">Download</a></td>
                    <td class="text-nowrap">' . esc_html(ucfirst($status)) . '</td>
                </tr>';
        }
    } else {
        echo '<tr>
                <td colspan="12">No Pending or Rejected Agents Found</td>
              </tr>';
    }

    wp_reset_postdata();

    echo '</tbody></table></div></div></div></div>';

    return ob_get_clean();
}


// Add custom columns to the "agent" post type
function custom_agent_add_custom_columns($columns)
{
    $columns['full_name'] = 'Full Name';
    $columns['user_email'] = 'Email';
    $columns['created_by'] = 'Created By';
    $columns['phone'] = 'Phone';
    $columns['brokerage'] = 'Brokerage';
    $columns['website'] = 'Website';
    $columns['city'] = 'City';
    $columns['dob'] = 'DOB';
    $columns['gender'] = 'Gender';
    $columns['designation'] = 'Designation';
    $columns['agent_resume'] = 'Agent Resume';
    $columns['user_profile'] = 'View User Profile';
    $columns['agent_status'] = 'Agent Status';
    return $columns;
}
add_filter('manage_agent_posts_columns', 'custom_agent_add_custom_columns');

// Populate custom columns with data
function custom_agent_show_custom_columns_data($column, $post_id)
{
    switch ($column) {
        case 'full_name':
            echo esc_html(get_post_meta($post_id, 'full_name', true));
            break;
        case 'user_email':
            echo esc_html(get_post_meta($post_id, 'user_email', true));
            break;
        case 'created_by':
            $created_by_user_id = get_post_meta($post_id, 'created_by', true);
            $created_by_user = get_userdata($created_by_user_id);
            echo $created_by_user->user_email;
            break;
        case 'phone':
            echo esc_html(get_post_meta($post_id, 'phone', true));
            break;
        case 'brokerage':
            echo esc_html(get_post_meta($post_id, 'brokerage', true));
            break;
        case 'website':
            $website = get_post_meta($post_id, 'website', true);
            echo $website ? '<a href="' . esc_url($website) . '" target="_blank">' . esc_html($website) . '</a>' : '';
            break;
        case 'city':
            echo esc_html(get_post_meta($post_id, 'city', true));
            break;
        case 'dob':
            echo esc_html(get_post_meta($post_id, 'dob', true));
            break;
        case 'gender':
            echo esc_html(get_post_meta($post_id, 'gender', true));
            break;
        case 'designation':
            echo esc_html(get_post_meta($post_id, 'designation', true));
            break;
        case 'agent_resume':
            $resume_url = get_post_meta($post_id, 'agent_resume', true);
            echo $resume_url ? '<a href="' . esc_url($resume_url) . '" target="_blank">View Resume</a>' : '';
            break;
        case 'user_profile':
            $user_id = get_post_meta($post_id, 'user_id', true);
            if ($user_id) {
                $user_info = get_userdata($user_id);
                if ($user_info) {
                    echo '<a href="' . esc_url(get_edit_user_link($user_id)) . '" target="_blank">' . esc_html($user_info->display_name) . '</a>';
                } else {
                    echo 'No user found';
                }
            } else {
                echo 'N/A';
            }
            break;
        case 'agent_status':
            $current_status = get_post_meta($post_id, 'agent_status', true);
            if ($current_status != 'approved') {
                ?>
                <select class="agent-status-select" data-post-id="<?php echo esc_attr($post_id); ?>"
                    data-nonce="<?php echo wp_create_nonce('custom_agent_nonce'); ?>">
                    <?php if ($current_status != 'approved') { ?>
                        <option value="pending" <?php selected($current_status, 'pending'); ?>>Pending</option>
                    <?php } ?>
                    <option value="approved" <?php selected($current_status, 'approved'); ?>>Approved</option>
                    <option value="rejected" <?php selected($current_status, 'rejected'); ?>>Rejected</option>
                </select>
            <?php } else {
                echo "Approved";
            }
            break;
    }
}
add_action('manage_agent_posts_custom_column', 'custom_agent_show_custom_columns_data', 10, 2);

function enqueue_custom_admin_scripts($hook)
{
    if ('edit.php' !== $hook || 'agent' !== get_post_type()) {
        return;
    }

    wp_enqueue_script('custom-agent-admin', plugin_dir_url(__FILE__) . 'custom-agent-admin.js', array('jquery'), null, true);
    wp_localize_script(
        'custom-agent-admin',
        'customAgentAdmin',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_agent_nonce')
        )
    );
}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_scripts');


function handle_update_agent_status()
{
    check_ajax_referer('custom_agent_nonce', 'nonce');

    if (!current_user_can('edit_post', $_POST['post_id'])) {
        wp_send_json_error('You do not have permission to edit this post.');
    }

    $post_id = intval($_POST['post_id']);
    $new_status = sanitize_text_field($_POST['new_status']);

    update_post_meta($post_id, 'agent_status', $new_status);
    $full_name = get_post_meta($post_id, 'full_name', true);

    if ($new_status == 'approved') {
        // Retrieve post meta data
        $user_login = get_post_meta($post_id, 'user_login', true);
        $user_email = get_post_meta($post_id, 'user_email', true);
        $created_by = get_post_meta($post_id, 'created_by', true);
        $phone = get_post_meta($post_id, 'phone', true);
        $brokerage = get_post_meta($post_id, 'brokerage', true);
        $website = get_post_meta($post_id, 'website', true);
        $city = get_post_meta($post_id, 'city', true);
        $dob = get_post_meta($post_id, 'dob', true);
        $gender = get_post_meta($post_id, 'gender', true);
        $designation = get_post_meta($post_id, 'designation', true);
        $agent_resume = get_post_meta($post_id, 'agent_resume', true);
        $profile_picture = get_post_meta($post_id, 'profile_picture', true);

        // Generate a unique username
        $user_login = sanitize_user($user_login);
        $user_email = sanitize_email($user_email);

        // Create user
        $user_password = wp_generate_password();
        $user_id = wp_create_user($user_login, $user_password, $user_email);

        if (!is_wp_error($user_id)) {

            // Update user role and meta
            wp_update_user(array('ID' => $user_id, 'role' => 'agent'));
            update_user_meta($user_id, 'full_name', $full_name);
            update_user_meta($user_id, 'created_by', $created_by);
            update_user_meta($user_id, 'phone', $phone);
            update_user_meta($user_id, 'brokerage', $brokerage);
            update_user_meta($user_id, 'website', $website);
            update_user_meta($user_id, 'city', $city);
            update_user_meta($user_id, 'dob', $dob);
            update_user_meta($user_id, 'gender', $gender);
            update_user_meta($user_id, 'designation', $designation);

            if ($profile_picture || $agent_resume) {

                require_once (ABSPATH . 'wp-admin/includes/file.php');
                require_once (ABSPATH . 'wp-admin/includes/media.php');
                require_once (ABSPATH . 'wp-admin/includes/image.php');


                if ($profile_picture) {

                    $tmp = download_url($profile_picture);

                    if (!is_wp_error($tmp)) {
                        $file_array = array(
                            'name' => basename($profile_picture),
                            'tmp_name' => $tmp,
                        );

                        $attachment_id = media_handle_sideload($file_array, 0);

                        if (is_wp_error($attachment_id)) {
                            @unlink($file_array['tmp_name']);
                        } else {
                            update_user_meta($user_id, 'profile_picture', $attachment_id);
                        }
                    }
                }

                if ($agent_resume) {

                    $tmp = download_url($agent_resume);

                    if (!is_wp_error($tmp)) {
                        $file_array = array(
                            'name' => basename($agent_resume),
                            'tmp_name' => $tmp,
                        );

                        $attachment_id = media_handle_sideload($file_array, 0);

                        if (is_wp_error($attachment_id)) {
                            @unlink($file_array['tmp_name']);
                        } else {
                            update_user_meta($user_id, 'agent_resume', $attachment_id);
                        }
                    }
                }
            }

            // Send reset password email
            send_reset_password_email($user_id);

            // Update the post meta to link to the new user
            update_post_meta($post_id, 'user_id', $user_id);

            wp_send_json_success();
        } else {
            wp_send_json_error('User creation failed: ' . $user_id->get_error_message());
        }
    } elseif ($new_status == 'rejected') {
        // Get the created_by user ID from the post meta
        $created_by_user_id = get_post_meta($post_id, 'created_by', true);
        $created_by_user = get_userdata($created_by_user_id);

        if ($created_by_user) {
            $created_by_email = $created_by_user->user_email;

            // Send rejection email
            $subject = __('Agent Referral Rejected');
            $message = '<html><body>';
            $message .= '<p>' . __('Hello,') . '</p>';
            $message .= '<p>' . __('We regret to inform you that the agent you referred has been rejected by the admin.') . '</p>';
            $message .= '<p>' . __('Agent Name: ') . esc_html($full_name) . '</p>';
            $message .= '<p>' . __('If you have any questions, please contact us.') . '</p>';
            $message .= '<p>' . __('Thank you,') . '</p>';
            $message .= '<p>' . __('The Admin Team') . '</p>';
            $message .= '</body></html>';

            // Set the content type to HTML
            add_filter('wp_mail_content_type', function () {
                return 'text/html';
            });

            // Send the email
            wp_mail($created_by_email, $subject, $message);

            // Remove the content type filter to avoid conflicts
            remove_filter('wp_mail_content_type', function () {
                return 'text/html';
            });

            wp_send_json_success('Rejection email sent to the referrer.');
        } else {
            error_log("Status is not approved, no user created.");
            wp_send_json_success();
        }

    }
}

add_action('wp_ajax_update_agent_status', 'handle_update_agent_status');

function send_reset_password_email($user_id)
{
    $user = get_userdata($user_id);
    $reset_key = get_password_reset_key($user);

    if (is_wp_error($reset_key)) {
        return false;
    }

    $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');

    $message = '<html><body>';
    $message .= '<p>' . __('Welcome') . '</p>';
    $message .= '<p>' . __('Your agent account is approved by admin:') . '</p>';
    $message .= '<p>' . __('You can set your password now:') . '</p>';
    $message .= '<p>' . sprintf(__('Username: %s'), $user->user_login) . '</p>';
    $message .= '<p>' . __('If this was a mistake, just ignore this email and nothing will happen.') . '</p>';
    $message .= '<p>' . __('To reset your password, visit the following address:') . '</p>';
    $message .= '<p><a href="' . esc_url($reset_url) . '" style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; background-color: #0073aa; text-decoration: none; border-radius: 5px;">' . __('Reset Password') . '</a></p>';
    $message .= '</body></html>';

    // Set the content type to HTML
    add_filter('wp_mail_content_type', function () {
        return 'text/html';
    });

    // Send the email
    wp_mail($user->user_email, __('Password Reset'), $message);

    // Remove the content type filter to avoid conflicts
    remove_filter('wp_mail_content_type', function () {
        return 'text/html';
    });
}




function create_user_from_post($post_id)
{
    $user_data = array(
        'user_login' => get_post_meta($post_id, 'full_name', true),
        'user_email' => get_post_meta($post_id, 'email', true),
        'user_pass' => wp_generate_password(), // Generate a random password
        'role' => 'subscriber' // You can change the role if needed
    );

    $user_id = wp_insert_user($user_data);

    if (is_wp_error($user_id)) {
        return false;
    }

    // Copy additional meta data
    update_user_meta($user_id, 'phone', get_post_meta($post_id, 'phone', true));
    update_user_meta($user_id, 'brokerage', get_post_meta($post_id, 'brokerage', true));
    update_user_meta($user_id, 'website', get_post_meta($post_id, 'website', true));
    update_user_meta($user_id, 'city', get_post_meta($post_id, 'city', true));
    update_user_meta($user_id, 'dob', get_post_meta($post_id, 'dob', true));
    update_user_meta($user_id, 'gender', get_post_meta($post_id, 'gender', true));
    update_user_meta($user_id, 'designation', get_post_meta($post_id, 'designation', true));

    return $user_id;
}


// Add custom columns to users list

function custom_agent_add_custom_user_columns($columns)
{
    $columns['full_name'] = __('Full Name');
    $columns['created_by'] = __('Created By');
    $columns['phone'] = __('Phone');
    $columns['brokerage'] = __('Brokerage');
    $columns['website'] = __('Website');
    $columns['city'] = __('City');
    $columns['dob'] = __('Date of Birth');
    $columns['gender'] = __('Gender');
    $columns['designation'] = __('Designation');
    $columns['agent_resume'] = __('Resume');
    return $columns;
}
add_filter('manage_users_columns', 'custom_agent_add_custom_user_columns');

// Populate custom columns with data
function custom_agent_show_custom_user_columns_data($value, $column_name, $user_id)
{
    switch ($column_name) {
        case 'full_name':
            return get_user_meta($user_id, 'full_name', true);
        case 'created_by':
            $created_by_user_id = get_user_meta($user_id, 'created_by', true);
            if ($created_by_user_id) {
                $created_by_user = get_userdata($created_by_user_id);
                return $created_by_user ? esc_html($created_by_user->user_email) : 'User not found';
            } else {
                return 'No creator ID';
            }
        case 'phone':
            return get_user_meta($user_id, 'phone', true);
        case 'brokerage':
            return get_user_meta($user_id, 'brokerage', true);
        case 'website':
            $website = get_user_meta($user_id, 'website', true);
            return $website ? '<a href="' . esc_url($website) . '" target="_blank">' . esc_html($website) . '</a>' : '';
        case 'city':
            return get_user_meta($user_id, 'city', true);
        case 'dob':
            return get_user_meta($user_id, 'dob', true);
        case 'gender':
            return get_user_meta($user_id, 'gender', true);
        case 'designation':
            return get_user_meta($user_id, 'designation', true);
        case 'agent_resume':
            $resume_meta = get_user_meta($user_id, 'agent_resume', true);
            if (is_numeric($resume_meta)) {
                $resume_attachment_id = intval($resume_meta);
                $resume_url = wp_get_attachment_url($resume_attachment_id);
            } else {
                $resume_url = esc_url($resume_meta);
            }
            if ($resume_url) {
                return '<a href="' . esc_url($resume_url) . '" target="_blank">View Resume</a>';
            }

            return '';
        default:
            return $value;
    }
}
add_action('manage_users_custom_column', 'custom_agent_show_custom_user_columns_data', 10, 3);

function custom_agent_post_type()
{
    $labels = array(
        'name' => _x('Agents', 'post type general name', 'your-plugin-textdomain'),
        'singular_name' => _x('Agent', 'post type singular name', 'your-plugin-textdomain'),
        'menu_name' => _x('Agents', 'admin menu', 'your-plugin-textdomain'),
        'name_admin_bar' => _x('Agent', 'add new on admin bar', 'your-plugin-textdomain'),
        'add_new' => _x('Add New', 'agent', 'your-plugin-textdomain'),
        'add_new_item' => __('Add New Agent', 'your-plugin-textdomain'),
        'new_item' => __('New Agent', 'your-plugin-textdomain'),
        'edit_item' => __('Edit Agent', 'your-plugin-textdomain'),
        'view_item' => __('View Agent', 'your-plugin-textdomain'),
        'all_items' => __('All Agents', 'your-plugin-textdomain'),
        'search_items' => __('Search Agents', 'your-plugin-textdomain'),
        'parent_item_colon' => __('Parent Agents:', 'your-plugin-textdomain'),
        'not_found' => __('No agents found.', 'your-plugin-textdomain'),
        'not_found_in_trash' => __('No agents found in Trash.', 'your-plugin-textdomain')
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_admin_bar' => false,
        'query_var' => true,
        'rewrite' => array('slug' => 'agent'),
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => false,
        ),
        'map_meta_cap' => true,
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => null,
        'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments')
    );

    register_post_type('agent', $args);
}
add_action('init', 'custom_agent_post_type');

?>