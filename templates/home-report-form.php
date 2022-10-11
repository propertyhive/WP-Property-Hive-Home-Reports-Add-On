<?php
/**
 * Property home report data capture form
 *
 * @author      PropertyHive
 * @package     PropertyHive/Templates
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>

<form name="ph_property_home_report" class="property-home-report-form" action="" method="post">
    
    <div id="hrEnquirySuccess" style="display:none;" class="alert alert-success alert-box success">
        <?php _e( 'Thank you. Your request has been sent successfully and the Home Report emailed to you.', 'propertyhive' ); ?>
    </div>
    <div id="hrEnquiryError" style="display:none;" class="alert alert-danger alert-box">
        <?php _e( 'An error occurred whilst trying to send your request. Please try again.', 'propertyhive' ); ?>
    </div>
    <div id="hrEnquiryValidation" style="display:none;" class="alert alert-danger alert-box">
        <?php _e( 'Please ensure all required fields have been completed', 'propertyhive' ); ?>
    </div>
    
    <?php foreach ( $form_controls as $key => $field ) : ?>

        <?php ph_form_field( $key, $field ); ?>

    <?php endforeach; ?>

    <input type="submit" value="<?php _e( 'Request Home Report', 'propertyhive' ); ?>">

</form>