<?php
/**
 * Plugin Name: Property Hive Home Reports Add On
 * Plugin Uri: http://wp-property-hive.com/addons/home-reports/
 * Description: Add On for Property Hive allowing users to save add Home Reports, a legal requirement for properties in Scotland
 * Version: 1.0.4
 * Author: PropertyHive
 * Author URI: http://wp-property-hive.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'PH_Home_Reports' ) ) :

final class PH_Home_Reports {

    /**
     * @var string
     */
    public $version = '1.0.4';

    /**
     * @var Property Hive The single instance of the class
     */
    protected static $_instance = null;
    
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

        add_action( 'admin_init', array( $this, 'show_home_report_settings') );

        add_action( 'admin_notices', array( $this, 'home_reports_error_notices') );

        add_filter( 'propertyhive_property_media_meta_boxes', array( $this, 'add_home_reports_meta_box' ) );
        add_action( 'propertyhive_process_property_meta', array( $this, 'save_home_reports_meta_box' ), 1, 2 );

        add_filter( 'propertyhive_single_property_actions', array( $this, 'add_home_report_to_actions' ) );

        add_action( "propertyhive_property_imported_dezrez_json", array( $this, 'import_dezrez_json_home_reports' ), 10, 2 );

        $current_settings = get_option( 'propertyhive_home_reports', array() );

        if ( !isset($current_settings['include_in_portal_feeds']) || ( isset($current_settings['include_in_portal_feeds']) && $current_settings['include_in_portal_feeds'] == 1 ) )
        {
            add_filter( 'ph_rtdf_send_request_data', array( $this, 'send_home_reports_to_rtdf' ), 10, 2 );
            add_filter( 'ph_zoopla_rtdf_send_request_data', array( $this, 'send_home_reports_to_zoopla_rtdf' ), 10, 2 );
        }
    }

    public function show_home_report_settings()
    {
        if ( class_exists('PH_Realtimefeed') || class_exists('PH_Zooplarealtimefeed') )
        {
            add_filter( 'propertyhive_settings_tabs_array', array( $this, 'add_settings_tab' ), 19 );
            add_action( 'propertyhive_settings_' . $this->id, array( $this, 'output' ) );
            add_action( 'propertyhive_settings_save_' . $this->id, array( $this, 'save' ) );

            add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), array( $this, 'plugin_add_settings_link' ) );
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
            foreach ($home_report_ids as $home_report_id)
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

        return $actions;
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
            'title'     => __( 'Include Home Reports In Portal Feeds', 'propertyhive' ),
            'id'        => 'include_in_portal_feeds',
            'type'      => 'checkbox',
            'default'   => ( !isset($current_settings['include_in_portal_feeds']) || ( isset($current_settings['include_in_portal_feeds']) && $current_settings['include_in_portal_feeds'] == 1 ) ? 'yes' : ''),
        );

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