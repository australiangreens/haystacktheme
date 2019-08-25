<?php
/**
 * Created by PhpStorm.
 * User: matthew
 * Date: 28-02-2019
 * Time: 19:32
 */

use CRM_Haystack_ExtensionUtil as E;

class CRM_Haystack_Main {

  public function isAdmin() {
    return (!CRM_Core_Config::singleton()->userFrameworkFrontend || CRM_Core_Config::singleton()->userFramework == 'drupal');
  }

  /**
   * Disable CiviCRM resources from front-end.
   *
   */
  public function resources_disable() {

    // Clear any custom CSS URL that is configured.
    Civi::settings()->set('customCSSURL', NULL);
    // Maybe disable core stylesheet.
    if ((boolean) CRM_Haystack_Settings::getValue('disable_civicrm_core_css')) {
      Civi::settings()->set('disable_core_css', TRUE);
      $this->resource_disable( 'civicrm', 'css/civicrm.css' );
    }

    if (!$this->isAdmin()) {
      // Maybe disable navigation stylesheet (there's no menu on the front-end).
      $this->resource_disable('civicrm', 'css/civicrmNavigation.css');

      // If Shoreditch present.
      if ($this->shoreditch_is_active()) {
        // Maybe disable Shoreditch stylesheet.
        $this->resource_disable('org.civicrm.shoreditch', 'css/custom-civicrm.css');

        // Maybe disable Shoreditch Bootstrap stylesheet.
        $this->resource_disable('org.civicrm.shoreditch', 'css/bootstrap.css');

      }
      else {
        // Maybe disable custom stylesheet (not provided by Shoreditch).
        //if ( $this->setting_get( 'css_custom', '0' ) == '1' ) {
        $this->custom_css_disable();
      }
    }
  }

  /**
   * Enable CiviCRM theme resources
   *
   * @param $region
   */
  public function resources_enable($region, $cmsOnly = FALSE) {
    // Load a cms specific css file
    if ($region == 'html-header') {
      switch (strtolower(CRM_Core_Config::singleton()->userFramework)) {
        case 'joomla':
          $css = 'joomla';
          break;
        case 'wordpress':
          $css = 'wordpress';
          break;
        case 'drupal':
          $css = 'drupal7';
          break;
        default:
          $css = 'drupal7';
      }

      $theme = CRM_Haystack_Settings::getValue('theme');
      if (file_exists(E::path("theme/{$theme}/{$css}.css"))) {
        CRM_Core_Resources::singleton()
          ->addStyleFile('haystack', "theme/{$theme}/{$css}.css", -50, $region);
      }

      CRM_Core_Resources::singleton()
        ->addStyleUrl(\Civi::service('asset_builder')->getUrl('main.css'), -40, $region);

      // Responsive datatables only makes sense for CiviCRM admin interfaces
      if (!$cmsOnly && self::isAdmin()) {
        if ((boolean) CRM_Haystack_Settings::getValue('responsive_datatables')) {
          // If we want responsive datatables?
          CRM_Core_Resources::singleton()
            ->addStyleFile('haystack', 'css/responsive.dataTables.min.css', -50, $region);
          CRM_Core_Resources::singleton()
            ->addScriptFile('haystack', 'js/dataTables.responsive.min.js', -50, $region);
        }
        if ((boolean) CRM_Haystack_Settings::getValue('responsive_tables')) {
          // If we want responsive tables?
          CRM_Core_Resources::singleton()
            ->addStyleFile('haystack', 'css/responsivetables.css', -50, $region);
          CRM_Core_Resources::singleton()
            ->addScriptFile('haystack', 'js/responsivetables.js', -50, $region);
        }
      }

      switch ((int)CRM_Haystack_Settings::getValue('theme_frontend')) {
        case 0:
          // Never
          $loadFrontend = FALSE;
          break;

        case 1:
          // Only frontend
          if (!$this->isAdmin()) {
            $loadFrontend = TRUE;
          }
          break;

        case 2:
          // Only Backend
          if ($this->isAdmin()) {
            $loadFrontend = TRUE;
          }
          break;

        case 3:
          // Frontend and Backend
          $loadFrontend = TRUE;
          break;

        default:
          $loadFrontend = TRUE;

      }

      if ($loadFrontend) {
        if ($this->isAdmin()) {
          if (file_exists(E::path("theme/{$theme}/frontend.css"))) {
            CRM_Core_Resources::singleton()
              ->addStyleUrl(\Civi::service('asset_builder')->getUrl('frontend.css'), -50, $region);
          }
        }
        else {
          $this->addCssToFrontend('frontend.css', $region);
        }
      }
    }
  }

  public function addCssToFrontend($cssFile, $region) {
    if (function_exists('wp_enqueue_style')) {
      // Add frontend css for Wordpress
      wp_enqueue_style(
        'civicrm_haystacktheme_frontend',
        \Civi::service('asset_builder')->getUrl($cssFile),
        NULL,
        '1.0', // Version.
        'all' // Media.
      );
    }
    else {
      // This will work for Drupal
      // @fixme Add a Joomla method
      CRM_Core_Resources::singleton()
        ->addStyleUrl(\Civi::service('asset_builder')
          ->getUrl('frontend.css'), -50, $region);
    }
  }

  /**
   * Disable a resource enqueued by CiviCRM.
   *
   *
   * @param str $extension The name of the extension e.g. 'org.civicrm.shoreditch'. Default is CiviCRM core.
   * @param str $file The relative path to the resource. Default is CiviCRM core stylesheet.
   */
  public function resource_disable($extension = 'civicrm', $file = 'css/civicrm.css') {

    // Get the resource URL.
    $url = $this->resource_get_url( $extension, $file );

    // Kick out if not enqueued.
    if ( $url === false ) return;

    // Set to disabled.
    CRM_Core_Region::instance('html-header')->update( $url, array( 'disabled' => FALSE ) );

  }

  /**
   * Get the URL of a resource if it is enqueued by CiviCRM.
   *
   * @param str $extension The name of the extension e.g. 'org.civicrm.shoreditch'. Default is CiviCRM core.
   * @param str $file The relative path to the resource. Default is CiviCRM core stylesheet.
   * @return bool|str $url The URL if the resource is enqueued, false otherwise.
   */
  public function resource_get_url( $extension = 'civicrm', $file = 'css/civicrm.css' ) {
    // Get registered URL.
    $url = CRM_Core_Resources::singleton()->getUrl( $extension, $file, TRUE );

    // Get registration data from region.
    $registration = CRM_Core_Region::instance( 'html-header' )->get( $url );

    // Bail if not registered.
    if ( empty( $registration ) ) return false;

    // Is enqueued.
    return $url;
  }

  /**
   * Disable any custom CSS file enqueued by CiviCRM.
   *
   */
  public function custom_css_disable() {

    // Get CiviCRM config.
    $config = CRM_Core_Config::singleton();

    // Bail if there's no custom CSS file.
    if ( empty( $config->customCSSURL ) ) return;

    // Get registered URL.
    $url = CRM_Core_Resources::singleton()->addCacheCode( $config->customCSSURL );

    // Get registration data from region.
    $registration = CRM_Core_Region::instance('html-header')->get( $url );

    // Bail if not registered.
    if ( empty ( $registration ) ) return;

    // Set to disabled.
    CRM_Core_Region::instance('html-header')->update( $url, array( 'disabled' => TRUE ) );
  }


  /**
   * Determine if the Shoreditch CSS file is being used.
   *
   * @return bool $shoreditch True if Shoreditch CSS file is used, false otherwise.
   */
  public function shoreditch_is_active() {

    // Assume not.
    $shoreditch = false;

    // Get the current Custom CSS URL.
    $config = CRM_Core_Config::singleton();

    // Has the Shoreditch CSS been activated?
    if ( strstr( $config->customCSSURL, 'org.civicrm.shoreditch' ) !== false ) {

      // Shoreditch CSS is active.
      $shoreditch = true;

    }

    return $shoreditch;
  }

}
