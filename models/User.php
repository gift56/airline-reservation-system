<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private PDO $pdo)
    {
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    public function createPassenger(string $fullName, string $email, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, role)
             VALUES (:full_name, :email, :password_hash, 'passenger')"
        );
        $statement->execute([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);
    }

    /**
     * @return array{id: string, full_name: string, email: string, password_hash: string, role: string}|null
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, full_name, email, password_hash, role FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($record)) {
            return null;
        }

        return [
            'id' => (string) $record['id'],
            'full_name' => (string) $record['full_name'],
            'email' => (string) $record['email'],
            'password_hash' => (string) $record['password_hash'],
            'role' => (string) $record['role'],
        ];
    }
}
