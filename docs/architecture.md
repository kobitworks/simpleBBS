# simpleBBS アーキテクチャ概要

## 目的
simpleBBS は SQLite を利用したシンプルな掲示板です。ボード(掲示板) とスレッド(投稿のまとまり) で構成され、最小限のクラス構成で動作します。ボードごとに SQLite ファイルを分けることで、掲示板単位でのデータ分離を実現しています。

## ディレクトリ構成
```
src/
  Application.php   ... HTTP ルーティングと描画を担当するエントリポイント
  SimpleBBS.php     ... ボード・スレッド・投稿表示と更新のコア機能
  Admin.php         ... ボード作成や更新など管理向け機能
public/
  index.php         ... フロントコントローラ
  css/              ... スタイルシート
  js/               ... JavaScript(任意)
resources/views/    ... Twig テンプレート
.storage/           ... SQLite ファイル配置先 (自動生成)
```

## データベース
- **システムDB (`.storage/system.sqlite`)**
  - `boards` テーブル: ボードのスラッグ、タイトル、説明、作成・更新日時を保持します。
- **ボードDB (`.storage/boards/{slug}.sqlite`)**
  - `threads` テーブル: スレッドのタイトルと作成・更新日時を保持します。
  - `posts` テーブル: スレッド内の投稿(投稿者、本文、作成日時) を保持します。

`SimpleBBS\SimpleBBS` が SQLite の生成とスキーマ初期化を自動的に行います。

## クラス構成
- `SimpleBBS\SimpleBBS`
  - ボード・スレッド・投稿の取得、スレッド作成、投稿追加・編集を担当します。
  - ボード DB への接続やスキーマ管理もこのクラスが行います。
- `SimpleBBS\Admin`
  - `SimpleBBS` を継承し、ボードの新規作成、更新、削除など管理者向け操作を提供します。
- `SimpleBBS\Application`
  - `route` クエリパラメータを基にルーティングし、Twig でテンプレートを描画します。
  - POST 処理では `SimpleBBS` / `Admin` のメソッドを呼び出してリダイレクトまたはエラー表示を行います。

## ルーティング
`SimpleBBS\Application` では以下のルートを扱います。

| ルート | メソッド | 説明 |
|--------|----------|------|
| `boards` | GET | ボード一覧を表示 |
| `board` | GET | 指定ボードのスレッド一覧を表示 |
| `thread` | GET | スレッド詳細と投稿一覧を表示 |
| `board_create` | POST | ボードを新規作成 |
| `thread_create` | POST | 新しいスレッドを作成 |
| `post_create` | POST | スレッドに返信を追加 |
| `post_update` | POST | 投稿内容を編集 |

## テンプレート
Twig テンプレートは `resources/views` に配置されています。

- `base.twig` : 共通レイアウト。
- `boards.twig` : ボード一覧と作成フォーム。
- `board.twig` : スレッド一覧とスレッド作成フォーム。
- `thread.twig` : 投稿一覧、返信フォーム、投稿編集フォーム。
- `error.twig` : エラー表示用。

## セットアップ
1. `.storage/` ディレクトリを書き込み可能にするか、環境変数 `STORAGE_PATH` で保存先を指定します。
2. ブラウザで `public/index.php` にアクセスすると掲示板を利用できます。

最小限のクラス構成により、他システムへの組み込みやカスタマイズも容易です。
