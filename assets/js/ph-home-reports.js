var ph_hr_is_submitting = false;
var ph_hr_form_obj;
jQuery( function($){

    // Enquiry form being submitted
    $('body').on('submit', 'form[name=\'ph_property_home_report\']', function()
    {
        if (!ph_hr_is_submitting)
        {
            ph_hr_is_submitting = true;

            var data = $(this).serialize() + '&'+$.param({ 'action': 'propertyhive_request_home_report' });

            ph_hr_form_obj = $(this);

            ph_hr_form_obj.find('#hrEnquirySuccess').hide();
            ph_hr_form_obj.find('#hrEnquiryValidation').hide();
            ph_hr_form_obj.find('#hrEnquiryError').hide();

            $.post( propertyhive_home_reports_params.ajax_url, data, function(response) {

                if (response.success == true)
                {
                    if ( propertyhive_home_reports_params.redirect_url && propertyhive_home_reports_params.redirect_url != '' )
                    {
                        window.location.href = propertyhive_home_reports_params.redirect_url;
                    }
                    else
                    {
                        ph_hr_form_obj.find('#hrEnquirySuccess').fadeIn();
                        ph_hr_form_obj.trigger('ph_home_report:success');

                        ph_hr_form_obj.trigger("reset");
                    }
                }
                else
                {
                    console.log(response);
                    if (response.reason == 'validation')
                    {
                        ph_hr_form_obj.find('#hrEnquiryValidation').fadeIn();
                        ph_hr_form_obj.trigger('ph_home_report:validation');
                    }
                    else if (response.reason == 'nosend')
                    {
                        ph_hr_form_obj.find('#hrEnquiryError').fadeIn();
                        ph_hr_form_obj.trigger('ph_home_report:nosend');
                    }
                }

                ph_hr_is_submitting = false;

                if ( typeof grecaptcha != 'undefined' && $( "div.g-recaptcha" ).length > 0 )
                {
                    grecaptcha.reset();
                }

            });
        }

        return false;
    });

});