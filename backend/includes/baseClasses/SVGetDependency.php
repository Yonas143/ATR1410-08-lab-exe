<?php

namespace Includes\baseClasses;


use Automatic_Upgrader_Skin;
use Plugin_Upgrader;

class SVGetDependency
{
    protected $include_files = false;
    protected $require_plugins;
    protected $installed_plugins;

    public function __construct($require_plugins)
    {
        $this->require_plugins = $require_plugins;
    }
    public function dependent_external_plugin($key)
    {
        $external_plugins = [
            "wp-story-premium"          => "https://assets.iqonic.design/wp/plugins/wp-story-premium.zip",
            "iqonic-moderation-tool"    => "http://assets.iqonic.design/wp/plugins/iqonic-moderation-tool.zip",
            "iqonic-reactions"          => "http://assets.iqonic.design/wp/plugins/socialv/iqonic-reactions.zip"
        ];
        return $external_plugins[$key];
    }
    public function installedPlugins()
    {
        $installed_plugins  = get_plugins();
        $textdomains        = array_column($installed_plugins, 'TextDomain');
        $basenames          = array_keys($installed_plugins);
        return array_combine($textdomains, $basenames);
    }
    public function getPlugins()
    {
        $this->installed_plugins    = $this->installedPlugins();
        foreach ($this->require_plugins as $plugin => $is_external) {
            if (key_exists($plugin, $this->installed_plugins)) {
                if (!is_plugin_active($this->installed_plugins[$plugin])) {
                    activate_plugin($this->callPluginPath(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->installed_plugins[$plugin]), '', false, false);
                    continue;
                }
            } else {
                if ($is_external) {
                    $this->installPlugin(self::dependent_external_plugin($plugin));
                    continue;
                } else {
                    $plugin_data = $this->getPluginData($plugin);
                    if (isset($plugin_data->download_link)) {
                        $this->installPlugin($plugin_data->download_link);
                    }
                }
            }
        }

        // $plugin_data = $this->getPluginData($this->pluginName);
        // if ($this->isPluginInstalled($basename)) {
        //     if (!is_plugin_active($basename)) {
        //         activate_plugin($this->callPluginPath(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $basename), '', false, false);
        //         return true;
        //     }
        // } else {
        //     if (!$this->is_external && isset($plugin_data->download_link)) {
        //         $this->installPlugin($plugin_data->download_link);
        //         return true;
        //     } elseif ($this->is_external) {
        //         $this->installPlugin(self::dependent_external_plugin($this->pluginName));
        //         return true;
        //     }
        // }
        // return false;
    }

    public function getPluginData($slug = '')
    {
        $args = array(
            'slug'      => $slug,
            'fields'    => array(
                'version' => false,
            ),
        );

        $response = wp_remote_post(
            'http://api.wordpress.org/plugins/info/1.0/',
            array(
                'body' => array(
                    'action' => 'plugin_information',
                    'request' => serialize((object) $args),
                ),
            )
        );

        if (is_wp_error($response)) {
            return false;
        } else {
            $response = unserialize(wp_remote_retrieve_body($response));
            if ($response)
                return $response;
        }

        return false;
    }

    // public function isPluginInstalled($basename)
    // {
    //     if (!function_exists('get_plugins')) {
    //         include_once ABSPATH . 'wp-admin/includes/plugin.php';
    //     }

    //     $plugins = get_plugins();

    //     return isset($plugins[$basename]);
    // }

    public function installPlugin($plugin_url)
    {
        if (!$this->include_files) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
            include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
        }
        $skin       = new Automatic_Upgrader_Skin;
        $upgrade    = new Plugin_Upgrader($skin);
        $upgrade->install($plugin_url);
        $this->include_files = true;
        // activate plugin
        activate_plugin($upgrade->plugin_info(), '', false, false);

        return $skin->result;
    }

    public function callPluginPath($path)
    {
        $path = str_replace(['//', '\\\\'], ['/', '\\'], $path);

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
