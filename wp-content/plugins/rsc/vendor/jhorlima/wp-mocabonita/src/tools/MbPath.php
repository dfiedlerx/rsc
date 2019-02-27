<?php

namespace MocaBonita\tools;

/**
 *
 * Main class of the MocaBonita Path
 *
 * @author    Jhordan Lima <jhorlima@icloud.com>
 * @category  WordPress
 * @package   \MocaBonita\tools
 *
 * @copyright Jhordan Lima 2017
 * @copyright Divisão de Projetos e Desenvolvimento - DPD
 * @copyright Núcleo de Tecnologia da Informação - NTI
 * @copyright Universidade Estadual do Maranhão - UEMA
 *
 */
class MbPath
{

    /**
     * Stored the plugin name
     *
     * @var string
     */
    protected static $pluginName;

    /**
     * Stored the base name of the plugin
     *
     * @var string
     */
    protected static $pluginBaseName;

    /**
     * Stored the plugin directory
     *
     * @var string
     */
    protected static $pluginDirectory;

    /**
     * Stored the URL of the plugin
     *
     * @var string
     */
    protected static $pluginUrl;

    /**
     * Get plugin name
     *
     * @return string
     */
    public static function pName()
    {
        if (is_null(self::$pluginName)) {
            self::$pluginName = explode('/', plugin_basename(__FILE__))[0];
        }

        return self::$pluginName;
    }

    /**
     * Get plugin base name
     *
     * @return string
     */
    public static function pBaseN()
    {
        if (is_null(self::$pluginBaseName)) {
            self::$pluginBaseName = self::pName() . "/index.php";
        }

        return self::$pluginBaseName;
    }

    /**
     * Get plugin directory
     *
     * @param string $complement
     *
     * @return string
     */
    public static function pDir($complement = "")
    {
        if (is_null(self::$pluginDirectory)) {
            self::$pluginDirectory = WP_PLUGIN_DIR . "/" . self::pName();
        }

        return self::$pluginDirectory . $complement;
    }

    /**
     * Get plugin url
     *
     * @param string $complement
     *
     * @return string
     */
    public static function pUrl($complement = "")
    {
        if (is_null(self::$pluginUrl)) {
            self::$pluginUrl = WP_PLUGIN_URL . "/" . self::pName();
        }

        return self::$pluginUrl . $complement;
    }

    /**
     * Get plugin view directory
     *
     * @param string $complement
     * @param string $path
     *
     * @return string
     */
    public static function pViewDir($complement = "", $path = 'view')
    {
        return self::pDir("/{$path}/{$complement}");
    }

    /**
     * Get Js directory of the plugin
     *
     * @param string $complement
     * @param string $path
     *
     * @return string
     */
    public static function pJsDir($complement = "", $path = 'public/js')
    {
        return self::pUrl("/{$path}/{$complement}");
    }

    /**
     * Get plugin css directory
     *
     * @param string $complement
     * @param string $path
     *
     * @return string
     */
    public static function pCssDir($complement = "", $path = 'public/css')
    {
        return self::pUrl("/{$path}/{$complement}");
    }

    /**
     * Get plugin images directory
     *
     * @param string $complement
     * @param string $path
     *
     * @return string
     */
    public static function pImgDir($complement = "", $path = 'public/images')
    {
        return self::pUrl("/{$path}/{$complement}");
    }

    /**
     * Get plugin directory bower_components
     *
     * @param string $complement
     * @param string $path
     *
     * @return string
     */
    public static function pBwDir($complement = "", $path = 'public/bower_components')
    {
        return self::pUrl("/{$path}/{$complement}");
    }
}