<?php
/**
 * メタボックスクラス
 *
 * @package Kashiwazaki_SEO_Lock_Modified_Date
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class KSEO_Meta_Box
 *
 * 投稿編集画面のメタボックス表示と保存を管理
 */
class KSEO_Meta_Box {

    /**
     * メタキー（ロック状態）
     *
     * @var string
     */
    private $meta_key_lock = '_kseo_lock_modified_date';

    /**
     * オプション名
     *
     * @var string
     */
    private $option_name = 'kseo_lock_modified_date_post_types';

    /**
     * プラグインURL
     *
     * @var string
     */
    private $plugin_url;

    /**
     * コンストラクタ
     *
     * @param string $plugin_url プラグインURL
     */
    public function __construct($plugin_url) {
        $this->plugin_url = $plugin_url;

        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_kseo_update_modified_date', array($this, 'ajax_update_modified_date'));
    }

    /**
     * メタボックスの追加
     */
    public function add_meta_box() {
        $selected_post_types = get_option($this->option_name, array('post', 'page'));

        foreach ($selected_post_types as $post_type) {
            add_meta_box(
                'kseo_lock_modified_date',
                'Kashiwazaki SEO Lock Modified Date',
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * メタボックスのレンダリング
     *
     * @param WP_Post $post 投稿オブジェクト
     */
    public function render_meta_box($post) {
        // nonceフィールドの追加
        wp_nonce_field('kseo_lock_modified_date_nonce', 'kseo_lock_modified_date_nonce_field');

        // 現在のロック状態を取得
        $is_locked = get_post_meta($post->ID, $this->meta_key_lock, true);
        if ($is_locked === '') {
            // デフォルト設定を参照
            $default_locked = get_option('kseo_lock_modified_date_default_locked', '1');
            $is_locked = $default_locked;
        }

        // 現在の更新日時
        $modified_date_formatted = get_post_modified_time('Y年n月j日 H:i', false, $post);

        // 公開日
        $post_date = get_post_field('post_date', $post->ID);

        // 経過時間
        $time_diff = human_time_diff(get_post_modified_time('U', false, $post), current_time('timestamp'));

        ?>
        <div class="kseo-lock-modified-date-meta-box">
            <p>
                <label>
                    <input type="checkbox"
                           name="kseo_lock_modified_date"
                           value="1"
                           <?php checked($is_locked, '1'); ?>>
                    <strong>更新日をロックする</strong>
                </label>
            </p>
            <p class="description">
                チェックを入れると、投稿を編集・保存しても更新日が変わりません。<br>
                チェックを外すと、通常通り更新日が変わります。
            </p>

            <hr style="margin: 15px 0;">

            <div class="kseo-current-modified-date">
                <p><strong>現在の更新日時:</strong></p>
                <p style="margin: 5px 0 10px 0;"><?php echo esc_html($modified_date_formatted); ?></p>
                <p style="color: #666; font-size: 12px;"><?php echo esc_html($time_diff); ?>前</p>
            </div>

            <hr style="margin: 15px 0;">

            <div class="kseo-manual-update">
                <p><strong>更新日を手動で変更:</strong></p>
                <p style="margin: 5px 0;">
                    <input type="datetime-local"
                           id="kseo_manual_modified_date"
                           value="<?php echo esc_attr(get_post_modified_time('Y-m-d\TH:i', false, $post)); ?>"
                           style="width: 100%;">
                </p>
                <p style="margin: 10px 0;">
                    <button type="button"
                            id="kseo_set_to_post_date"
                            class="button button-secondary"
                            style="width: 100%;"
                            data-post-date="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime($post_date))); ?>">
                        公開日と同じにする
                    </button>
                </p>
                <p style="margin: 10px 0;">
                    <button type="button"
                            id="kseo_update_modified_date"
                            class="button button-primary"
                            style="width: 100%;"
                            data-post-id="<?php echo esc_attr($post->ID); ?>">
                        更新日を変更
                    </button>
                </p>
                <div id="kseo_update_message" style="margin-top: 10px;"></div>
            </div>
        </div>

        <style>
            .kseo-lock-modified-date-meta-box {
                font-size: 13px;
            }
            .kseo-lock-modified-date-meta-box hr {
                border: none;
                border-top: 1px solid #ddd;
            }
            .kseo-current-modified-date p {
                margin: 0;
            }
            #kseo_update_message.success {
                color: #46b450;
                font-weight: bold;
            }
            #kseo_update_message.error {
                color: #dc3232;
                font-weight: bold;
            }
        </style>
        <?php
    }

    /**
     * メタボックスの保存
     *
     * @param int     $post_id 投稿ID
     * @param WP_Post $post    投稿オブジェクト
     */
    public function save_meta_box($post_id, $post) {
        // nonceチェック
        if (!isset($_POST['kseo_lock_modified_date_nonce_field']) ||
            !wp_verify_nonce($_POST['kseo_lock_modified_date_nonce_field'], 'kseo_lock_modified_date_nonce')) {
            return;
        }

        // 自動保存の場合は処理しない
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // チェックボックスの値を保存
        $is_locked = isset($_POST['kseo_lock_modified_date']) ? '1' : '0';
        update_post_meta($post_id, $this->meta_key_lock, $is_locked);
    }

    /**
     * スクリプトとスタイルの読み込み
     *
     * @param string $hook 現在の管理画面ページ
     */
    public function enqueue_scripts($hook) {
        // 投稿編集画面のみ
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        wp_enqueue_script(
            'kseo-admin-js',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            '1.0.1',
            true
        );

        wp_localize_script(
            'kseo-admin-js',
            'kseoData',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kseo_update_modified_date_nonce')
            )
        );
    }

    /**
     * AJAX: 手動更新日変更
     */
    public function ajax_update_modified_date() {
        // nonceチェック
        check_ajax_referer('kseo_update_modified_date_nonce', 'nonce');

        // パラメータ取得
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';

        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => '権限がありません。'));
            return;
        }

        // 日付の検証
        $timestamp = strtotime($new_date);
        if ($timestamp === false) {
            wp_send_json_error(array('message' => '無効な日時形式です。'));
            return;
        }

        // 日時をWordPress形式に変換
        $formatted_date = date('Y-m-d H:i:s', $timestamp);
        $formatted_date_gmt = get_gmt_from_date($formatted_date);

        // 投稿の更新日を直接更新
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->posts,
            array(
                'post_modified' => $formatted_date,
                'post_modified_gmt' => $formatted_date_gmt
            ),
            array('ID' => $post_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => '更新に失敗しました。'));
            return;
        }

        // キャッシュをクリア
        clean_post_cache($post_id);

        // 新しい表示用の日時を取得
        $new_modified_date = get_post_modified_time('Y年n月j日 H:i', false, $post_id);
        $time_diff = human_time_diff(get_post_modified_time('U', false, $post_id), current_time('timestamp'));

        wp_send_json_success(array(
            'message' => '更新日を変更しました。',
            'new_date' => $new_modified_date,
            'time_diff' => $time_diff . '前'
        ));
    }

    /**
     * メタキーを取得
     *
     * @return string
     */
    public function get_meta_key_lock() {
        return $this->meta_key_lock;
    }
}
