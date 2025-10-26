<?php
/**
 * 設定画面クラス
 *
 * @package Kashiwazaki_SEO_Lock_Modified_Date
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class KSEO_Settings
 *
 * 設定画面の表示と保存を管理
 */
class KSEO_Settings {

    /**
     * オプション名
     *
     * @var string
     */
    private $option_name = 'kseo_lock_modified_date_post_types';

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . KSEO_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
    }

    /**
     * 設定ページの追加
     */
    public function add_settings_page() {
        add_options_page(
            'Kashiwazaki SEO Lock Modified Date 設定',
            'Kashiwazaki SEO Lock Modified Date',
            'manage_options',
            'kseo-lock-modified-date',
            array($this, 'render_settings_page'),
            81
        );
    }

    /**
     * 設定の登録
     */
    public function register_settings() {
        register_setting('kseo_lock_modified_date_group', $this->option_name);
    }

    /**
     * 設定ページのレンダリング
     */
    public function render_settings_page() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            return;
        }

        // すべての投稿タイプを取得（組み込みと公開カスタム投稿タイプ）
        $post_types = get_post_types(array('public' => true), 'objects');
        $selected_post_types = get_option($this->option_name, array('post', 'page'));

        ?>
        <div class="wrap">
            <h1>Kashiwazaki SEO Lock Modified Date 設定</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('kseo_lock_modified_date_group');
                do_settings_sections('kseo_lock_modified_date_group');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">メタボックスを表示する投稿タイプ</th>
                        <td>
                            <?php foreach ($post_types as $post_type): ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($this->option_name); ?>[]"
                                           value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked(in_array($post_type->name, $selected_post_types)); ?>>
                                    <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                                </label>
                            <?php endforeach; ?>
                            <p class="description">
                                Kashiwazaki SEO Lock Modified Date 機能のメタボックスを表示する投稿タイプを選択してください。
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('設定を保存'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * オプション名を取得
     *
     * @return string
     */
    public function get_option_name() {
        return $this->option_name;
    }

    /**
     * プラグイン一覧に設定リンクを追加
     *
     * @param array $links 現在のリンク配列
     * @return array 修正されたリンク配列
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=kseo-lock-modified-date') . '">設定</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
