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
 * 投稿編集画面のメタボックス表示と、ロック切り替え・手動変更の AJAX 処理を管理
 */
class KSEO_Meta_Box {

    /**
     * AJAX 用 nonce のアクション名
     */
    const NONCE_ACTION = 'kseo_lock_modified_date_ajax';

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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_kseo_set_lock', array($this, 'ajax_set_lock'));
        add_action('wp_ajax_kseo_update_modified_date', array($this, 'ajax_update_modified_date'));
        add_action('wp_ajax_kseo_get_modified_state', array($this, 'ajax_get_modified_state'));
    }

    /**
     * メタボックスの追加
     */
    public function add_meta_box() {
        foreach (KSEO_Lock_State::enabled_post_types() as $post_type) {
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
     * 画面表示用の状態を作る
     *
     * @param WP_Post $post 投稿
     * @return array
     */
    public static function build_state($post) {
        $post = get_post($post->ID);
        $published = KSEO_Lock_State::is_published_for_lock($post);
        $locked = KSEO_Lock_State::stored_state($post->ID);

        $modified_ts = get_post_modified_time('U', true, $post);
        if (false === $modified_ts) {
            $modified_ts = get_post_modified_time('U', false, $post);
        }

        return array(
            'locked' => $locked,
            'published' => $published,
            'lock_effective' => $published && $locked,
            'modified_display' => get_post_modified_time('Y年n月j日 H:i', false, $post),
            'modified_input' => get_post_modified_time('Y-m-d\TH:i', false, $post),
            'time_diff' => $modified_ts ? sprintf('%s前', human_time_diff((int) $modified_ts, time())) : '',
            'post_date_display' => get_post_time('Y年n月j日 H:i', false, $post),
            'max_input' => wp_date('Y-m-d\TH:i'),
        );
    }

    /**
     * メタボックスのレンダリング
     *
     * @param WP_Post $post 投稿オブジェクト
     */
    public function render_meta_box($post) {
        $state = self::build_state($post);
        ?>
        <div class="kseo-lock-modified-date-meta-box" id="kseo-lmd-box" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <p>
                <label>
                    <input type="checkbox" id="kseo_lock_toggle" <?php checked($state['locked']); ?>>
                    <strong>更新日をロックする</strong>
                </label>
            </p>
            <p class="description">
                ON: 投稿を保存しても更新日は変わりません。<br>
                OFF: 保存するたびに、保存した日時が更新日になります。<br>
                切り替えはその場で保存されます（投稿の保存は不要です）。
            </p>
            <p class="kseo-unpublished-note" id="kseo_unpublished_note"<?php echo $state['published'] ? ' hidden' : ''; ?>>
                この投稿はまだ公開されていません。ロックは公開後に有効になります（公開操作をした場合はその時点の日時が更新日になります）。
            </p>
            <div id="kseo_lock_message" class="kseo-message" role="status" aria-live="polite"></div>

            <hr>

            <div class="kseo-current-modified-date">
                <p><strong>現在の更新日時:</strong></p>
                <p class="kseo-current-value" id="kseo_current_modified"><?php echo esc_html($state['modified_display']); ?></p>
                <p class="kseo-current-diff" id="kseo_current_diff"><?php echo esc_html($state['time_diff']); ?></p>
            </div>

            <hr>

            <div class="kseo-manual-update">
                <p><strong>更新日を手動で変更:</strong></p>
                <p class="description">
                    日時を選んで「更新日を変更」を押すと、その場で反映されます（投稿の保存は不要です）。変更するとロックが自動で ON になります。
                </p>
                <p>
                    <input type="datetime-local"
                           id="kseo_manual_modified_date"
                           value="<?php echo esc_attr($state['modified_input']); ?>"
                           max="<?php echo esc_attr($state['max_input']); ?>"
                           <?php disabled(!$state['published']); ?>>
                </p>
                <p class="kseo-pending" id="kseo_manual_pending" hidden>まだ反映されていません。「更新日を変更」を押してください。</p>
                <p>
                    <button type="button" id="kseo_set_to_post_date" class="button button-secondary" <?php disabled(!$state['published']); ?>>
                        公開日と同じにする
                    </button>
                </p>
                <p>
                    <button type="button" id="kseo_update_modified_date" class="button button-primary" <?php disabled(!$state['published']); ?>>
                        更新日を変更
                    </button>
                </p>
                <div id="kseo_update_message" class="kseo-message" role="status" aria-live="polite"></div>
            </div>
        </div>

        <style>
            .kseo-lock-modified-date-meta-box { font-size: 13px; }
            .kseo-lock-modified-date-meta-box hr { border: none; border-top: 1px solid #ddd; margin: 15px 0; }
            .kseo-lock-modified-date-meta-box p { margin: 6px 0; }
            .kseo-lock-modified-date-meta-box input[type="datetime-local"],
            .kseo-lock-modified-date-meta-box .button { width: 100%; }
            .kseo-current-modified-date p { margin: 0; }
            .kseo-current-value { margin: 5px 0 2px !important; }
            .kseo-current-diff { color: #666; font-size: 12px; }
            .kseo-unpublished-note { background: #fcf9e8; border-left: 4px solid #dba617; padding: 6px 8px; }
            .kseo-pending { color: #b32d2e; }
            .kseo-message.success { color: #1a7f37; font-weight: bold; }
            .kseo-message.error { color: #d63638; font-weight: bold; }
        </style>
        <?php
    }

    /**
     * スクリプトの読み込み
     *
     * @param string $hook 現在の管理画面ページ
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !KSEO_Lock_State::is_enabled_type($screen->post_type)) {
            return;
        }

        $script_path = KSEO_PLUGIN_DIR . 'assets/js/admin.js';

        wp_enqueue_script(
            'kseo-admin-js',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            file_exists($script_path) ? (string) filemtime($script_path) : KSEO_VERSION,
            true
        );

        wp_localize_script(
            'kseo-admin-js',
            'kseoData',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
            )
        );
    }

    /**
     * 日時入力（datetime-local）を厳密に解釈する
     *
     * @param string $raw 入力値（Y-m-d\TH:i または Y-m-d\TH:i:s）
     * @return DateTimeImmutable|null
     */
    public static function parse_input_datetime($raw) {
        foreach (array('Y-m-d\TH:i:s', 'Y-m-d\TH:i') as $format) {
            $datetime = DateTimeImmutable::createFromFormat('!' . $format, $raw, wp_timezone());
            $errors = DateTimeImmutable::getLastErrors();
            $has_errors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
            if (false !== $datetime && !$has_errors && $datetime->format($format) === $raw) {
                return $datetime;
            }
        }
        return null;
    }

    /**
     * AJAX 共通: リクエストの投稿を検証して返す
     *
     * @return WP_Post 失敗時は JSON エラーを返して終了
     */
    private function verify_ajax_post() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array('message' => 'セッションの有効期限が切れた可能性があります。ページを再読み込みしてください。'), 403);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $post = $post_id ? get_post($post_id) : null;

        if (!$post || 'revision' === $post->post_type) {
            wp_send_json_error(array('message' => '投稿が見つかりません。'), 404);
        }

        if (!current_user_can('edit_post', $post->ID)) {
            wp_send_json_error(array('message' => '権限がありません。'), 403);
        }

        if (!KSEO_Lock_State::is_enabled_type($post->post_type)) {
            wp_send_json_error(array('message' => 'この投稿タイプは設定で対象外になっています。'), 400);
        }

        return $post;
    }

    /**
     * AJAX: ロックの切り替え（その場で保存）
     */
    public function ajax_set_lock() {
        $post = $this->verify_ajax_post();

        $locked = isset($_POST['locked']) && '1' === sanitize_text_field(wp_unslash($_POST['locked']));
        KSEO_Lock_State::set_state($post->ID, $locked);

        $state = self::build_state($post);
        if ($locked) {
            $state['message'] = $state['published']
                ? 'ロックを有効にしました。保存しても更新日は変わりません。'
                : 'ロックを有効にしました（公開後に有効になります）。';
        } else {
            $state['message'] = 'ロックを解除しました。次に保存したときに更新日が変わります。';
        }

        wp_send_json_success($state);
    }

    /**
     * AJAX: 現在の状態を返す（保存後の表示同期用）
     */
    public function ajax_get_modified_state() {
        $post = $this->verify_ajax_post();
        wp_send_json_success(self::build_state($post));
    }

    /**
     * AJAX: 更新日の手動変更
     */
    public function ajax_update_modified_date() {
        $post = $this->verify_ajax_post();

        if (!KSEO_Lock_State::is_published_for_lock($post)) {
            wp_send_json_error(array('message' => '公開前の投稿は更新日を変更できません。公開後に変更してください。'), 400);
        }

        if (KSEO_Lock_Handler::has_future_publish_date($post)) {
            wp_send_json_error(array('message' => '公開日時が未来のため、更新日を変更できません。'), 400);
        }

        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'custom';

        if ('post_date' === $mode) {
            $requested_local = $post->post_date;
            $requested_gmt = KSEO_Lock_State::is_valid_date($post->post_date_gmt) ? $post->post_date_gmt : get_gmt_from_date($post->post_date);
        } else {
            $raw = isset($_POST['new_date']) ? sanitize_text_field(wp_unslash($_POST['new_date'])) : '';
            $datetime = self::parse_input_datetime($raw);
            if (null === $datetime) {
                wp_send_json_error(array('message' => '日時の形式が正しくありません。'), 400);
            }
            if ($datetime->getTimestamp() - time() >= MINUTE_IN_SECONDS) {
                wp_send_json_error(array('message' => '未来の日時は指定できません。'), 400);
            }
            $requested_local = $datetime->format('Y-m-d H:i:s');
            $requested_gmt = $datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }

        list($local, $gmt) = KSEO_Lock_State::normalize_dates($requested_local, $requested_gmt, $post->post_date, $post->post_date_gmt);
        $adjusted_to_post_date = ('post_date' !== $mode) && ($local === $post->post_date) && ($requested_local !== $post->post_date);

        // 更新日の 2 列だけを書き込み、成功したらロックを有効にしてから更新フックを発火する
        $write = KSEO_Lock_Handler::write_modified_date($post->ID, $local, $gmt, true);

        if (!$write['ok']) {
            $state = self::build_state(get_post($post->ID));
            $state['message'] = '更新日を書き込めませんでした。' . ($write['error'] !== '' ? '（' . $write['error'] . '）' : '');
            wp_send_json_error($state, 500);
        }

        // 表示とメッセージは DB から読み直した値で組み立てる
        $state = self::build_state(get_post($post->ID));
        if ($state['locked']) {
            $state['message'] = sprintf('更新日を %s に変更し、ロックを有効にしました（解除する場合はチェックを外してください）。', $state['modified_display']);
        } else {
            $state['message'] = sprintf('更新日を %s に変更しましたが、ロックを有効にできませんでした。チェックを入れてロックしてください。', $state['modified_display']);
        }
        if ($adjusted_to_post_date) {
            $state['message'] .= '公開日より前にはできないため、公開日時に合わせました。';
        }

        wp_send_json_success($state);
    }
}
