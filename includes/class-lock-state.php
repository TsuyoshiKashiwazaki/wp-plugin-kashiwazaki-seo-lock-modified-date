<?php
/**
 * ロック状態の判定と日付の正規化
 *
 * メタボックス表示・保存時のロック・AJAX・一括処理のすべてがこのクラスを経由する。
 *
 * @package Kashiwazaki_SEO_Lock_Modified_Date
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class KSEO_Lock_State
 */
class KSEO_Lock_State {

    /**
     * メタキー（ロック状態 '1' / '0'）
     */
    const META_KEY = '_kseo_lock_modified_date';

    /**
     * オプション名（対象投稿タイプ）
     */
    const OPTION_POST_TYPES = 'kseo_lock_modified_date_post_types';

    /**
     * オプション名（デフォルトでロックする）
     */
    const OPTION_DEFAULT_LOCKED = 'kseo_lock_modified_date_default_locked';

    /**
     * MySQL のゼロ日時
     */
    const ZERO_DATE = '0000-00-00 00:00:00';

    /**
     * 有効な投稿タイプ（常に配列）
     *
     * @return string[]
     */
    public static function enabled_post_types() {
        $types = get_option(self::OPTION_POST_TYPES, null);

        // 一度も保存されていないサイトは従来どおり投稿と固定ページ
        if (null === $types || false === $types) {
            return array('post', 'page');
        }

        // 保存済みで配列でない値（全解除時の空文字など）は「対象なし」
        if (!is_array($types)) {
            return array();
        }

        $clean = array();
        foreach ($types as $type) {
            if (is_string($type) && $type !== '') {
                $clean[] = sanitize_key($type);
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * 投稿タイプが有効か
     *
     * @param string $post_type 投稿タイプ
     * @return bool
     */
    public static function is_enabled_type($post_type) {
        return is_string($post_type) && $post_type !== '' && in_array($post_type, self::enabled_post_types(), true);
    }

    /**
     * 「デフォルトでロックする」の値
     *
     * @return bool
     */
    public static function default_locked() {
        $value = get_option(self::OPTION_DEFAULT_LOCKED, null);
        if (null === $value || false === $value) {
            return true;
        }
        return '1' === (string) $value;
    }

    /**
     * 保存されているロック状態（未設定ならデフォルト値）
     *
     * @param int $post_id 投稿ID
     * @return bool
     */
    public static function stored_state($post_id) {
        $value = get_post_meta($post_id, self::META_KEY, true);
        if ($value === '1') {
            return true;
        }
        if ($value === '0') {
            return false;
        }
        return self::default_locked();
    }

    /**
     * ロック状態を保存
     *
     * @param int  $post_id 投稿ID
     * @param bool $locked  ロックするか
     */
    public static function set_state($post_id, $locked) {
        update_post_meta($post_id, self::META_KEY, $locked ? '1' : '0');
    }

    /**
     * ロック対象となる公開状態か
     *
     * @param WP_Post $post 投稿
     * @return bool
     */
    public static function is_published_for_lock($post) {
        if (!($post instanceof WP_Post)) {
            return false;
        }

        $status = $post->post_status;

        if ('attachment' === $post->post_type) {
            return in_array($status, array('inherit', 'private'), true);
        }
        return in_array($status, array('publish', 'private'), true);
    }

    /**
     * 保存時にロックが実際に効くか
     *
     * @param WP_Post $post 投稿
     * @return bool
     */
    public static function is_lock_effective($post) {
        return ($post instanceof WP_Post)
            && self::is_enabled_type($post->post_type)
            && self::is_published_for_lock($post)
            && self::stored_state($post->ID);
    }

    /**
     * 更新日を整合性のある値にそろえる
     *
     * - GMT をゼロ日時にしない
     * - 公開日が現在以前なら、公開日より前にしない
     * - 現在時刻より後にしない
     * - 公開日が未来なら公開日には合わせず、元の値が有効かつ現在以前ならそれを、そうでなければ現在時刻を使う
     *
     * @param string $local         更新日（サイトのタイムゾーン）
     * @param string $gmt           更新日（GMT）。ゼロ日時・空ならローカル値から算出
     * @param string $post_date     公開日（サイトのタイムゾーン）
     * @param string $post_date_gmt 公開日（GMT）
     * @return string[] array( ローカル, GMT )
     */
    public static function normalize_dates($local, $gmt, $post_date, $post_date_gmt) {
        $now = time();
        $value_ts = self::timestamp($local, $gmt);

        if (null === $value_ts || $value_ts > $now) {
            $result = array(wp_date('Y-m-d H:i:s', $now), gmdate('Y-m-d H:i:s', $now));
            $value_ts = $now;
        } else {
            $result = array($local, self::is_valid_date($gmt) ? $gmt : gmdate('Y-m-d H:i:s', $value_ts));
        }

        $date_ts = self::timestamp($post_date, $post_date_gmt);
        if (null !== $date_ts && $date_ts <= $now && $value_ts < $date_ts) {
            $result = array(
                $post_date,
                self::is_valid_date($post_date_gmt) ? $post_date_gmt : get_gmt_from_date($post_date)
            );
        }

        return $result;
    }

    /**
     * 日時文字列が有効か（ゼロ日時・空は無効）
     *
     * @param string $date 日時
     * @return bool
     */
    public static function is_valid_date($date) {
        return is_string($date) && $date !== '' && $date !== self::ZERO_DATE;
    }

    /**
     * Unix タイムスタンプを求める（GMT 優先、無ければローカル値をサイトのタイムゾーンで解釈）
     *
     * @param string $local ローカル日時
     * @param string $gmt   GMT 日時
     * @return int|null
     */
    private static function timestamp($local, $gmt) {
        if (self::is_valid_date($gmt)) {
            $datetime = date_create_immutable($gmt, new DateTimeZone('UTC'));
            if ($datetime) {
                return $datetime->getTimestamp();
            }
        }
        if (self::is_valid_date($local)) {
            $datetime = date_create_immutable($local, wp_timezone());
            if ($datetime) {
                return $datetime->getTimestamp();
            }
        }
        return null;
    }
}
