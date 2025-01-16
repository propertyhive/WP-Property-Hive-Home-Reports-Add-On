<?php
/**
 * Elementor Property Home Report Link Widget.
 *
 * @since 1.0.0
 */
class Elementor_Property_Home_Report_Link_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'property-home-report-link';
	}

	public function get_title() {
		return __( 'Home Report Link', 'propertyhive' );
	}

	public function get_icon() {
		return 'eicon-document-file';
	}

	public function get_categories() {
		return [ 'property-hive' ];
	}

	public function get_keywords() {
		return [ 'property hive', 'propertyhive', 'property', 'home report', 'pdf' ];
	}

	protected function register_controls() {

		$this->start_controls_section(
			'style_section',
			[
				'label' => __( 'Home Report', 'propertyhive' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'label',
			[
				'label' => __( 'Label', 'propertyhive' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Home Report', 'propertyhive' ),
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'typography',
				'label' => __( 'Typography', 'propertyhive' ),
				'global' => [
					'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
				],
				'selector' => '{{WRAPPER}} a',
			]
		);

		$this->add_control(
			'color',
			[
				'label' => __( 'Colour', 'propertyhive' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'global' => [
				    'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
				],
				'selectors' => [
					'{{WRAPPER}} a' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'text_align',
			[
				'label' => __( 'Alignment', 'propertyhive' ),
				'type' => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => __( 'Left', 'propertyhive' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'propertyhive' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => __( 'Right', 'propertyhive' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'default' => 'center',
				'toggle' => true,
				'selectors' => [
					'{{WRAPPER}}' => 'text-align: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'background_color',
			[
				'label' => __( 'Background Colour', 'propertyhive' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'global' => [
				    'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_SECONDARY,
				],
				'selectors' => [
					'{{WRAPPER}} a' => 'background: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'padding',
			[
				'label' => __( 'Link Padding', 'propertyhive' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px' ],
				'selectors' => [
					'{{WRAPPER}} a' => 'display:inline-block; padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'default' => [
					'top' => 5,
					'right' => 5,
					'bottom' => 5,
					'left' => 5,
					'isLinked' => true,
				],
			]
		);

		$this->end_controls_section();

	}

	protected function render() {

		global $property;

		$settings = $this->get_settings_for_display();

		if ( !isset($property->id) ) {
			return;
		}

		$label = isset($settings['label']) && !empty($settings['label']) ? $settings['label'] : __( 'Home Report', 'propertyhive' );

		$current_settings = get_option( 'propertyhive_home_reports', array() );

		$home_report_ids = $property->_home_reports;
        if ( is_array($home_report_ids) )
        {
            $home_report_ids = array_filter( $home_report_ids );
        }
        else
        {
            $home_report_ids = array();
        }

		if ( !empty($home_report_ids) )
		{
			foreach ( $home_report_ids as $attachment_id )
			{
				if ( isset($current_settings['data_capture']) && $current_settings['data_capture'] == '1' )
                {
                	global $PHHR;
                	echo '<a href="javascript:;" rel="nofollow" data-fancybox data-src="#homeReport' . $property->ID . '">' . $label . '</a>';
?>
<!-- LIGHTBOX FORM -->
    <div id="homeReport<?php echo $property->ID; ?>" style="display:none;">
        
        <h2><?php _e( 'Request Home Report', 'propertyhive' ); ?></h2>
        
        <p><?php _e( 'Please complete the form below and the Home Report will be emailed to you.', 'propertyhive' ); ?></p>
        
        <?php $PHHR->data_capture_form(); ?>
        
    </div>
    <!-- END LIGHTBOX FORM -->
<?php
                }
                else
                {
					echo '<a href="' . wp_get_attachment_url($attachment_id) . '" target="_blank" rel="nofollow">' . $label . '</a>';
                }
			}
		}

	}

}