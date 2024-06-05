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
            $avatar = '<img src="' . esc_url($profile_picture) . '" alt="' . esc_attr($alt) . '" width="' . (int) $size . '" height="' . (int) $size . '" />';
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

    wp_mail("alamgir@yopmail.com", "TESt", "MEssage");

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
                    <?php if (!empty($_SESSION['agent_form_msg'])) { ?>
                        <?php echo $_SESSION['agent_form_msg'];
                        $_SESSION['agent_form_msg'] = '';
                        ?>
                    <?php } ?>
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
                            <input placeholder="Enter Phone" type="text" name="phone" id="phone" class="form-control">
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="brokerage">Brokerage</label>
                            <input placeholder="Enter Brokerage" type="text" name="brokerage" id="brokerage"
                                class="form-control">
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="website">Website</label>
                            <input placeholder="Enter Website" type="url" name="website" id="website" class="form-control">
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="city">City</label>
                            <input placeholder="Enter City" type="text" name="city" id="city" class="form-control">
                        </div>
                        <div class="form-group col-md-6 mt-3">
                            <label class="form-label" for="dob">Date of Birth</label>
                            <input type="date" name="dob" id="dob" class="form-control">
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

// Shortcode for the table
add_shortcode('custom_agent_table', 'custom_agent_table_shortcode');

function custom_agent_table_shortcode()
{
    ob_start();
    custom_agent_display_referred_agents_table();
    return ob_get_clean();
}

// Handle form submission
add_action('init', 'custom_agent_handle_form_submission');

function custom_agent_handle_form_submission()
{
    if (isset($_POST['custom_agent_form']) && $_POST['custom_agent_form'] == '1') {
        if (!isset($_POST['custom_agent_form_nonce']) || !wp_verify_nonce($_POST['custom_agent_form_nonce'], 'custom_agent_form_action')) {
            return;
        }

        $user_login = sanitize_text_field($_POST['user_login']);
        $user_email = sanitize_email($_POST['user_email']);
        $full_name = sanitize_text_field($_POST['full_name']);
        $phone = sanitize_text_field($_POST['phone']);
        $brokerage = sanitize_text_field($_POST['brokerage']);
        $website = esc_url($_POST['website']);
        $city = sanitize_text_field($_POST['city']);
        $dob = sanitize_text_field($_POST['dob']);
        $gender = sanitize_text_field($_POST['gender']);
        $designation = sanitize_text_field($_POST['designation']);
        $current_user = wp_get_current_user();

        $profile_picture = custom_agent_handle_file_upload('profile_picture');
        $agent_resume = custom_agent_handle_file_upload('agent_resume');

        $user_password = $user_login;

        $user_id = wp_create_user($user_login, $user_password, $user_email);

        if (!is_wp_error($user_id)) {
            wp_update_user(array('ID' => $user_id, 'role' => 'agent'));
            add_user_meta($user_id, 'referred_by', $current_user->ID);
            add_user_meta($user_id, 'agent_status', 'pending');
            add_user_meta($user_id, 'full_name', $full_name);
            add_user_meta($user_id, 'phone', $phone);
            add_user_meta($user_id, 'brokerage', $brokerage);
            add_user_meta($user_id, 'website', $website);
            add_user_meta($user_id, 'city', $city);
            add_user_meta($user_id, 'dob', $dob);
            add_user_meta($user_id, 'gender', $gender);
            add_user_meta($user_id, 'designation', $designation);
            if ($profile_picture) {
                add_user_meta($user_id, 'profile_picture', $profile_picture);
            }
            if ($agent_resume) {
                add_user_meta($user_id, 'agent_resume', $agent_resume);
            }
            $_SESSION['agent_form_msg'] = '<div class="alert alert-success"><p>Agent added successfully.</p></div>';
        } else {
            $_SESSION['agent_form_msg'] = '<div class="alert alert-danger"><p>Error: ' . $user_id->get_error_message() . '</p></div>';
        }

        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }
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

// Display referred agents table
function custom_agent_display_referred_agents_table()
{
    $current_user_id = get_current_user_id();
    $args = array(
        'meta_key' => 'referred_by',
        'meta_value' => $current_user_id,
        'role' => 'agent'
    );
    $user_query = new WP_User_Query($args);

    echo '
        <div class="col-md-12">
        <div class="card shadow-lg px-md-3">
        <div class="card-header bg-white">
        <h2>Referred agents list</h2>
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
                            <td><img src="' . esc_url(get_user_meta($user->ID, 'profile_picture', true)) . '" width="50" height="50" /></td>
                            <td><a href="' . esc_url(get_user_meta($user->ID, 'agent_resume', true)) . '">Download</a></td>
                            </tr>';
        }
    } else {
        echo '<tr>
        <td colspan="12">No Agents Found</td>
        </tr>';
    }
    echo '</tbody></table></div></div></div></div>';
}

?>