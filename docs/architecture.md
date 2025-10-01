# simpleBBS アーキテクチャ概要

## 目的
simpleBBS は、Composer で導入できるシンプルな BBS パッケージです。ボード(掲示板) とスレッド(投稿の連なり) を分離し、ボードごとに
SQLite データベースを持つ構成で、別サイトへの組み込みを前提としています。

## ディレクトリ構成
```
src/
  Application.php        ... 依存解決とルーティングを担うエントリポイント
  Controllers/          ... HTTP コントローラ層
  Core/                 ... ルーターなどコアコンポーネント
  Http/                 ... リクエストオブジェクト
  Repositories/         ... DB アクセス層
  Services/             ... ドメインロジック
  Support/              ... 共通ユーティリティ (SQLite 管理など)
public/
  index.php             ... フロントコントローラ
  css/, js/             ... アセット
resources/views/        ... Twig テンプレート
storage/                ... SQLite ファイル配置 (system.sqlite と boards/*.sqlite を生成)
docs/                   ... ドキュメント
```

## データベース
- **システムDB (`storage/system.sqlite`)**
  - `boards` テーブル: ボードのメタ情報(スラッグ、タイトル、説明、作成日時) を保持。
- **ボードDB (`storage/boards/{slug}.sqlite`)**
  - `threads` テーブル: スレッドタイトルと作成・更新日時。
  - `posts` テーブル: スレッド内の投稿(投稿者、本文、投稿日時)。

`SimpleBBS\Support\DatabaseManager` がファイル生成やスキーマ初期化を担い、必要に応じて PDO 接続を返します。

## ドメインロジック
- `BoardService`
  - ボード一覧取得、ボード作成、スラッグの正規化/重複チェックを担当。
- `ThreadService`
  - 指定ボードのスレッド一覧、スレッド作成、投稿追加、スレッド詳細取得を担当。

各サービスは対応するリポジトリ経由で DB にアクセスします。

## プレゼンテーション層
- Twig を利用したテンプレートでレイアウト(`base.twig`) を定義。
- `boards/index.twig` でボード一覧・作成フォーム、`boards/show.twig` でスレッド一覧・作成フォーム、`threads/show.twig` で投稿表示
と投稿フォームを表示します。
- フロントエンドは `public/css/main.css` の軽量スタイルのみを使用。

## ルーティング
`SimpleBBS\Application` が `Router` に以下のルートを定義します。

| ルート名 | メソッド | 説明 |
|----------|----------|------|
| `boards.index` | GET | ボード一覧を表示 |
| `boards.store` | POST | ボード作成 |
| `boards.show` | GET | ボード詳細・スレッド一覧 |
| `threads.store` | POST | スレッド作成 |
| `threads.show` | GET | スレッド詳細・投稿一覧 |
| `threads.posts.store` | POST | スレッドへの投稿追加 |

ルートはクエリパラメータ `route` で指定し、必要な `slug` や `thread` をクエリで渡します。

## 組み込み手順概要
1. `composer require simplebbs/simple-bbs` でパッケージを導入。(開発中はローカルパス指定も可能)
2. `public/index.php` を Web ルートに配置し、`storage/` ディレクトリへの書き込み権限を付与するか、環境変数 `SIMPLEBBS_STORAGE_PATH`
   で別のディレクトリを指定します。
3. 必要に応じて Twig テンプレートや CSS をカスタマイズしてサイトデザインと統一。

以上の構成により、複数のボードを持つ BBS を迅速に構築できます。
