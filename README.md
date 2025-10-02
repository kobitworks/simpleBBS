# simpleBBS

SQLite ベースのシンプルな BBS です。ボード(掲示板)とスレッド(投稿の連なり)を扱うための最小限のクラスだけで構成されています。ボードごとに SQLite のデータベースファイルを分けて保存するため、小規模な掲示板を手軽に設置できます。

## インストール

```
composer require simplebbs/simple-bbs
```

## セットアップ
1. Web ルートを `vendor/simplebbs/simple-bbs/public` に向けるか、`public/` ディレクトリの内容を任意の公開ディレクトリへ配置します。
2. `.storage/` ディレクトリを書き込み可能にするか、環境変数 `STORAGE_PATH` (または `.env` の `STORAGE_PATH`) で保存先を指定します。
3. ブラウザでアクセスすると、ボード作成からスレッド・投稿まで利用できます。

`public/index.php` では `SimpleBBS\Application` を生成し、HTTP リクエストを処理します。

## クラス概要
- `SimpleBBS\SimpleBBS`
  - ボード一覧・詳細、スレッド一覧・詳細、スレッド作成、投稿追加・編集を提供します。
- `SimpleBBS\Admin`
  - ボードの新規作成・更新・削除など管理向けの操作を提供します。
- `SimpleBBS\Application`
  - クエリパラメータ `route` に基づいて画面を切り替え、Twig テンプレートを描画します。

## 例: PHP からの直接利用
```
use SimpleBBS\Admin;
use SimpleBBS\SimpleBBS;

$storage = __DIR__ . '/bbs-data';
$admin = new Admin($storage);
$bbs = new SimpleBBS($storage);

// ボード作成
$board = $admin->createBoard('雑談', 'general', '自由な話題用ボード');

// スレッド作成
$threadId = $bbs->createThread($board['slug'], 'はじめまして', '管理人', 'よろしくお願いします。');

// 投稿追加
$bbs->addPost($board['slug'], $threadId, '名無しさん', 'こんにちは！');

// 投稿編集
$thread = $bbs->getThread($board['slug'], $threadId);
$firstPostId = $thread['posts'][0]['id'];
$bbs->updatePost($board['slug'], $threadId, $firstPostId, '管理人', '自己紹介スレッドです。');
```

`Application::create()` に `SimpleBBS` や `Admin` を渡すことで、Web アプリケーションと他システムで同じインスタンスを共有できます。

```
$app = Application::create($storage, viewsPath: __DIR__ . '/templates');
$app->handle();
```

## 必要条件
- PHP 8.1 以上
- SQLite3 拡張
- Composer

## ドキュメント
アーキテクチャの詳細は `docs/architecture.md` を参照してください。
