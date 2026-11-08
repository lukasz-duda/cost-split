<?php

declare(strict_types=1);

defined('COST_SPLIT') || exit;

final class Repository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function connect(array $config): self
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
        $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return new self($pdo);
    }

    public function groups(): array
    {
        return $this->pdo->query(
            'SELECT g.*, COUNT(p.id) AS person_count FROM `groups` g
             LEFT JOIN persons p ON p.group_id = g.id GROUP BY g.id ORDER BY g.name'
        )->fetchAll();
    }

    public function group(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM `groups` WHERE id = ?', [$id]);
    }

    public function createGroup(string $name): int
    {
        $this->pdo->prepare('INSERT INTO `groups` (name) VALUES (?)')
            ->execute([$name]);
        return (int) $this->pdo->lastInsertId();
    }

    public function renameGroup(int $id, string $name): void
    {
        $this->pdo->prepare('UPDATE `groups` SET name = ? WHERE id = ?')->execute([$name, $id]);
    }

    /** Usuwa grupę razem z osobami, osobami obdarowanymi, zakupami i zwrotami. */
    public function deleteGroup(int $id): void
    {
        $this->transaction(function () use ($id) {
            $persons = 'SELECT id FROM persons WHERE group_id = ?';
            $this->pdo->prepare("DELETE FROM repayments WHERE from_person_id IN ({$persons}) OR to_person_id IN ({$persons})")
                ->execute([$id, $id]);
            $this->pdo->prepare("DELETE FROM purchases WHERE person_id IN ({$persons})")->execute([$id]);
            $this->pdo->prepare('DELETE FROM persons WHERE group_id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM recipients WHERE group_id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM `groups` WHERE id = ?')->execute([$id]);
        });
    }

    /** Usuwa osobę razem z jej zakupami i zwrotami, w których uczestniczyła. */
    public function deletePerson(int $groupId, int $personId): void
    {
        $this->transaction(function () use ($groupId, $personId) {
            $stmt = $this->pdo->prepare('SELECT id FROM persons WHERE id = ? AND group_id = ? FOR UPDATE');
            $stmt->execute([$personId, $groupId]);
            if (!$stmt->fetch()) {
                return;
            }
            $this->pdo->prepare('DELETE FROM repayments WHERE from_person_id = ? OR to_person_id = ?')->execute([$personId, $personId]);
            $this->pdo->prepare('DELETE FROM purchases WHERE person_id = ?')->execute([$personId]);
            $this->pdo->prepare('DELETE FROM persons WHERE id = ?')->execute([$personId]);
        });
    }

    public function persons(int $groupId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM persons WHERE group_id = ? ORDER BY id');
        $stmt->execute([$groupId]);
        return array_map(self::castPerson(...), $stmt->fetchAll());
    }

    public function personByToken(string $token): ?array
    {
        $row = $this->fetchOne('SELECT * FROM persons WHERE access_token = ?', [$token]);
        return $row ? self::castPerson($row) : null;
    }

    /**
     * Zapisuje zmiany osób i (opcjonalnie) nową osobę w jednej transakcji,
     * dzięki czemu suma udziałów w grupie nigdy nie przestaje wynosić 100%.
     *
     * @param array<int, array{name: string, email: ?string, share_bp: ?int}> $persons
     * @param array{name: string, email: ?string, share_bp: ?int}|null $newPerson
     */
    public function savePersons(int $groupId, array $persons, ?array $newPerson): void
    {
        $this->transaction(function () use ($groupId, $persons, $newPerson) {
            $update = $this->pdo->prepare('UPDATE persons SET name = ?, email = ?, share_bp = ? WHERE id = ? AND group_id = ?');
            foreach ($persons as $id => $p) {
                $update->execute([$p['name'], $p['email'], $p['share_bp'], $id, $groupId]);
            }
            if ($newPerson !== null) {
                $this->pdo->prepare('INSERT INTO persons (group_id, name, email, share_bp, access_token) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$groupId, $newPerson['name'], $newPerson['email'], $newPerson['share_bp'], bin2hex(random_bytes(16))]);
            }
        });
    }

    /** @return list<array{id: int, group_id: int, name: string}> */
    public function recipients(int $groupId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recipients WHERE group_id = ? ORDER BY name, id');
        $stmt->execute([$groupId]);
        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['group_id'] = (int) $row['group_id'];
            return $row;
        }, $stmt->fetchAll());
    }

    /** @param array<int, string> $names nazwy istniejących osób obdarowanych według id */
    public function saveRecipients(int $groupId, array $names, ?string $newName): void
    {
        $this->transaction(function () use ($groupId, $names, $newName) {
            $update = $this->pdo->prepare('UPDATE recipients SET name = ? WHERE id = ? AND group_id = ?');
            foreach ($names as $id => $name) {
                $update->execute([$name, $id, $groupId]);
            }
            if ($newName !== null) {
                $this->pdo->prepare('INSERT INTO recipients (group_id, name) VALUES (?, ?)')->execute([$groupId, $newName]);
            }
        });
    }

    /** Usuwa osobę obdarowaną, o ile nie ma przypisanych prezentów. Zwraca false, gdy je ma. */
    public function deleteRecipient(int $groupId, int $recipientId): bool
    {
        $deleted = true;
        $this->transaction(function () use ($groupId, $recipientId, &$deleted) {
            $stmt = $this->pdo->prepare('SELECT id FROM recipients WHERE id = ? AND group_id = ? FOR UPDATE');
            $stmt->execute([$recipientId, $groupId]);
            if (!$stmt->fetch()) {
                return;
            }
            $stmt = $this->pdo->prepare('SELECT 1 FROM purchases WHERE recipient_id = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$recipientId]);
            if ($stmt->fetch()) {
                $deleted = false;
                return;
            }
            $this->pdo->prepare('DELETE FROM recipients WHERE id = ?')->execute([$recipientId]);
        });
        return $deleted;
    }

    public function purchases(int $groupId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pu.*, pe.name AS person_name, re.name AS recipient_name FROM purchases pu
             JOIN persons pe ON pe.id = pu.person_id
             JOIN recipients re ON re.id = pu.recipient_id
             WHERE pe.group_id = ? ORDER BY pu.created_at DESC, pu.id DESC'
        );
        $stmt->execute([$groupId]);
        return array_map(self::castPurchase(...), $stmt->fetchAll());
    }

    public function purchase(int $groupId, int $id): ?array
    {
        $row = $this->fetchOne(
            'SELECT pu.*, pe.name AS person_name, re.name AS recipient_name FROM purchases pu
             JOIN persons pe ON pe.id = pu.person_id
             JOIN recipients re ON re.id = pu.recipient_id
             WHERE pu.id = ? AND pe.group_id = ?',
            [$id, $groupId],
        );
        return $row ? self::castPurchase($row) : null;
    }

    public function updatePurchase(int $id, int $personId, int $recipientId, string $name, int $amount): void
    {
        $this->pdo->prepare('UPDATE purchases SET person_id = ?, recipient_id = ?, name = ?, amount = ? WHERE id = ?')
            ->execute([$personId, $recipientId, $name, $amount, $id]);
    }

    public function deletePurchase(int $id): void
    {
        $this->pdo->prepare('DELETE FROM purchases WHERE id = ?')->execute([$id]);
    }

    public function addPurchase(int $personId, int $recipientId, string $name, int $amount): void
    {
        $this->pdo->prepare('INSERT INTO purchases (person_id, recipient_id, name, amount) VALUES (?, ?, ?, ?)')
            ->execute([$personId, $recipientId, $name, $amount]);
    }

    /** @return list<array{id: int, from: int, to: int, amount: int, confirmed: bool, created_at: string}> */
    public function repayments(int $groupId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.* FROM repayments r
             JOIN persons p ON p.id = r.to_person_id
             WHERE p.group_id = ? ORDER BY r.created_at DESC, r.id DESC'
        );
        $stmt->execute([$groupId]);
        return array_map(self::castRepayment(...), $stmt->fetchAll());
    }

    /** @return array{id: int, from: int, to: int, amount: int, confirmed: bool, created_at: string}|null */
    public function repayment(int $groupId, int $id): ?array
    {
        $row = $this->fetchOne(
            'SELECT r.* FROM repayments r JOIN persons p ON p.id = r.to_person_id WHERE r.id = ? AND p.group_id = ?',
            [$id, $groupId],
        );
        return $row ? self::castRepayment($row) : null;
    }

    public function updateRepayment(int $id, int $fromPersonId, int $toPersonId, int $amount, bool $confirmed): void
    {
        $this->pdo->prepare('UPDATE repayments SET from_person_id = ?, to_person_id = ?, amount = ?, confirmed = ? WHERE id = ?')
            ->execute([$fromPersonId, $toPersonId, $amount, (int) $confirmed, $id]);
    }

    public function confirmRepayment(int $id): void
    {
        $this->pdo->prepare('UPDATE repayments SET confirmed = 1 WHERE id = ?')->execute([$id]);
    }

    public function deleteRepayment(int $id): void
    {
        $this->pdo->prepare('DELETE FROM repayments WHERE id = ?')->execute([$id]);
    }

    public function addRepayment(int $fromPersonId, int $toPersonId, int $amount, bool $confirmed): void
    {
        $this->pdo->prepare('INSERT INTO repayments (from_person_id, to_person_id, amount, confirmed) VALUES (?, ?, ?, ?)')
            ->execute([$fromPersonId, $toPersonId, $amount, (int) $confirmed]);
    }

    private function transaction(callable $work): void
    {
        $this->pdo->beginTransaction();
        try {
            $work();
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private static function castPerson(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['group_id'] = (int) $row['group_id'];
        $row['share_bp'] = $row['share_bp'] === null ? null : (int) $row['share_bp'];
        return $row;
    }

    private static function castPurchase(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['person_id'] = (int) $row['person_id'];
        $row['recipient_id'] = (int) $row['recipient_id'];
        $row['amount'] = (int) $row['amount'];
        return $row;
    }

    private static function castRepayment(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'from' => (int) $row['from_person_id'],
            'to' => (int) $row['to_person_id'],
            'amount' => (int) $row['amount'],
            'confirmed' => (bool) $row['confirmed'],
            'created_at' => $row['created_at'],
        ];
    }
}
