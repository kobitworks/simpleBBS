# simpleBBS

Composer で導入できる SQLite ベースのシンプルな BBS です。ボード(掲示板)とスレッド(投稿の連なり)に分類され、ボードごとに SQLite の DB ファイルを生成します。システム全体を管理するための SQLite DB も別途作成されます。

## 必要条件
- PHP 8.1 以上
- SQLite3 拡張
- Composer

## セットアップ
1. 依存関係をインストール
   ```bash
   composer install
   ```
2. Web ルートを `public/` に設定し、`storage/` ディレクトリに書き込み権限を付与します。
3. ブラウザでアクセスすると、ボード作成からスレッド・投稿まで利用できます。

## ドキュメント
アーキテクチャやディレクトリ構成の詳細は `docs/architecture.md` を参照してください。
