=== Kashiwazaki SEO Lock Modified Date ===
Contributors: tsuyoshikashiwazaki
Tags: seo, modified date, post date, update date, lock date
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress投稿の更新日（post_modified）をロックし、SEO最適化のために更新日を意図的にコントロールできます。

== Description ==

このプラグインは、WordPressの投稿・固定ページ・カスタム投稿タイプの更新日を管理し、編集時に自動的に更新されないようにロックする機能を提供します。SEO対策として、更新日を意図的にコントロールしたい場合に便利です。

SEO対策研究室（https://www.tsuyoshikashiwazaki.jp）で開発されたプラグインです。

= 主な機能 =

* **更新日ロック機能**
    * デフォルトでロック状態: 投稿を作成すると、自動的に更新日がロックされます
    * チェックボックスで簡単切り替え: チェックを外すと、通常通り更新日が自動更新されます
    * 投稿タイプの選択: どの投稿タイプにロック機能を適用するか選択可能

* **手動更新日変更機能**
    * ロック中でも手動変更可能: ロック状態でも、手動で更新日を任意の日時に変更できます
    * 日時入力フォーム: 年月日・時刻を直接入力できます
    * 「公開日と同じにする」ボタン: ワンクリックで公開日と同じ日時に設定できます

* **視覚的な情報表示**
    * 現在の更新日時: 年月日・時刻を日本語形式で表示
    * 経過時間: 「3日前」「2ヶ月前」などの分かりやすい表示

= 使用例 =

* SEO対策として、更新日を固定したい場合
* 軽微な修正で更新日を変えたくない場合
* 過去記事の更新日を意図的にコントロールしたい場合
* リライト時に更新日を任意の日時に設定したい場合

== Installation ==

1. プラグインフォルダを `/wp-content/plugins/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化
3. 「設定」→「Kashiwazaki SEO Lock Modified Date」から設定を行う

または

1. WordPress管理画面の「プラグイン」→「新規追加」を開く
2. 「Kashiwazaki SEO Lock Modified Date」で検索
3. 「今すぐインストール」をクリック
4. 「有効化」をクリック

== Frequently Asked Questions ==

= 既存の投稿にもロック機能を適用できますか？ =

はい、既存の投稿でも編集画面を開いて「更新日をロックする」にチェックを入れることで適用できます。

= ロック状態でも更新日を変更できますか？ =

はい、「更新日を手動で変更」セクションから任意の日時に変更できます。

= カスタム投稿タイプにも対応していますか？ =

はい、すべての公開カスタム投稿タイプに対応しています。設定画面で選択できます。

= プラグインを無効化するとどうなりますか？ =

プラグインを無効化すると、通常通り更新日が自動更新されるようになります。メタデータは削除されませんので、再度有効化すると以前の設定が復元されます。

= デフォルトの動作は？ =

デフォルトでは、新規投稿作成時に自動的にロック状態（チェックON）になります。編集・保存しても更新日は変わりません。

= 更新日のロックを解除するには？ =

投稿編集画面のサイドバーにある「Kashiwazaki SEO Lock Modified Date」メタボックスで、「更新日をロックする」のチェックを外してください。

== Screenshots ==

1. 設定画面 - 投稿タイプを選択
2. Kashiwazaki SEO Lock Modified Date メタボックス - ロックのON/OFF
3. 手動更新日変更 - 日時入力フォーム
4. 経過時間の表示

== Changelog ==

= 1.0.0 =
* 初回リリース
* 更新日ロック機能
* 手動更新日変更機能
* 投稿タイプ選択機能
* 「公開日と同じにする」ボタン

== Upgrade Notice ==

= 1.0.0 =
初回リリース版です。

== Technical Specifications ==

= フック =
* `wp_insert_post_data`: 投稿保存時に更新日をロック
* `add_meta_boxes`: メタボックスの追加
* `save_post`: メタボックスの保存
* `wp_insert_post`: 新規投稿作成時のデフォルトロック設定
* `wp_ajax_kseo_update_modified_date`: AJAX処理（手動更新日変更）

= メタデータ =
* `_kseo_lock_modified_date`: ロック状態を保存（0 or 1）

= オプション =
* `kseo_lock_modified_date_post_types`: メタボックスを表示する投稿タイプの配列

= セキュリティ =
* nonceによるCSRF対策
* 権限チェック（current_user_can）
* データのサニタイズとエスケープ
