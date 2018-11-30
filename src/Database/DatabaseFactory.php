<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class DatabaseFactory
{
    public function create(string $path = 'steam.sqlite'): Connection
    {
        $connection = DriverManager::getConnection(['url' => "sqlite:///$path"]);

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS app (
                id INTEGER PRIMARY KEY NOT NULL,
                name TEXT NOT NULL,
                type TEXT,
                total_reviews INTEGER,
                positive_reviews INTEGER,
                negative_reviews INTEGER,
                steam_reviews INTEGER,
                release_date INTEGER,
                platforms INTEGER,
                price INTEGER,
                discount_price INTEGER,
                discount INTEGER,
                vrx INTEGER,
                free INTEGER,
                ea INTEGER,
                owners INTEGER
            );
            CREATE TABLE IF NOT EXISTS app_tag (
                app_id INTEGER NOT NULL,
                tag TEXT NOT NULL,
                votes INTEGER NOT NULL,
                PRIMARY KEY(app_id, tag)
            );
            CREATE TABLE IF NOT EXISTS app_developer (
                app_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                PRIMARY KEY(app_id, name)
            );
            CREATE TABLE IF NOT EXISTS app_publisher (
                app_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                PRIMARY KEY(app_id, name)
            );
            CREATE TABLE IF NOT EXISTS app_players (
                app_id INTEGER PRIMARY KEY NOT NULL,
                average_players_7d INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS patron_review (
                app_id INTEGER NOT NULL,
                profile_id TEXT NOT NULL,
                positive INTEGER NOT NULL,
                PRIMARY KEY(app_id, profile_id)
            );
            CREATE TABLE IF NOT EXISTS steam_profile (
                profile_id TEXT PRIMARY KEY NOT NULL,
                avatar_url TEXT NOT NULL
            );'
        );

        return $connection;
    }
}
