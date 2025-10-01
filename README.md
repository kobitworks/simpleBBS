# simpleBBS

Composer で導入できる SQLite ベースのシンプルな BBS です。ボード(掲示板)とスレッド(投稿の連なり)に分類され、ボードごとに SQLite
の DB ファイルを生成します。システム全体を管理するための SQLite DB も別途作成されます。

## インストール

```bash
composer require simplebbs/simple-bbs
```

## セットアップ
1. Web ルートを `vendor/simplebbs/simple-bbs/public` に向けるか、`public/` ディレクトリの内容を任意の公開ディレクトリに配置します。
2. `storage/` ディレクトリを BBS のデータ格納用に書き込み可能へ設定するか、環境変数 `SIMPLEBBS_STORAGE_PATH` で任意の書き込み先パスを指定します。
3. ブラウザでアクセスすると、ボード作成からスレッド・投稿まで利用できます。

`public/index.php` では `SimpleBBS\\Application` を生成し、HTTP リクエストを処理します。設置先で Twig のカスタマイズを行いたい場合は、
`SimpleBBS\\Application::create()` の第 2 引数以降に Twig Environment やビューのパスを渡してください。

## 必要条件
- PHP 8.1 以上
- SQLite3 拡張
- Composer

## ドキュメント
アーキテクチャやディレクトリ構成の詳細は `docs/architecture.md` を参照してください。
