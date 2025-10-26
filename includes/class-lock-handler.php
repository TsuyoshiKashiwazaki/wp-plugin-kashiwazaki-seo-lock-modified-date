<?php
/**
 * ロックハンドラークラス
 *
 * @package Kashiwazaki_SEO_Lock_Modified_Date
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class KSEO_Lock_Handler
 *
 * 更新日のロック機能を管理
 */
class KSEO_Lock_Handler {

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
     * コンストラクタ
     */
    public function __construct() {
        // 更新日ロック機能（優先度99で他のフィルターより後に実行）
        add_filter('wp_insert_post_data', array($this, 'lock_modified_date'), 99, 2);

        // 新規投稿作成時に自動的にロック状態にする
        add_action('wp_insert_post', array($this, 'set_default_lock'), 10, 3);
    }

    /**
     * 更新日ロック機能
     *
     * @param array $data    投稿データ
     * @param array $postarr 投稿配列
     * @return array 修正された投稿データ
     */
    public function lock_modified_date($data, $postarr) {
        // 新規投稿の場合は処理しない
        if (empty($postarr['ID'])) {
            return $data;
        }

        // 自動保存の場合は処理しない
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $data;
        }

        // リビジョンの場合は処理しない
        if (wp_is_post_revision($postarr['ID'])) {
            return $data;
        }

        $post_id = $postarr['ID'];

        // POSTデータから直接ロック状態を確認（メタボックスの値）
        if (isset($_POST['kseo_lock_modified_date_nonce_field']) &&
            wp_verify_nonce($_POST['kseo_lock_modified_date_nonce_field'], 'kseo_lock_modified_date_nonce')) {
            // フォームから送信されたロック状態を使用
            $is_locked = isset($_POST['kseo_lock_modified_date']) ? '1' : '0';
        } else {
            // それ以外の場合は保存されているメタデータを確認
            $is_locked = get_post_meta($post_id, $this->meta_key_lock, true);
        }

        // ロックされている場合
        if ($is_locked === '1') {
            // 元の投稿データを取得
            $original_post = get_post($post_id);

            if ($original_post) {
                // 更新日を元の日時に戻す
                $data['post_modified'] = $original_post->post_modified;
                $data['post_modified_gmt'] = $original_post->post_modified_gmt;
            }
        }

        return $data;
    }

    /**
     * 新規投稿作成時に自動的にロック状態にする
     *
     * @param int     $post_id 投稿ID
     * @param WP_Post $post    投稿オブジェクト
     * @param bool    $update  更新かどうか
     */
    public function set_default_lock($post_id, $post, $update) {
        // 更新の場合は処理しない
        if ($update) {
            return;
        }

        // 自動保存の場合は処理しない
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // リビジョンの場合は処理しない
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // 対象の投稿タイプか確認
        $selected_post_types = get_option($this->option_name, array('post', 'page'));
        if (!in_array($post->post_type, $selected_post_types)) {
            return;
        }

        // メタデータが未設定の場合のみデフォルトでロック状態にする
        $existing_lock = get_post_meta($post_id, $this->meta_key_lock, true);
        if ($existing_lock === '') {
            update_post_meta($post_id, $this->meta_key_lock, '1');
        }
    }
}
