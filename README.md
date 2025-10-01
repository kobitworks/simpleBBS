# simpleBBS

Composer で導入できる SQLite ベースのシンプルな BBS です。ボード(掲示板)とスレッド(投稿の連なり)に分類され、ボードごとに SQLite
の DB ファイルを生成します。システム全体を管理するための SQLite DB も別途作成されます。

## インストール

```bash
composer require simplebbs/simple-bbs
```

## セットアップ
1. Web ルートを `vendor/simplebbs/simple-bbs/public` に向けるか、`public/` ディレクトリの内容を任意の公開ディレクトリに配置します。
2. `.storage/` ディレクトリを BBS のデータ格納用に書き込み可能へ設定するか、`.env` (または環境変数 `SIMPLEBBS_STORAGE_PATH`) で任意の書き込み先パスを指定します。設定例は `sample.env` を参照してください。
3. ブラウザでアクセスすると、ボード作成からスレッド・投稿まで利用できます。

`public/index.php` では `SimpleBBS\\Application` を生成し、HTTP リクエストを処理します。設置先で Twig のカスタマイズを行いたい場合は、
`SimpleBBS\\Application::create()` の第 2 引数以降に Twig Environment やビューのパスを渡してください。

### 設定項目

`.env` または環境変数で以下の項目を設定できます。未指定の場合は既定値が使用されます。

- `SIMPLEBBS_REQUIRE_LOGIN` (既定値: `false`)
  - `true` の場合はログイン必須となり、認証の設定がないとアプリケーションが起動しません。
- `SIMPLEBBS_ALLOW_ANONYMOUS_POST` (既定値: `true`)
  - 匿名でのスレッド作成・投稿を許可します。`false` にすると未ログイン時は投稿できません。
- `SIMPLEBBS_ALLOW_USER_BOARD_CREATION` (既定値: `true`)
  - ユーザーによる新規ボード作成を許可します。`false` にすると作成フォームが表示されません。

### 認証設定

ログインを利用する場合は Google OAuth クライアントを用意し、以下の環境変数を設定してください。

- `SIMPLEBBS_GOOGLE_CLIENT_ID`
- `SIMPLEBBS_GOOGLE_CLIENT_SECRET`
- `SIMPLEBBS_GOOGLE_REDIRECT_URI` (例: `https://example.com/index.php?route=auth.callback`)

他システムに組み込んで利用する場合は、`SimpleBBS\Auth\PreAuthenticatedAuthenticator` を利用して認証済みユーザー情報を渡してください。

ログインを必須にしない場合は上記の環境変数を設定しなくても動作します。

```php
use SimpleBBS\Auth\PreAuthenticatedAuthenticator;
use SimpleBBS\Auth\User;
use SimpleBBS\Application;

$user = new User('123', '山田 太郎', 'taro@example.com');
$authenticator = new PreAuthenticatedAuthenticator($user);
$app = Application::create(authenticator: $authenticator);
```

## 他システムからの利用

`SimpleBBS\\SimpleBBS` を生成することで、ボードやスレッド操作用のファサードクラスに直接アクセスできます。

```php
use SimpleBBS\SimpleBBS;

$bbs = SimpleBBS::create('/path/to/storage');

// ボード一覧を取得
$boards = $bbs->boards()->listBoards();

// スレッドの作成
$threadId = $bbs->threads()->createThread('general', 'はじめまして', '管理人', 'よろしくお願いします。');

// ストレージに関する情報へアクセス
$storagePath = $bbs->system()->storagePath();
```

`SimpleBBS\\Application::create()` に `SimpleBBS` インスタンスを渡すことで、Web アプリケーションと他システムで同じコンポーネン
ト構成を共有することも可能です。

## 必要条件
- PHP 8.1 以上
- SQLite3 拡張
- Composer

## ドキュメント
アーキテクチャやディレクトリ構成の詳細は `docs/architecture.md` を参照してください。
