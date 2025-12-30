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
     * デフォルトロックオプション名
     *
     * @var string
     */
    private $option_name_default_locked = 'kseo_lock_modified_date_default_locked';

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . KSEO_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));

        // 一括操作のAJAXハンドラー
        add_action('wp_ajax_kseo_bulk_lock_all', array($this, 'ajax_bulk_lock_all'));
    }

    /**
     * 設定ページの追加
     */
    public function add_settings_page() {
        add_menu_page(
            'Kashiwazaki SEO Lock Modified Date 設定',
            'Kashiwazaki SEO Lock Modified Date',
            'manage_options',
            'kseo-lock-modified-date',
            array($this, 'render_settings_page'),
            'dashicons-lock',
            81
        );
    }

    /**
     * 設定の登録
     */
    public function register_settings() {
        register_setting('kseo_lock_modified_date_group', $this->option_name);
        register_setting('kseo_lock_modified_date_group', $this->option_name_default_locked);
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
        $default_locked = get_option($this->option_name_default_locked, '1');

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
                        <th scope="row">デフォルトでロックする</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr($this->option_name_default_locked); ?>"
                                       value="1"
                                       <?php checked($default_locked, '1'); ?>>
                                新規投稿作成時に「更新日をロックする」をデフォルトでONにする
                            </label>
                            <p class="description">
                                チェックを外すと、新規投稿作成時にロックがOFFの状態から始まります。
                            </p>
                        </td>
                    </tr>
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

            <hr style="margin: 30px 0;">

            <h2>一括操作</h2>
            <p class="description">対象の投稿タイプに含まれるすべての投稿に対して一括でロック状態を変更します。</p>

            <table class="form-table">
                <tr>
                    <th scope="row">全投稿を一括処理</th>
                    <td>
                        <button type="button" id="kseo_bulk_lock_all" class="button button-primary">
                            すべてロック
                        </button>
                        <button type="button" id="kseo_bulk_unlock_all" class="button">
                            すべてロック解除
                        </button>
                        <span id="kseo_bulk_spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                        <p id="kseo_bulk_message" style="margin-top: 10px;"></p>
                    </td>
                </tr>
            </table>

            <script>
            jQuery(document).ready(function($) {
                function bulkAction(action) {
                    var $spinner = $('#kseo_bulk_spinner');
                    var $message = $('#kseo_bulk_message');
                    var $buttons = $('#kseo_bulk_lock_all, #kseo_bulk_unlock_all');

                    var confirmMsg = action === 'lock'
                        ? '対象の投稿タイプに含まれるすべての投稿をロックします。よろしいですか？'
                        : '対象の投稿タイプに含まれるすべての投稿のロックを解除します。よろしいですか？';

                    if (!confirm(confirmMsg)) {
                        return;
                    }

                    $spinner.addClass('is-active');
                    $buttons.prop('disabled', true);
                    $message.text('処理中...').css('color', '#666');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kseo_bulk_lock_all',
                            lock_action: action,
                            nonce: '<?php echo wp_create_nonce('kseo_bulk_lock_all_nonce'); ?>'
                        },
                        success: function(response) {
                            $spinner.removeClass('is-active');
                            $buttons.prop('disabled', false);
                            if (response.success) {
                                $message.text(response.data.message).css('color', '#46b450');
                            } else {
                                $message.text(response.data.message).css('color', '#dc3232');
                            }
                        },
                        error: function() {
                            $spinner.removeClass('is-active');
                            $buttons.prop('disabled', false);
                            $message.text('エラーが発生しました。').css('color', '#dc3232');
                        }
                    });
                }

                $('#kseo_bulk_lock_all').on('click', function() {
                    bulkAction('lock');
                });

                $('#kseo_bulk_unlock_all').on('click', function() {
                    bulkAction('unlock');
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * AJAX: 全投稿の一括ロック/解除
     */
    public function ajax_bulk_lock_all() {
        // nonceチェック
        if (!check_ajax_referer('kseo_bulk_lock_all_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => '不正なリクエストです。'));
            return;
        }

        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません。'));
            return;
        }

        $lock_action = isset($_POST['lock_action']) ? sanitize_text_field($_POST['lock_action']) : '';
        if (!in_array($lock_action, array('lock', 'unlock'))) {
            wp_send_json_error(array('message' => '無効なアクションです。'));
            return;
        }

        $lock_value = ($lock_action === 'lock') ? '1' : '0';
        $selected_post_types = get_option($this->option_name, array('post', 'page'));

        // 対象の投稿を取得
        $posts = get_posts(array(
            'post_type' => $selected_post_types,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        $count = 0;
        foreach ($posts as $post_id) {
            update_post_meta($post_id, '_kseo_lock_modified_date', $lock_value);
            $count++;
        }

        $action_text = ($lock_action === 'lock') ? 'ロック' : 'ロック解除';
        wp_send_json_success(array(
            'message' => sprintf('%d件の投稿を%sしました。', $count, $action_text)
        ));
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
        $settings_link = '<a href="' . admin_url('admin.php?page=kseo-lock-modified-date') . '">設定</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
