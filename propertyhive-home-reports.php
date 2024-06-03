<?php
/**
 * Plugin Name: Property Hive Home Reports Add On
 * Plugin Uri: https://wp-property-hive.com/addons/home-reports/
 * Description: Add On for Property Hive allowing users to save add Home Reports, a legal requirement for properties in Scotland
 * Version: 1.0.6
 * Author: PropertyHive
 * Author URI: https://wp-property-hive.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'PH_Home_Reports' ) ) :

final class PH_Home_Reports {

    /**
     * @var string
     */
    public $version = '1.0.6';

    /**
     * @var Property Hive The single instance of the class
     */
    protected static $_instance = null;

    /**
     * @var string
     */
    public $id = '';

    /**
     * @var string
     */
    public $label = '';
    
    /**
     * Main Property Hive Home Reports Instance
     *
     * Ensures only one instance of Property Hive Home Reports is loaded or can be loaded.
     *
     * @static
     * @return Property Hive Home Reports - Main instance
     */
    public static function instance() 
    {
        if ( is_null( self::$_instance ) ) 
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {

        $this->id    = 'home-reports';
        $this->label = __( 'Home Reports', 'propertyhive' );

        // Define constants
        $this->define_constants();

        // Include required files
        $this->includes();

        add_action( 'admin_notices', array( $this, 'home_reports_error_notices') );

        add_filter( 'propertyhive_settings_tabs_array', array( $this, 'add_settings_tab' ), 19 );
        add_action( 'propertyhive_settings_' . $this->id, array( $this, 'output' ) );
        add_action( 'propertyhive_settings_save_' . $this->id, array( $this, 'save' ) );

        add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), array( $this, 'plugin_add_settings_link' ) );

        add_filter( 'propertyhive_property_media_meta_boxes', array( $this, 'add_home_reports_meta_box' ) );
        add_action( 'propertyhive_process_property_meta', array( $this, 'save_home_reports_meta_box' ), 1, 2 );

        add_filter( 'propertyhive_single_property_actions', array( $this, 'add_home_report_to_actions' ) );

        add_action( "propertyhive_property_imported_dezrez_json", array( $this, 'import_dezrez_json_home_reports' ), 10, 2 );
        add_action( "propertyhive_property_imported_vebra_api_xml", array( $this, 'import_vebra_api_xml_home_reports' ), 10, 2 );
        add_filter( "propertyhive_rtdf_property_due_import", array( $this, 'change_rtdf_media_type_of_home_reports' ) );
        add_action( "propertyhive_property_imported_rtdf", array( $this, 'import_rtdf_home_reports' ), 10, 2 );

        $current_settings = get_option( 'propertyhive_home_reports', array() );

        if ( !isset($current_settings['include_in_portal_feeds']) || ( isset($current_settings['include_in_portal_feeds']) && $current_settings['include_in_portal_feeds'] == 1 ) )
        {
            add_filter( 'ph_rtdf_send_request_data', array( $this, 'send_home_reports_to_rtdf' ), 10, 2 );
            add_filter( 'ph_zoopla_rtdf_send_request_data', array( $this, 'send_home_reports_to_zoopla_rtdf' ), 10, 2 );
        }

        if ( isset($current_settings['data_capture']) && $current_settings['data_capture'] == 1 )
        {
            add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        }

        add_action( 'wp_ajax_propertyhive_request_home_report', array( $this, 'ajax_propertyhive_home_report_request' ) );
        add_action( 'wp_ajax_nopriv_propertyhive_request_home_report', array( $this, 'ajax_propertyhive_home_report_request' ) );
    }

    public function ajax_propertyhive_home_report_request()
    {
        global $post;
        
        $return = array();
        
        // Validate
        $errors = array();
        $form_controls = array();

        if ( ! isset( $_POST['property_id'] ) || ( isset( $_POST['property_id'] ) && empty( $_POST['property_id'] ) ) )
        {
            $errors[] = __( 'Property ID is a required field and must be supplied when requesting a Home Report', 'propertyhive' ) . ': ' . $key;
        }
        else
        {
            $form_controls = $this->data_capture_form_fields();

            $form_controls = apply_filters( 'propertyhive_property_home_report_form_fields', $form_controls, $property->id );

        }
        
        foreach ( $form_controls as $key => $control )
        {
            if ( isset( $control ) && isset( $control['required'] ) && $control['required'] === TRUE )
            {
                // This field is mandatory. Lets check we received it in the post
                if ( ! isset( $_POST[$key] ) || ( isset( $_POST[$key] ) && empty( $_POST[$key] ) ) )
                {
                    $errors[] = __( 'Missing required field', 'propertyhive' ) . ': ' . $key;
                }
            }
            if ( isset( $control['type'] ) && $control['type'] == 'email' && isset( $_POST[$key] ) && ! empty( $_POST[$key] ) && ! is_email( $_POST[$key] ) )
            {
                $errors[] = __( 'Invalid email address provided', 'propertyhive' ) . ': ' . $key;
            }
            if ( in_array( $key, array('recaptcha', 'recaptcha-v3') ) )
            {
                $errors = $this->check_recaptcha_form_response($errors, $key, $control);
            }
            if ( $key == 'hCaptcha' )
            {
                $secret = isset( $control['secret'] ) ? $control['secret'] : '';
                $response = isset( $_POST['h-captcha-response'] ) ? ph_clean($_POST['h-captcha-response']) : '';

                $response = wp_remote_post(
                    'https://hcaptcha.com/siteverify',
                    array(
                        'method' => 'POST',
                        'body' => array( 'secret' => $secret, 'response' => $response ),
                    )
                );

                if ( is_wp_error( $response ) )
                {
                    $errors[] = $response->get_error_message();
                }
                else
                {
                    $response = json_decode($response['body'], TRUE);
                    if ( $response === FALSE )
                    {
                        $errors[] = 'Error decoding response from hCaptcha check';
                    }
                    else
                    {
                        if ( isset($response['success']) && $response['success'] == true )
                        {

                        }
                        else
                        {
                            $errors[] = 'Failed hCaptcha validation';
                        }
                    }
                }
            }
        }

        if ( 
            get_option( 'propertyhive_property_enquiry_form_disclaimer', '' ) != '' &&
            ( 
                !isset( $_POST['disclaimer'] ) || 
                ( 
                    isset( $_POST['disclaimer'] ) && empty( $_POST['disclaimer'] ) 
                ) 
            )
        )
        {
            $errors[] = __( 'Missing required field', 'propertyhive' ) . ': disclaimer';
        }
        
        if ( !empty($errors) )
        {
            // Failed validation
            
            $return['success'] = false;
            $return['reason'] = 'validation';
            $return['errors'] = $errors;
        }
        else
        {
            // Passed validation
            $property_ids = explode("|", ph_clean($_POST['property_id']));
            
            // Get recipient email address
            $to = '';
            
            // Try and get office's email address first, else fallback to admin email
            $office_id = get_post_meta((int)$property_ids[0], '_office_id', TRUE);
            if ( $office_id != '' )
            {
                if ( get_post_type( $office_id ) == 'office' )
                {
                    $property_department = get_post_meta((int)$property_ids[0], '_department', TRUE);
                    
                    $fields_to_check = array();
                    switch ( $property_department )
                    {
                        case "residential-sales":
                        {
                            $fields_to_check[] = '_office_email_address_sales';
                            $fields_to_check[] = '_office_email_address_lettings';
                            $fields_to_check[] = '_office_email_address_commercial';
                            break;
                        }
                        case "residential-lettings":
                        {
                            $fields_to_check[] = '_office_email_address_lettings';
                            $fields_to_check[] = '_office_email_address_sales';
                            $fields_to_check[] = '_office_email_address_commercial';
                            break;
                        }
                        case "commercial":
                        {
                            $fields_to_check[] = '_office_email_address_commercial';
                            $fields_to_check[] = '_office_email_address_lettings';
                            $fields_to_check[] = '_office_email_address_sales';
                            break;
                        }
                        default:
                        {
                            $fields_to_check[] = '_office_email_address_' . str_replace("residential-", "", $property_department);
                            $fields_to_check[] = '_office_email_address_sales';
                            $fields_to_check[] = '_office_email_address_lettings';
                            $fields_to_check[] = '_office_email_address_commercial';
                            break;
                        }
                    }
                    
                    foreach ( $fields_to_check as $field_to_check )
                    {
                        $to = get_post_meta($office_id, $field_to_check, TRUE);
                        if ( $to != '' )
                        {
                            break;
                        }
                    }
                }
            }
            if ( $to == '' )
            {
                $to = get_option( 'admin_email' );
            }

            //if ( count($property_ids) == 1 )
            //{
                $subject = __( 'New Home Report Request', 'propertyhive' ) . ': ' . get_the_title( (int)$property_ids[0] );
            /*}
            else
            {
                $subject = __( 'Multiple Property Enquiry', 'propertyhive' ) . ': ' . count($property_ids) . ' Properties';
            }*/
            $message = __( "You have received a Home Report request via your website. Please find details of the request below", 'propertyhive' ) . "\n\n";
            
            $message .= ( count($property_ids) > 1 ? __( 'Properties', 'propertyhive' ) : __( 'Property', 'propertyhive' ) ) . ":\n";
            foreach ( $property_ids as $property_id )
            {
                $property = new PH_Property((int)$property_id);
                $message .= apply_filters( 'propertyhive_home_report_request_property_output', $property->get_formatted_full_address() . "\n" . $property->get_formatted_price() . "\n" . get_permalink( (int)$property_id ), (int)$property_id ) . "\n\n";
            }

            unset($form_controls['action']);
            unset($_POST['action']);
            unset($form_controls['property_id']); // Unset so the field doesn't get shown in the enquiry details
            
            foreach ($form_controls as $key => $control)
            {
                if ( isset($control['type']) && $control['type'] == 'html' ) { continue; }

                $label = ( isset($control['label']) ) ? $control['label'] : $key;
                $label = ( isset($control['email_label']) ) ? $control['email_label'] : $label;
                $value = ( isset($_POST[$key]) ) ? sanitize_textarea_field($_POST[$key]) : '';

                $message .= strip_tags($label) . ": " . strip_tags($value) . "\n";
            }

            if ( 
                apply_filters('propertyhive_home_report_request_email_show_manage_link', true) &&
                count($property_ids) == 1 &&
                get_option( 'propertyhive_module_disabled_enquiries', '' ) != 'yes' &&
                get_option( 'propertyhive_store_property_enquiries', 'yes' ) == 'yes'
            )
            {
                $post_type_object = get_post_type_object( 'property' );
                $property_enquiries_url = admin_url( sprintf( $post_type_object->_edit_link . '&action=edit', (int)$property_ids[0] ) ) . '#propertyhive-property-enquiries';
                $message .= "\n" . __( "To manage this enquiry please visit the following URL", 'propertyhive' ) . ':' . "\n\n";
                $message .= $property_enquiries_url;
            }

            $from_email_address = get_option('propertyhive_email_from_address', '');
            if ( $from_email_address == '' )
            {
                $from_email_address = get_option('admin_email');
            }
            if ( $from_email_address == '' )
            {
                // Should never get here
                $from_email_address = $_POST['email_address'];
            }

            $headers = array();
            if ( isset($_POST['name']) && ! empty($_POST['name']) )
            {
                $headers[] = 'From: ' . html_entity_decode(ph_clean( $_POST['name'] )) . ' <' . sanitize_email( $from_email_address ) . '>';
            }
            else
            {
                $headers[] = 'From: <' . sanitize_email( $from_email_address ) . '>';
            }
            if ( isset($_POST['email_address']) && sanitize_email( $_POST['email_address'] ) != '' )
            {
                $headers[] = 'Reply-To: ' . sanitize_email( $_POST['email_address'] );
            }

            $to = apply_filters( 'propertyhive_home_report_request_to', $to, $property_ids );
            $subject = apply_filters( 'propertyhive_home_report_request_subject', $subject, $property_ids );
            $message = apply_filters( 'propertyhive_home_report_request_body', $message, $property_ids );
            $headers = apply_filters( 'propertyhive_home_report_request_headers', $headers, $property_ids );

            do_action( 'propertyhive_before_home_report_request_sent' );

            $sent = wp_mail( $to, $subject, $message, $headers );

            do_action( 'propertyhive_after_home_report_request_sent' );
            
            if ( ! $sent )
            {
                $return['success'] = false;
                $return['reason'] = 'nosend';
                $return['errors'] = $errors;
            }
            else
            {
                $return['success'] = true;

                $enquiry_post_id = '';
                
                if ( get_option( 'propertyhive_store_property_enquiries', 'yes' ) == 'yes' )
                {
                    $title = __( 'Home Report Request', 'propertyhive' ) . ': ' . get_the_title( (int)$property_ids[0] );
                    if ( isset($_POST['name']) && ! empty($_POST['name']) )
                    {
                        $title .= __( ' from ', 'propertyhive' ) . ph_clean($_POST['name']);
                    }
                    
                    $enquiry_post = array(
                      'post_title'    => $title,
                      'post_content'  => '',
                      'post_type'  => 'enquiry',
                      'post_status'   => 'publish',
                      'comment_status'    => 'closed',
                      'ping_status'    => 'closed',
                    );
                    
                    // Insert the post into the database
                    $enquiry_post_id = wp_insert_post( $enquiry_post );
                    
                    add_post_meta( $enquiry_post_id, '_status', 'open' );
                    add_post_meta( $enquiry_post_id, '_source', 'website' );
                    add_post_meta( $enquiry_post_id, '_negotiator_id', '' );
                    add_post_meta( $enquiry_post_id, '_office_id', $office_id );
                    
                    foreach ($_POST as $key => $value)
                    {
                        if ( $key == 'property_id' )
                        {
                            foreach ( $property_ids as $property_id )
                            {
                                add_post_meta( $enquiry_post_id, $key, (int)$property_id );
                            }
                        }
                        else
                        {
                            add_post_meta( $enquiry_post_id, $key, sanitize_textarea_field($value) );
                        }
                    }
                }

                do_action('propertyhive_home_report_request_sent', $_POST, $to, $enquiry_post_id);

                // send email to requester containing link to Home Report
                $to = sanitize_email( $_POST['email_address'] );

                $subject = __( 'Home Report for', 'propertyhive' ) . ' ' . get_the_title($property_ids[0]);
                $subject = apply_filters( 'propertyhive_home_report_response_subject', $subject, $property_ids );

                $message = 'Hi ' . $_POST['name'] . "\n\n";
                $message .= 'Following your recent request for a Home Report for property ' . get_the_title($property_ids[0]) . ', I have pleasure in attaching it to this email.' . "\n\n";
                $message .= 'Should you have any questions regarding the attached or require further information please do not hesitate to get in touch.' . "\n\n";
                $message .= get_bloginfo( 'name' );
                $message = apply_filters( 'propertyhive_home_report_response_body', $message, $property_ids );

                $headers = array();
                $from_email_address = get_option('propertyhive_email_from_address', '');
                if ( $from_email_address == '' )
                {
                    $from_email_address = get_option('admin_email');
                }
                $headers[] = 'From: ' . html_entity_decode(ph_clean( get_bloginfo( 'name' ) )) . ' <' . sanitize_email( $from_email_address ) . '>';
                $headers = apply_filters( 'propertyhive_home_report_response_headers', $headers, $property_ids );

                $attachments = array();
                $home_reports = get_post_meta( $property_ids[0], '_home_reports', TRUE );
                foreach ( $home_reports as $attachment_id )
                {
                    $attachments[] = get_attached_file( $attachment_id );
                }

                wp_mail( $to, $subject, $message, $headers, $attachments );
            }
        }
        
        header( 'Content-Type: application/json; charset=utf-8' );
        echo json_encode( $return );
        
        // Quit out
        die();
    }

    public function load_scripts() 
    {
        $assets_path = str_replace( array( 'http:', 'https:' ), '', untrailingslashit( plugins_url( '/', __FILE__ ) ) ) . '/assets/';

        wp_register_script( 
            'ph-home-reports', 
            $assets_path . 'js/ph-home-reports.js', 
            array(), 
            PH_HOME_REPORTS_VERSION,
            true
        );

        wp_localize_script( 'ph-home-reports', 'propertyhive_home_reports_params', array( 
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ) );

        if ( is_singular('property') )
        {
            wp_enqueue_script('ph-home-reports');
        }
    }

    public function plugin_add_settings_link( $links )
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=ph-settings&tab=home-reports') . '">' . __( 'Settings' ) . '</a>';
        array_push( $links, $settings_link );
        return $links;
    }

    public function send_home_reports_to_rtdf($request_data, $post_id)
    {
        $attachment_ids = get_post_meta( $post_id, '_home_reports', TRUE );

        if ( is_array($attachment_ids) && !empty($attachment_ids) )
        {
            $i = 50;
            foreach ($attachment_ids as $attachment_id)
            {
                $url = wp_get_attachment_url( $attachment_id );
                if ($url !== FALSE)
                {
                    $media = array(
                        'media_type' => 3,
                        'media_url' => $url,
                        'caption' => 'Home Report',
                        'sort_order' => $i,
                    );

                    $request_data['property']['media'][] = $media;

                    ++$i;
                }
            }
        }

        return $request_data;
    }

    public function send_home_reports_to_zoopla_rtdf($request_data, $post_id)
    {
        $attachment_ids = get_post_meta( $post_id, '_home_reports', TRUE );

        if ( is_array($attachment_ids) && !empty($attachment_ids) )
        {
            foreach ($attachment_ids as $attachment_id)
            {
                $url = wp_get_attachment_url( $attachment_id );
                if ($url !== FALSE)
                {
                    $media = array(
                        'url' => $url,
                        'type' => 'brochure',
                        'caption' => 'Home Report',
                    );

                    $request_data['content'][] = $media;
                }
            }
        }

        return $request_data;
    }

    public function import_dezrez_json_home_reports($post_id, $property)
    {
        $media_ids = array();
        $new = 0;
        $existing = 0;
        $deleted = 0;
        $previous_media_ids = get_post_meta( $post_id, '_home_reports', TRUE );
        if ( isset($property['Documents']) && !empty($property['Documents']) )
        {
            foreach ( $property['Documents'] as $document )
            {
                if ( 
                    isset($document['Url']) && $document['Url'] != ''
                    &&
                    (
                        substr( strtolower($document['Url']), 0, 2 ) == '//' || 
                        substr( strtolower($document['Url']), 0, 4 ) == 'http'
                    )
                    &&
                    isset($document['DocumentType']['SystemName']) && $document['DocumentType']['SystemName'] == 'Document'
                    &&
                    isset($document['DocumentSubType']['SystemName']) && $document['DocumentSubType']['SystemName'] == 'HomeReport'
                )
                {
                    // This is a URL
                    $url = $document['Url'];
                    $description = '';
                    
                    $filename = basename( $url );

                    // Check, based on the URL, whether we have previously imported this media
                    $imported_previously = false;
                    $imported_previously_id = '';
                    if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
                    {
                        foreach ( $previous_media_ids as $previous_media_id )
                        {
                            if ( get_post_meta( $previous_media_id, '_imported_url', TRUE ) == $url )
                            {
                                $imported_previously = true;
                                $imported_previously_id = $previous_media_id;
                                break;
                            }
                        }
                    }

                    if ($imported_previously)
                    {
                        $media_ids[] = $imported_previously_id;

                        if ( $description != '' )
                        {
                            $my_post = array(
                                'ID'             => $imported_previously_id,
                                'post_title'     => $description,
                            );

                            // Update the post into the database
                            wp_update_post( $my_post );
                        }

                        ++$existing;
                    }
                    else
                    {
                        $tmp = download_url( $url );

                        $exploded_filename = explode(".", $filename);
                        $ext = 'pdf';
                        if (strlen($exploded_filename[count($exploded_filename)-1]) == 3)
                        {
                            $ext = $exploded_filename[count($exploded_filename)-1];
                        }
                        $name = $property['RoleId'] . '_' . $document['Id'] . '.' . $ext;

                        $file_array = array(
                            'name' => $name,
                            'tmp_name' => $tmp
                        );

                        // Check for download errors
                        if ( is_wp_error( $tmp ) ) 
                        {
                            @unlink( $file_array[ 'tmp_name' ] );

                            //$this->add_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['RoleId'] );
                        }
                        else
                        {
                            $id = media_handle_sideload( $file_array, $post_id, $description );
                            
                            // Check for handle sideload errors.
                            if ( is_wp_error( $id ) ) 
                            {
                                @unlink( $file_array['tmp_name'] );
                                
                                //$this->add_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['RoleId'] );
                            }
                            else
                            {
                                $media_ids[] = $id;

                                update_post_meta( $id, '_imported_url', $url);

                                ++$new;
                            }
                        }
                    }
                }
            }
        }
        update_post_meta( $post_id, '_home_reports', $media_ids );

        // Loop through $previous_media_ids, check each one exists in $media_ids, and if it doesn't then delete
        if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
        {
            foreach ( $previous_media_ids as $previous_media_id )
            {
                if ( !in_array($previous_media_id, $media_ids) )
                {
                    if ( wp_delete_attachment( $previous_media_id, TRUE ) !== FALSE )
                    {
                        ++$deleted;
                    }
                }
            }
        }
    }

    public function import_vebra_api_xml_home_reports($post_id, $property)
    {
        $media_ids = array();
        $new = 0;
        $existing = 0;
        $deleted = 0;
        $previous_media_ids = get_post_meta( $post_id, '_home_reports', TRUE );

        $property_attributes = $property->attributes();

        if (isset($property->files) && !empty($property->files))
        {
            foreach ($property->files as $files)
            {
                if (!empty($files->file))
                {
                    foreach ($files->file as $file)
                    {
                        $file_attributes = $file->attributes();

                        if (
                            (string)$file_attributes['type'] == '8'
                            &&
                            (
                                substr( strtolower((string)$file->url), 0, 2 ) == '//' ||
                                substr( strtolower((string)$file->url), 0, 4 ) == 'http'
                            )
                            &&
                            substr(strtolower((string)$file->name), 0, 11) === 'home report'
                        )
                        {
                            $url = (string)$file->url;
                            $description = (string)$file->name;

                            $filename = basename( $url );

                            // Check, based on the URL, whether we have previously imported this media
                            $imported_previously = false;
                            $imported_previously_id = '';
                            if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
                            {
                                foreach ( $previous_media_ids as $previous_media_id )
                                {
                                    if ( get_post_meta( $previous_media_id, '_imported_url', TRUE ) == $url )
                                    {
                                        $imported_previously = true;
                                        $imported_previously_id = $previous_media_id;
                                        break;
                                    }
                                }
                            }

                            if ($imported_previously)
                            {
                                $media_ids[] = $imported_previously_id;

                                if ( $description != '' )
                                {
                                    $my_post = array(
                                        'ID'             => $imported_previously_id,
                                        'post_title'     => $description,
                                    );

                                    wp_update_post( $my_post );
                                }

                                ++$existing;
                            }
                            else
                            {
                                $tmp = download_url( $url );

                                $file_id = (string)$file_attributes['id'];
                                $property_id = (string)$property_attributes['id'];

                                $exploded_filename = explode(".", $filename);
                                $ext = 'pdf';
                                if (strlen($exploded_filename[count($exploded_filename)-1]) == 3)
                                {
                                    $ext = $exploded_filename[count($exploded_filename)-1];
                                }
                                $name = $property_id . '_' . $file_id . '.' . $ext;

                                $file_array = array(
                                    'name' => $name,
                                    'tmp_name' => $tmp
                                );

                                // Check for download errors
                                if ( is_wp_error( $tmp ) )
                                {
                                    @unlink( $file_array[ 'tmp_name' ] );
                                }
                                else
                                {
                                    $id = media_handle_sideload( $file_array, $post_id, $description );

                                    // Check for handle sideload errors.
                                    if ( is_wp_error( $id ) )
                                    {
                                        @unlink( $file_array['tmp_name'] );
                                    }
                                    else
                                    {
                                        $media_ids[] = $id;

                                        update_post_meta( $id, '_imported_url', $url);

                                        ++$new;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        update_post_meta( $post_id, '_home_reports', $media_ids );

        // Loop through $previous_media_ids, check each one exists in $media_ids, and if it doesn't then delete
        if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
        {
            foreach ( $previous_media_ids as $previous_media_id )
            {
                if ( !in_array($previous_media_id, $media_ids) )
                {
                    if ( wp_delete_attachment( $previous_media_id, TRUE ) !== FALSE )
                    {
                        ++$deleted;
                    }
                }
            }
        }
    }

    public function change_rtdf_media_type_of_home_reports($property)
    {
        $new_media = array();

        if ( isset($property['media']) && is_array($property['media']) && !empty($property['media']) )
        {
            foreach ( $property['media'] as $image )
            {
                if ( 
                    isset($image['media_url']) && $image['media_url'] != ''
                    &&
                    (
                        substr( strtolower($image['media_url']), 0, 2 ) == '//' || 
                        substr( strtolower($image['media_url']), 0, 4 ) == 'http'
                    )
                    &&
                    isset($image['media_type']) && $image['media_type'] == 3
                    &&
                    isset($image['caption']) && strpos( strtolower($image['caption']), 'home report' ) !== FALSE
                )
                {
                    // 111 is used by function below
                    $image['media_type'] = 111;
                }

                $new_media[] = $image;
            }
        }

        $property['media'] = $new_media;

        return $property;
    }

    public function import_rtdf_home_reports($post_id, $property)
    {
        $media_ids = array();
        $new = 0;
        $existing = 0;
        $deleted = 0;
        $previous_media_ids = get_post_meta( $post_id, '_home_reports', TRUE );
        if ( isset($property['media']) && is_array($property['media']) && !empty($property['media']) )
        {
            foreach ( $property['media'] as $image )
            {
                if ( 
                    isset($image['media_url']) && $image['media_url'] != ''
                    &&
                    (
                        substr( strtolower($image['media_url']), 0, 2 ) == '//' || 
                        substr( strtolower($image['media_url']), 0, 4 ) == 'http'
                    )
                    &&
                    isset($image['media_type']) && $image['media_type'] == 111 // Used 111 as a random number
                    &&
                    isset($image['caption']) && strpos( strtolower($image['caption']), 'home report' ) !== FALSE
                )
                {
                    // This is a URL
                    $url = $image['media_url'];
                    $description = $image['caption'];
                    $modified = ( isset($image['media_update_date']) && !empty($image['media_update_date']) ) ? $image['media_update_date'] : '';
                    
                    $filename = basename( $url );

                    // Check, based on the URL, whether we have previously imported this media
                    $imported_previously = false;
                    $imported_previously_id = '';
                    if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
                    {
                        foreach ( $previous_media_ids as $previous_media_id )
                        {
                            if ( 
                                get_post_meta( $previous_media_id, '_imported_url', TRUE ) == $url
                                &&
                                (
                                    get_post_meta( $previous_media_id, '_modified', TRUE ) == '' 
                                    ||
                                    (
                                        get_post_meta( $previous_media_id, '_modified', TRUE ) != '' &&
                                        get_post_meta( $previous_media_id, '_modified', TRUE ) == $modified
                                    )
                                )
                            )
                            {
                                $imported_previously = true;
                                $imported_previously_id = $previous_media_id;
                                break;
                            }
                        }
                    }

                    if ($imported_previously)
                    {
                        $media_ids[] = $imported_previously_id;

                        if ( $description != '' )
                        {
                            $my_post = array(
                                'ID'             => $imported_previously_id,
                                'post_title'     => $description,
                            );

                            // Update the post into the database
                            wp_update_post( $my_post );
                        }

                        ++$existing;
                    }
                    else
                    {
                        $tmp = download_url( $url );

                        $file_array = array(
                            'name' => $filename,
                            'tmp_name' => $tmp
                        );

                        // Check for download errors
                        if ( is_wp_error( $tmp ) ) 
                        {
                            @unlink( $file_array[ 'tmp_name' ] );

                            //$this->add_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['RoleId'] );
                        }
                        else
                        {
                            $id = media_handle_sideload( $file_array, $post_id, $description );
                            
                            // Check for handle sideload errors.
                            if ( is_wp_error( $id ) ) 
                            {
                                @unlink( $file_array['tmp_name'] );
                                
                                //$this->add_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['RoleId'] );
                            }
                            else
                            {
                                $media_ids[] = $id;

                                update_post_meta( $id, '_imported_url', $url);
                                update_post_meta( $id, '_modified', $modified);

                                ++$new;
                            }
                        }
                    }
                }
            }
        }
        update_post_meta( $post_id, '_home_reports', $media_ids );

        // Loop through $previous_media_ids, check each one exists in $media_ids, and if it doesn't then delete
        if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
        {
            foreach ( $previous_media_ids as $previous_media_id )
            {
                if ( !in_array($previous_media_id, $media_ids) )
                {
                    if ( wp_delete_attachment( $previous_media_id, TRUE ) !== FALSE )
                    {
                        ++$deleted;
                    }
                }
            }
        }
    }

    /**
     * Define PH Home Reports Constants
     */
    private function define_constants() 
    {
        define( 'PH_HOME_REPORTS_PLUGIN_FILE', __FILE__ );
        define( 'PH_HOME_REPORTS_VERSION', $this->version );
    }

    private function includes()
    {
        //include_once( dirname( __FILE__ ) . "/includes/class-ph-home-reports-install.php" );
    }

    /**
     * Output error message if core Property Hive plugin isn't active
     */
    public function home_reports_error_notices() 
    {
        if (!is_plugin_active('propertyhive/propertyhive.php'))
        {
            $message = __( "The Property Hive plugin must be installed and activated before you can use the Property Hive Home Reports add-on", 'propertyhive' );
            echo"<div class=\"error\"> <p>$message</p></div>";
        }
    }

    public function add_home_reports_meta_box( $meta_boxes )
    {
        $meta_boxes[23] = array(
            'id' => 'propertyhive-property-home-reports',
            'title' => __( 'Property Home Reports', 'propertyhive' ),
            'callback' => array( $this, 'output_meta_box' ),
            'screen' => 'property',
            'context' => 'normal',
            'priority' => 'high'
        );

        return $meta_boxes;
    }

    public function output_meta_box( $post )
    {
        $thumbnail_width = get_option( 'thumbnail_size_w', 150 );
        $thumbnail_height = get_option( 'thumbnail_size_h', 150 );
        
        echo '<div class="propertyhive_meta_box">';
        
            echo '<div class="options_group">';
            
                echo '<div class="media_grid" id="property_home_reports_grid"><ul>';
                
                $home_reports = get_post_meta($post->ID, '_home_reports', TRUE);
                $input_value = '';
                if (is_array($home_reports) && !empty($home_reports))
                {                    
                    $input_value = implode(",", $home_reports);
                    
                    foreach ($home_reports as $home_reports_attachment_id)
                    {
                        $type = get_post_mime_type($home_reports_attachment_id);
                        $icon = 'text.png';
                        
                        switch ($type)
                        {
                            case "application/pdf":
                            case "application/x-pdf":
                            {
                                $icon = 'pdf.png';
                                break;
                            }
                            case "application/msword":
                            case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                            {
                                $icon = 'word.png';
                                break;
                            }
                            case "text/csv":
                            case "application/vnd.ms-excel":
                            case "text/csv":
                            {
                                $icon = 'excel.png';
                                break;
                            }
                        }
                        
                        echo '<li id="home_report_' . $home_reports_attachment_id . '">';
                            echo '<div class="hover"><div class="attachment-delete"><a href=""></a></div><div class="attachment-edit"><a href=""></a></div></div>';
                            echo '<a href="' . wp_get_attachment_url( $home_reports_attachment_id ) . '" target="_blank"><img src="' . PH()->plugin_url() . '/assets/images/filetypes/' . $icon . '" alt="" width="' . $thumbnail_width . '" height="' . $thumbnail_height . '"></a>';
                        echo '</li>';
                    }
                }
                else
                {
                    //echo '<p>' . __( 'No home reports have been uploaded yet', 'propertyhive' ) . '</p>';
                }
                
                echo '</ul></div>';
                
                echo '<a href="" class="button button-primary ph_upload_home_report_button">' . __('Add Home Reports', 'propertyhive') . '</a>';
    
                do_action('propertyhive_property_home_reports_fields');
               
                echo '<input type="hidden" name="home_report_attachment_ids" id="home_report_attachment_ids" value="' . $input_value . '">';
               
            echo '</div>';
        
        echo '</div>';
        
        echo '<script>
              // Uploading files
              var file_frame_home_reports;
              
              //var sortable_options = 
             
              jQuery(document).ready(function()
              {
                  jQuery( \'#property_home_reports_grid ul\' ).sortable({
                      update : function (event, ui) {
                            var new_order = \'\';
                            jQuery(\'#home_reports_grid ul\').find(\'li\').each( function () {
                                if (new_order != \'\')
                                {
                                    new_order += \',\';
                                }
                                new_order = new_order + jQuery(this).attr(\'id\').replace(\'home_report_\', \'\');
                            });
                            jQuery(\'#home_report_attachment_ids\').val(new_order);
                      }
                  });
                  jQuery( \'#property_home_reports_grid ul\' ).disableSelection();
                  
                  jQuery(\'body\').on(\'click\', \'#property_home_reports_grid .attachment-delete a\', function()
                  {
                      var container = jQuery(this).parent().parent().parent();
                      var home_report_id = container.attr(\'id\');
                      home_report_id = home_report_id.replace(\'home_report_\', \'\');
                      
                      var attachment_ids = jQuery(\'#home_report_attachment_ids\').val();
                      // Check it\'s not already in the list
                      attachment_ids = attachment_ids.split(\',\');
                      
                      var new_attachment_ids = \'\';
                      for (var i in attachment_ids)
                      {
                          if (attachment_ids[i] != home_report_id)
                          {
                              if (new_attachment_ids != \'\')
                              {
                                  new_attachment_ids += \',\';
                              }
                              new_attachment_ids += attachment_ids[i];
                          }
                      }
                      jQuery(\'#home_report_attachment_ids\').val(new_attachment_ids);
                      
                      container.fadeOut(\'fast\', function()
                      {
                          container.remove();
                      });
                      
                      return false;
                  });
                  
                  jQuery(\'body\').on(\'click\', \'#property_home_reports_grid .attachment-edit a\', function()
                  {
                      
                  });
                  
                  jQuery(\'body\').on(\'click\', \'.ph_upload_home_report_button\', function( event ){
                 
                    event.preventDefault();
                 
                    // If the media frame already exists, reopen it.
                    if ( file_frame_home_reports ) {
                      file_frame_home_reports.open();
                      return;
                    }
                 
                    // Create the media frame.
                    file_frame_home_reports = wp.media.frames.file_frame_home_reports = wp.media({
                      title: jQuery( this ).data( \'uploader_title\' ),
                      button: {
                        text: jQuery( this ).data( \'uploader_button_text\' ),
                      },
                      multiple: true  // Set to true to allow multiple files to be selected
                    });
                 
                    // When an image is selected, run a callback.
                    file_frame_home_reports.on( \'select\', function() {
                        var selection = file_frame_home_reports.state().get(\'selection\');
     
                        selection.map( function( attachment ) {
                     
                            attachment = attachment.toJSON();
                     
                            // Do something with attachment.id and/or attachment.url here
                            console.log(attachment.url);
                            
                            // Add selected images to grid
                            add_home_report_attachment_to_grid(attachment);
                        });
                    });
                 
                    // Finally, open the modal
                    file_frame_home_reports.open();
                  });
              });
              
              function add_home_report_attachment_to_grid(attachment)
              {
                  var attachment_ids = jQuery(\'#home_report_attachment_ids\').val();
                  // Check it\'s not already in the list
                  attachment_ids = attachment_ids.split(\',\');
                  
                  var ok_to_add = true;
                  for (var i in attachment_ids)
                  {
                      if (attachment.id == attachment_ids[i])
                      {
                          ok_to_add = false;
                      }
                  }
                  
                  if (ok_to_add)
                  {
                      // Append to hidden field
                      var new_attachment_ids = attachment_ids;
                      if (new_attachment_ids != \'\')
                      {
                          new_attachment_ids += \',\';
                      }
                      new_attachment_ids += attachment.id;
                      jQuery(\'#home_report_attachment_ids\').val(new_attachment_ids);
                      
                      // Add home report to media grid
                      var mediaHTML = \'\';
                      
                      // get extension and icon
                      var icon = \'text.png\';
                      var attachment_url = attachment.url;
                      attachment_url = attachment_url.split(\'.\');
                      var extension = attachment_url[attachment_url.length-1].toLowerCase();
                      switch (extension)
                      {
                          case \'pdf\':
                          {
                              icon = \'pdf.png\';
                              break;
                          }
                          case \'doc\':
                          case \'docx\':
                          {
                              icon = \'word.png\';
                              break;
                          }
                          case \'csv\':
                          case \'xls\':
                          case \'xlsx\':
                          {
                              icon = \'excel.png\';
                              break;
                          }
                      }
                      
                      mediaHTML += \'<li id="home_report_\' + attachment.id + \'">\';
                      mediaHTML += \'<div class="hover"><div class="attachment-delete"><a href=""></a></div><div class="attachment-edit"><a href=""></a></div></div>\';
                      mediaHTML += \'<img src="' . PH()->plugin_url() . '/assets/images/filetypes/\' + icon + \'" alt="" width="' . $thumbnail_width . '" height="' . $thumbnail_height . '"></li>\';
                      
                      jQuery(\'#property_home_reports_grid ul\').append(mediaHTML);
                  }
              }
        </script>';
    }

    public function save_home_reports_meta_box( $post_id, $post ) 
    {
        $home_reports = array();
        if (trim($_POST['home_report_attachment_ids'], ',') != '')
        {
            $home_reports = explode( ",", trim($_POST['home_report_attachment_ids'], ',') );
        }
        update_post_meta( $post_id, '_home_reports', $home_reports );
    }

    public function add_home_report_to_actions( $actions )
    {
        global $property;

        $home_report_ids = $property->_home_reports;
        if ( is_array($home_report_ids) )
        {
            $home_report_ids = array_filter( $home_report_ids );
        }
        else
        {
            $home_report_ids = array();
        }

        if ( !empty( $home_report_ids ) )
        {
            $current_settings = get_option( 'propertyhive_home_reports', array() );

            foreach ($home_report_ids as $home_report_id)
            {
                if ( isset($current_settings['data_capture']) && $current_settings['data_capture'] == '1' )
                {
                    $actions[] = array(
                        'href' => 'javascript:;',
                        'label' => __( 'View Home Report', 'propertyhive' ),
                        'class' => 'action-home-report',
                        'attributes' => array(
                            'data-fancybox' => '',
                            'data-src' => '#homeReport' . $property->ID
                        )
                    );
?>
<!-- LIGHTBOX FORM -->
    <div id="homeReport<?php echo $property->ID; ?>" style="display:none;">
        
        <h2><?php _e( 'Request Home Report', 'propertyhive' ); ?></h2>
        
        <p><?php _e( 'Please complete the form below and the Home Report will be emailed to you.', 'propertyhive' ); ?></p>
        
        <?php $this->data_capture_form(); ?>
        
    </div>
    <!-- END LIGHTBOX FORM -->
<?php
                }
                else
                {
                    $actions[] = array(
                        'href' => wp_get_attachment_url( $home_report_id ),
                        'label' => __( 'View Home Report', 'propertyhive' ),
                        'class' => 'action-home-report',
                        'attributes' => array(
                            'target' => '_blank'
                        )
                    );
                }
            }
        }

        return $actions;
    }

    public function data_capture_form()
    {
        global $property;
        
        $form_controls = $this->data_capture_form_fields();

        $form_controls = apply_filters( 'propertyhive_property_home_report_form_fields', $form_controls, $property->id );

        $form_controls['property_id'] = array(
            'type' => 'hidden',
            'value' => $property->id
        );

        if ( get_option( 'propertyhive_property_enquiry_form_disclaimer', '' ) != '' )
        {
            $disclaimer = get_option( 'propertyhive_property_enquiry_form_disclaimer', '' );

            $form_controls['disclaimer'] = array(
                'type' => 'checkbox',
                'label' => $disclaimer,
                'label_style' => 'width:100%;',
                'required' => true
            );
        }

        $template = locate_template( array('propertyhive/home-report-form.php') );
        if ( !$template )
        {
            include( dirname( PH_HOME_REPORTS_PLUGIN_FILE ) . '/templates/home-report-form.php' );
        }
        else
        {
            include( $template );
        }
    }

    public function data_capture_form_fields()
    {
        global $property;

        if ( is_user_logged_in() )
        {
            $current_user = wp_get_current_user();

            if ( $current_user instanceof WP_User )
            {
                $contact = new PH_Contact( '', $current_user->ID );
            }
        }

        $fields = array();

        $fields['name'] = array(
            'type' => 'text',
            'label' => __( 'Full Name', 'propertyhive' ),
            'show_label' => true,
            'before' => '<div class="control control-name">',
            'required' => true
        );
        if ( is_user_logged_in() )
        {
            $current_user = wp_get_current_user();

            $fields['name']['value'] = $current_user->display_name;
        }

        $fields['email_address'] = array(
            'type' => 'email',
            'label' => __( 'Email Address', 'propertyhive' ),
            'show_label' => true,
            'before' => '<div class="control control-email_address">',
            'required' => true
        );
        if ( is_user_logged_in() )
        {
            $current_user = wp_get_current_user();

            $fields['email_address']['value'] = $current_user->user_email;
        }

        $fields['telephone_number'] = array(
            'type' => 'text',
            'label' => __( 'Number', 'propertyhive' ),
            'show_label' => true,
            'before' => '<div class="control control-telephone_number">',
            'required' => true
        );

        return $fields;
    }

    /**
     * Add a new settings tab to the Property Hive settings tabs array.
     *
     * @param array $settings_tabs Array of Property Hive setting tabs & their labels
     * @return array $settings_tabs Array of Property Hive setting tabs & their labels
     */
    public function add_settings_tab( $settings_tabs ) {
        $settings_tabs[$this->id] = $this->label;
        return $settings_tabs;
    }

    /**
     * Uses the Property Hive admin fields API to output settings.
     *
     * @uses propertyhive_admin_fields()
     * @uses self::get_settings()
     */
    public function output() {

        global $current_section;
        
        propertyhive_admin_fields( self::get_home_reports_settings() );
    }

    /**
     * Get home reports settings
     *
     * @return array Array of settings
     */
    public function get_home_reports_settings() {

        global $post;

        $current_settings = get_option( 'propertyhive_home_reports', array() );

        $settings = array(

            array( 'title' => __( 'Home Reports Settings', 'propertyhive' ), 'type' => 'title', 'desc' => '', 'id' => 'home_reports_settings' )

        );

        $settings[] = array(
            'title'     => __( 'Capture Data Before Emailing Report', 'propertyhive' ),
            'id'        => 'data_capture',
            'type'      => 'checkbox',
            'default'   => ( isset($current_settings['data_capture']) && $current_settings['data_capture'] == 1 ) ? 'yes' : '',
        );

        if ( class_exists('PH_Realtimefeed') || class_exists('PH_Zooplarealtimefeed') )
        {
            $settings[] = array(
                'title'     => __( 'Include Home Reports In Portal Feeds', 'propertyhive' ),
                'id'        => 'include_in_portal_feeds',
                'type'      => 'checkbox',
                'default'   => ( !isset($current_settings['include_in_portal_feeds']) || ( isset($current_settings['include_in_portal_feeds']) && $current_settings['include_in_portal_feeds'] == 1 ) ? 'yes' : ''),
            );
        }

        $settings[] = array( 'type' => 'sectionend', 'id' => 'home_reports_settings');

        return $settings;
    }

    /**
     * Uses the Property Hive options API to save settings.
     *
     * @uses propertyhive_update_options()
     * @uses self::get_settings()
     */
    public function save() {

        $existing_propertyhive_home_reports = get_option( 'propertyhive_home_reports', array() );

        $propertyhive_home_reports = array(
            'data_capture' => ( (isset($_POST['data_capture'])) ? $_POST['data_capture'] : '' ),
            'include_in_portal_feeds' => ( (isset($_POST['include_in_portal_feeds'])) ? $_POST['include_in_portal_feeds'] : '' ),
        );

        $propertyhive_home_reports = array_merge( $existing_propertyhive_home_reports, $propertyhive_home_reports );

        update_option( 'propertyhive_home_reports', $propertyhive_home_reports );
    }
}

endif;

/**
 * Returns the main instance of PH_Home_Reports to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return PH_Home_Reports
 */
function PHHR() {
    return PH_Home_Reports::instance();
}

$PHHR = PHHR();

if( is_admin() && file_exists(  dirname( __FILE__ ) . '/propertyhive-home-reports-update.php' ) )
{
    include_once( dirname( __FILE__ ) . '/propertyhive-home-reports-update.php' );
}