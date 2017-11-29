<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Import;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\InvalidAppIdException;
use ScriptFUSION\Porter\Provider\Steam\Scrape\ParserException;
use ScriptFUSION\Steam250\Database\Queries;

class Importer
{
    public const DEFAULT_CHUNKS = 0;
    public const DEFAULT_CHUNK_INDEX = 1;

    private $porter;
    private $database;
    private $logger;
    private $appListPath;
    private $chunks = self::DEFAULT_CHUNKS;
    private $chunkIndex = self::DEFAULT_CHUNK_INDEX;
    private $lite = false;

    private static $players;

    public function __construct(Porter $porter, Connection $database, LoggerInterface $logger, string $appListPath)
    {
        $this->porter = $porter;
        $this->database = $database;
        $this->logger = $logger;
        $this->appListPath = $appListPath;
    }

    public function import(): void
    {
        $this->logger->info('Starting Steam games import...');
        $this->chunks && $this->logger->info("Processing chunk $this->chunkIndex of $this->chunks.");

        $reviews = $this->porter->import(
            new AppListSpecification($this->appListPath, $this->chunks, $this->chunkIndex)
        );

        $total = \count($reviews);
        $count = 0;

        foreach ($reviews as $review) {
            $percent = (++$count / $total) * 100 | 0;

            if (Queries::doesAppExist($this->database, $review['id'])) {
                $this->logger->warning(
                    "Skipped $count/$total ($percent%) #$review[id] $review[name]: already exists."
                );

                continue;
            }

            try {
                // Decorate app with full data set.
                $review += $this->porter->importOne(new AppSpecification($review['id']));
            } catch (InvalidAppIdException | ParserException $exception) {
                // This is fine 🔥.
            }

            // Data unavailable.
            if (!isset($review['type'])) {
                if ($this->lite) {
                    $this->logger->warning(
                        "Skipped $count/$total ($percent%) #$review[id] $review[name]: invalid."
                    );

                    continue;
                }

                $this->logger->debug("#$review[id] $review[name]: invalid.");
            }

            // No reviews.
            if (isset($review['total_reviews']) && $review['total_reviews'] < 1) {
                if ($this->lite) {
                    $this->logger->warning(
                        "Skipped $count/$total ($percent%) #$review[id] $review[name]: no reviews."
                    );

                    continue;
                }

                $this->logger->debug("#$review[id] $review[name]: no reviews.");
            }

            $this->decorateWithPlayers($review);

            /*
             * Insert data. In normal mode undecorated records are inserted for idempotence, allowing import to be
             * quickly resumed later.
             */
            $this->database->insert('app', $review);
            $this->logger->info("Inserted $count/$total ($percent%) #$review[id] $review[name].");
        }

        $this->logger->info('Finished :^)');
    }

    private function decorateWithPlayers(array &$review): void
    {
        self::$players || self::$players = iterator_to_array($this->porter->import(new PlayersSpecification));

        if (!isset(self::$players[$review['id']])) {
            $this->logger->debug("Players not found for $review[id] $review[name].");

            return;
        }

        $review['players'] = self::$players[$review['id']];
    }

    public function setChunks(int $chunks): void
    {
        $this->chunks = $chunks;
    }

    public function setChunkIndex(int $chunkIndex): void
    {
        $this->chunkIndex = $chunkIndex;
    }

    public function setLite(bool $lite): void
    {
        $this->lite = $lite;
    }
}
