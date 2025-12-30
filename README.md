# Kashiwazaki SEO Lock Modified Date

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.1-blue.svg)](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-lock-modified-date/releases)

WordPress投稿の更新日（post_modified）をロックし、SEO最適化のために更新日を意図的にコントロールできます。軽微な修正時に更新日を変えず、必要な時だけ手動で変更可能。

> **更新日をロックして、SEOを最適化**

## 主な機能

- **更新日ロック機能**: 投稿を編集しても更新日を固定
- **手動更新日変更**: 必要な時だけ更新日を変更
- **投稿タイプ選択**: 対象の投稿タイプを自由に選択
- **メタボックス表示**: 編集画面で簡単に設定
- **日時ピッカー**: 任意の日時に更新日を設定
- **公開日同期**: ワンクリックで公開日と同じに設定
- **デフォルトロック設定**: 新規投稿のデフォルトロック状態を設定で切り替え可能
- **一括ロック/解除**: 投稿一覧や設定画面から全投稿を一括操作
- **プラグイン一覧リンク**: 設定画面への直接アクセス

## クイックスタート

### インストール

1. プラグインファイルを `/wp-content/plugins/` ディレクトリにアップロード
2. WordPress管理画面でプラグインを有効化
3. 設定 > Kashiwazaki SEO Lock Modified Date で設定

### 基本設定

1. 対象の投稿タイプを選択
2. 投稿編集画面のサイドバーでロック設定
3. 必要に応じて手動で更新日を変更

## 使い方

### 更新日のロック

投稿編集画面のサイドバーにある「Kashiwazaki SEO Lock Modified Date」メタボックスで：

1. **「更新日をロックする」にチェック**: 投稿を保存しても更新日が変わりません
2. **チェックを外す**: 通常通り更新日が更新されます

### 手動で更新日を変更

メタボックス内の日時ピッカーを使用：

1. 任意の日時を選択
2. 「更新日を変更」ボタンをクリック
3. AJAXで即座に反映（ページリロード不要）

### 公開日と同じにする

「公開日と同じにする」ボタンをクリックすると、更新日が公開日と同じになります。

## 技術仕様

### システム要件

- WordPress 5.0以上
- PHP 7.4以上
- jQuery（WordPress同梱版）

### フック

- `wp_insert_post_data`: 更新日のロック処理
- `wp_insert_post`: デフォルトロック設定
- `plugin_action_links`: プラグイン一覧に設定リンク追加

### データ保存

- **メタキー**: `_kseo_lock_modified_date`
- **オプション**: `kseo_lock_modified_date_post_types`

## ライセンス

GPL-2.0-or-later

## サポート・開発者

**開発者**: 柏崎剛 (Tsuyoshi Kashiwazaki)
**ウェブサイト**: https://www.tsuyoshikashiwazaki.jp/
**サポート**: プラグインに関するご質問や不具合報告は、開発者ウェブサイトまでお問い合わせください。

## 貢献

バグ報告や機能リクエストは [Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-lock-modified-date/issues) からお願いします。

プルリクエストも歓迎します：

1. リポジトリをフォーク
2. 機能ブランチを作成 (`git checkout -b feature/AmazingFeature`)
3. 変更をコミット (`git commit -m 'Add some AmazingFeature'`)
4. ブランチにプッシュ (`git push origin feature/AmazingFeature`)
5. プルリクエストを作成

## サポート

- **ドキュメント**: このREADMEを参照
- **不具合報告**: [GitHub Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-lock-modified-date/issues)
- **開発者サイト**: https://www.tsuyoshikashiwazaki.jp/

---

<div align="center">

**Keywords**: WordPress, SEO, 更新日, ロック, post_modified, SEO最適化, メタボックス, 日付管理

Made with ❤️ by [Tsuyoshi Kashiwazaki](https://github.com/TsuyoshiKashiwazaki)

</div>


