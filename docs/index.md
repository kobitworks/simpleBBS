# simpleBBS 開発者向け仕様

このドキュメントは、simpleBBS パッケージの現在仕様を開発者向けに整理したものです。実装を拡張・保守する際の参照資料として利用してください。

## 全体概要
- SQLite を用いた単純な掲示板アプリケーションです。
- ボード(掲示板)・スレッド(話題)・投稿の 3 階層で構成されます。
- ボードごとに独立した SQLite データベースファイルを生成し、掲示板単位でデータを分離します。
- Web UI は `SimpleBBS\Application` が HTTP リクエストを受け取り、Twig テンプレートを描画します。
- PHP から直接 `SimpleBBS` / `Admin` クラスを利用することで、CLI や他システムからも掲示板機能を呼び出せます。

## 動作要件
- PHP 8.1 以上
- SQLite3 拡張
- Composer (パッケージ管理)

## ディレクトリ構成
```
src/
  Application.php   ... HTTP ルーティングとテンプレート描画
  SimpleBBS.php     ... 掲示板機能のコア実装
  Admin.php         ... ボード管理機能 (SimpleBBS を継承)
  autoload.php      ... シンプルな PSR-4 互換オートローダ
public/
  index.php         ... Web アプリのフロントコントローラ
resources/views/    ... Twig テンプレート
.storage/           ... SQLite データファイルのデフォルト配置先
```

## ストレージ設定
- 既定では、パッケージルート直下の `.storage/` ディレクトリを使用します。
- `.storage/` が存在しない場合は自動的に作成されます。作成できない場合は `RuntimeException` を投げます。
- `.storage/system.sqlite` が「システム DB」で、ボード定義を保存します。
- 各ボード用データベースは `.storage/boards/{slug}.sqlite` に作成されます。
- 書き込み先を変更する場合は、`SimpleBBS` / `Admin` / `Application::create()` に `storagePath` を渡すか、Web ホスティング時に `.env` の `STORAGE_PATH` を設定します。

## データモデル
### システム DB (`system.sqlite`)
| テーブル | カラム | 型 | 説明 |
|----------|--------|----|------|
| `boards` | `slug` | TEXT | ボード識別子 (主キー) |
|          | `title` | TEXT | ボード名 |
|          | `description` | TEXT NULL | 説明文 |
|          | `created_at` | TEXT | 作成日時 (UTC) |
|          | `updated_at` | TEXT | 最終更新日時 (UTC) |

### ボード DB (`boards/{slug}.sqlite`)
| テーブル | カラム | 型 | 説明 |
|----------|--------|----|------|
| `threads` | `id` | INTEGER | スレッド ID (PK, AUTOINCREMENT) |
|           | `title` | TEXT | スレッドタイトル |
|           | `created_at` | TEXT | 作成日時 (UTC) |
|           | `updated_at` | TEXT | 最終更新日時 (UTC) |
| `posts`   | `id` | INTEGER | 投稿 ID (PK, AUTOINCREMENT) |
|           | `thread_id` | INTEGER | 所属スレッド ID (FK → threads.id, ON DELETE CASCADE) |
|           | `author_name` | TEXT | 投稿者名 (未入力時は「名無しさん」) |
|           | `body` | TEXT | 本文 |
|           | `created_at` | TEXT | 作成日時 (UTC) |

`SimpleBBS` は接続確立時にスキーマを自動初期化し、欠損している `updated_at` カラムを追加・補完します。

## PHP クラス仕様

### `SimpleBBS\SimpleBBS`
掲示板機能のコアクラス。主な責務は以下の通りです。
- ストレージパスの初期化 (`__construct`, `storagePath`)
- ボード・スレッド・投稿の CRUD サポート (`listBoards`, `getBoard`, `listThreads`, `getThread`, `createThread`, `addPost`, `updatePost`)
- SQLite 接続管理 (`systemConnection`, `boardConnection`, `ensureBoardDatabase`)
- データ整形 (`normaliseSlug`, `filterAuthor`, `now`)
- 更新日時の同期 (`touchBoard`, `touchThread`)

#### 代表的なメソッド一覧
| メソッド | 引数 | 戻り値 | バリデーション・例外 |
|----------|------|--------|------------------------|
| `__construct(?string $storagePath = null)` | 任意のストレージパス | - | ディレクトリ生成失敗時 `RuntimeException` |
| `listBoards()` | - | `array<int, array>` | 更新日時降順で全ボードを返す |
| `getBoard(string $slug)` | ボードスラッグ | `array` | スラッグ正規化、存在しない場合 `InvalidArgumentException` |
| `listThreads(string $boardSlug)` | ボードスラッグ | `array<int, array>` | スレッドごとの投稿数付き一覧 |
| `getThread(string $boardSlug, int $threadId)` | ボードスラッグ, スレッド ID | `array` (投稿配列含む) | 存在しない場合 `InvalidArgumentException` |
| `createThread(string $boardSlug, string $title, ?string $authorName, string $body)` | 必須: タイトル・本文 | `int` (新規スレッド ID) | タイトル/本文空の場合 `InvalidArgumentException` |
| `addPost(string $boardSlug, int $threadId, ?string $authorName, string $body)` | 本文必須 | `void` | 本文空/スレッド不在時は `InvalidArgumentException` |
| `updatePost(string $boardSlug, int $threadId, int $postId, ?string $authorName, string $body)` | 本文必須 | `void` | 投稿未検出/スレッド不一致は `InvalidArgumentException` |

トランザクション管理はスレッド作成、投稿追加・更新で実施されます。失敗時はロールバックし例外を再送出します。

### `SimpleBBS\Admin`
- `SimpleBBS` を継承し、ボード管理操作を追加します。
- 主なメソッド:
  - `createBoard(string $title, ?string $slug = null, ?string $description = null): array`
    - タイトル必須、スラッグは手動指定可。既存スラッグと重複すると `InvalidArgumentException`。
    - 正常終了時は作成済みボード情報を返却し、対応するボード DB を初期化します。
  - `updateBoard(string $slug, string $title, ?string $description = null): array`
    - 既存ボードをタイトル・説明で更新し、更新後情報を返却します。
  - `deleteBoard(string $slug): void`
    - システム DB から該当ボードを削除し、ボード DB ファイルを削除します。

### `SimpleBBS\Application`
Web アプリケーションエントリポイント。`route` クエリパラメータと HTTP メソッドを基に処理を分岐します。
- `__construct(SimpleBBS $bbs, Admin $admin, ?Environment $view = null, ?string $viewsPath = null)`
  - Twig `Environment` を受け取るか、`viewsPath` から自動生成します (キャッシュ無効)。
- `public static function create(?string $storagePath = null, ?Environment $view = null, ?string $viewsPath = null): self`
  - `SimpleBBS` と `Admin` を同一ストレージで初期化してアプリケーションを生成します。
- `handle()`
  - `$_GET['route']` と `$_SERVER['REQUEST_METHOD']` を解釈し、GET/POST を振り分けます。
  - 400 番台の入力エラーはメッセージ付きで `error.twig` を描画し、500 エラーは汎用メッセージを表示します。

#### ルーティング定義
| ルート | メソッド | 概要 | 使用テンプレート | 主なバリデーション |
|--------|----------|------|--------------------|----------------------|
| `boards` | GET | ボード一覧 + 新規作成フォーム | `boards.twig` | - |
| `board` | GET | ボード詳細 (スレッド一覧) | `board.twig` | `slug` 必須 |
| `thread` | GET | スレッド詳細 (投稿一覧) | `thread.twig` | `slug`, `thread` 必須 |
| `board_create` | POST | ボード作成 | 成功時 `?route=board&slug=...` へリダイレクト | タイトル必須、スラッグ重複禁止 |
| `thread_create` | POST | 新規スレッド作成 | 成功時スレッド詳細へリダイレクト | `slug` 必須、タイトル/本文必須 |
| `post_create` | POST | スレッドへの返信 | 成功時スレッド詳細へリダイレクト | `slug`, `thread` 必須、本文必須 |
| `post_update` | POST | 投稿編集 | 成功時スレッド詳細へリダイレクト | `slug`, `thread`, `post` 必須、本文必須 |

入力エラー時は HTTP 422 を返し、該当画面を再描画します。`errors` 配列と `old` 入力値がテンプレートへ渡されるため、フォーム再表示で利用できます。`thread` 画面では `editingPostId` に編集中投稿 ID を渡します。

## テンプレートへのコンテキスト
| テンプレート | 変数 | 説明 |
|--------------|------|------|
| `boards.twig` | `boards` | ボード一覧 (`listBoards()` 結果) |
|              | `errors` | バリデーションエラーメッセージ配列 |
|              | `old` | 直前入力値 (`title`, `slug`, `description`) |
| `board.twig` | `board` | `getBoard()` 結果 |
|              | `threads` | `listThreads()` 結果 |
|              | `errors` | スレッド作成エラー |
|              | `old['thread']` | スレッド作成フォーム再表示用入力値 |
| `thread.twig` | `board` | `getBoard()` 結果 |
|               | `thread` | `getThread()` 結果 (投稿配列含む) |
|               | `errors` | 投稿作成/編集エラー |
|               | `old['reply']` | 返信フォームの入力値 |
|               | `old['post']` | 投稿編集フォームの入力値 |
|               | `editingPostId` | 編集対象投稿 ID (null で未編集) |
| `error.twig` | `message` | 表示メッセージ |
|              | `details` | 500 エラー時に例外メッセージを表示 (省略可) |

## HTTP 入力仕様
- すべてのフォームは `application/x-www-form-urlencoded` を前提としています。
- CSRF 対策は実装されていないため、導入時はアプリケーション側での対処が必要です。
- 文字コードは UTF-8。
- 投稿本文は改行を許容し、保存時にトリムされます (先頭末尾のみ)。
- 投稿者名は空の場合に "名無しさん" に置き換えられます。

## エラーハンドリング
- 入力エラー (`InvalidArgumentException`) は 400 / 422 応答としてテンプレートにメッセージを表示します。
- 想定外の例外は 500 応答で汎用メッセージと例外メッセージ詳細を表示します。
- SQLite 接続失敗時やディレクトリ作成失敗時は `RuntimeException` を送出し、アプリケーションレベルで捕捉されない場合は 500 エラーになります。

## オートローディング
- `src/autoload.php` は名前空間 `SimpleBBS\` を PSR-4 ルールで解決する簡易オートローダを登録します。
- Composer を利用する場合は `composer.json` の `autoload` に同梱して利用してください。

## 拡張時の注意事項
1. 既存 DB スキーマを変更する場合は、`initialiseSystemSchema` / `initialiseBoardSchema` にマイグレーション処理を追加してください。
2. 新規ルートを追加する場合は `Application::handleGet` / `handlePost` の分岐と対応テンプレートを追加します。
3. テンプレートへ渡すコンテキストは連想配列で統一されているため、新規要素を追加する際は既存キーとの競合に注意してください。
4. エラー文言は全て日本語で統一されています。メッセージ追加時も日本語表記としてください。

## 参考
- 利用例・セットアップ手順は `README.md` を参照してください。
- アーキテクチャ概要は `docs/architecture.md` に記載しています。
