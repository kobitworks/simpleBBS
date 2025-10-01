<?php

namespace SimpleBBS;

use SimpleBBS\Boards\BoardManager;
use SimpleBBS\Management\SystemManager;
use SimpleBBS\Repositories\BoardRepository;
use SimpleBBS\Repositories\ThreadRepository;
use SimpleBBS\Services\BoardService;
use SimpleBBS\Services\ThreadService;
use SimpleBBS\Support\DatabaseManager;
use SimpleBBS\Threads\ThreadManager;

/**
 * SimpleBBS のコンポーネントをまとめて提供するエントリポイント。
 *
 * public/index.php からの単体利用だけでなく、他システムへの
 * 組み込み時にも本クラスを new / create して利用できます。
 */
class SimpleBBS
{
    private BoardManager $boardManager;
    private ThreadManager $threadManager;
    private SystemManager $systemManager;

    private BoardService $boardService;
    private ThreadService $threadService;

    public function __construct(private readonly DatabaseManager $databaseManager)
    {
        $boardRepository = new BoardRepository($this->databaseManager);
        $threadRepository = new ThreadRepository($this->databaseManager);

        $this->boardService = new BoardService($boardRepository);
        $this->threadService = new ThreadService($threadRepository);

        $this->boardManager = new BoardManager($this->boardService);
        $this->threadManager = new ThreadManager($this->threadService);
        $this->systemManager = new SystemManager($this->databaseManager);
    }

    public static function create(?string $storagePath = null): self
    {
        $packageRoot = dirname(__DIR__);
        $storagePath ??= $packageRoot . '/.storage';

        $databaseManager = new DatabaseManager($storagePath);

        return new self($databaseManager);
    }

    public static function boot(string $storagePath): self
    {
        return self::create($storagePath);
    }

    public function boards(): BoardManager
    {
        return $this->boardManager;
    }

    public function threads(): ThreadManager
    {
        return $this->threadManager;
    }

    public function system(): SystemManager
    {
        return $this->systemManager;
    }

    public function database(): DatabaseManager
    {
        return $this->databaseManager;
    }

    public function boardService(): BoardService
    {
        return $this->boardService;
    }

    public function threadService(): ThreadService
    {
        return $this->threadService;
    }
}
