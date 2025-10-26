<?php
/**
 * Plugin Name: Kashiwazaki SEO Lock Modified Date
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: WordPress投稿の更新日（post_modified）をロックし、SEO最適化のために更新日を意図的にコントロールできます。軽微な修正時に更新日を変えず、必要な時だけ手動で変更可能。
 * Version: 1.0.0
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kashiwazaki-seo-lock-modified-date
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * メインプラグインクラス
 */
class Kashiwazaki_SEO_Lock_Modified_Date {

    /**
     * プラグインバージョン
     */
    const VERSION = '1.0.0';

    /**
     * インスタンス
     *
     * @var Kashiwazaki_SEO_Lock_Modified_Date
     */
    private static $instance = null;

    /**
     * 設定画面インスタンス
     *
     * @var KSEO_Settings
     */
    private $settings;

    /**
     * メタボックスインスタンス
     *
     * @var KSEO_Meta_Box
     */
    private $meta_box;

    /**
     * ロックハンドラーインスタンス
     *
     * @var KSEO_Lock_Handler
     */
    private $lock_handler;

    /**
     * シングルトンインスタンスを取得
     *
     * @return Kashiwazaki_SEO_Lock_Modified_Date
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_components();
    }

    /**
     * 定数の定義
     */
    private function define_constants() {
        define('KSEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('KSEO_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('KSEO_PLUGIN_BASENAME', plugin_basename(__FILE__));
        define('KSEO_VERSION', self::VERSION);
    }

    /**
     * 依存ファイルの読み込み
     */
    private function load_dependencies() {
        require_once KSEO_PLUGIN_DIR . 'includes/class-settings.php';
        require_once KSEO_PLUGIN_DIR . 'includes/class-meta-box.php';
        require_once KSEO_PLUGIN_DIR . 'includes/class-lock-handler.php';
    }

    /**
     * コンポーネントの初期化
     */
    private function init_components() {
        $this->settings = new KSEO_Settings();
        $this->meta_box = new KSEO_Meta_Box(KSEO_PLUGIN_URL);
        $this->lock_handler = new KSEO_Lock_Handler();
    }

    /**
     * 設定画面インスタンスを取得
     *
     * @return KSEO_Settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * メタボックスインスタンスを取得
     *
     * @return KSEO_Meta_Box
     */
    public function get_meta_box() {
        return $this->meta_box;
    }

    /**
     * ロックハンドラーインスタンスを取得
     *
     * @return KSEO_Lock_Handler
     */
    public function get_lock_handler() {
        return $this->lock_handler;
    }
}

/**
 * プラグインの初期化
 *
 * @return Kashiwazaki_SEO_Lock_Modified_Date
 */
function kashiwazaki_seo_lock_modified_date() {
    return Kashiwazaki_SEO_Lock_Modified_Date::get_instance();
}

// プラグインを開始
kashiwazaki_seo_lock_modified_date();
