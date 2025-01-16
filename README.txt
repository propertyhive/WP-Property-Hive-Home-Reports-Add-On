=== PropertyHive Home Reports ===
Contributors: PropertyHive,BIOSTALL
Tags: home report, scotland, propertyhive, property hive, property, real estate, software, estate agents, estate agent
Requires at least: 3.8
Tested up to: 6.7.1
Stable tag: trunk
Version: 1.0.11
Homepage: https://wp-property-hive.com/addons/home-reports/

This add on for Property Hive adds the ability for your users to upload home reports to properties

== Description ==

This add on for Property Hive adds the ability for your users to upload home reports to properties, a requirement for properties in Scotland.

Also includes the ability to capture the user's data first before emailing it to them.

Once installed and activated you can upload Home Reports to the new section on the property record under 'Media'. Uploaded Home Reports will then automatically appear on the property details page.

== Installation ==

= Manual installation =

The manual installation method involves downloading the Property Hive Home Reports Add-on plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Updating should work like a charm; as always though, ensure you backup your site just in case.

== Changelog ==

= 1.0.11 =
* Update Elementor widget to work with recent Elementor changes
* Declared support for WordPress 6.7.1

= 1.0.10 =
* Correct issue with property imports failing due to unnecessary calls to unlink() being made when files fail to download
* Declared support for WordPress 6.6.2

= 1.0.9 =
* Import home reports from SME Professional JSON imports where media type is 88

= 1.0.8 =
* Added new 'Home Report' Elementor widget so button can be placed on property page if building template in Elementor
* Declared support for WordPress 6.6.1

= 1.0.7 =
* Import home reports from RTDF imports where the sent as brochures with the caption 'Home Report'
* PHP8.2 compatibility
* Declared support for WordPress 6.5.3

= 1.0.6 =
* Added new setting allowing you to display a form for capturing the users data before the Home Report is emailed to them as an attachment
* Declared support for WordPress 6.0.2

= 1.0.5 =
* Import home reports from Vebra API XML format
* Declared support for WordPress 5.9.3

= 1.0.4 =
* Make compatible with WP 5.5 following recent jQuery changes
* Declared support for WordPress 5.5

= 1.0.3 =
* Include Home Reports in Zoopla RTDF add on requests
* Added new option to specify whether Home Reports uploaded should be included in portal feeds. Settings area will only show if one of the RTDF add ons is active
* Declared support for WordPress 5.4.2

= 1.0.2 =
* Include Home Reports in RTDF add on requests

= 1.0.1 =
* Import home reports from Dezrez Rezi JSON format

= 1.0.0 =
* First working release of the add on