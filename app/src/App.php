<?php

declare(strict_types=1);

defined('COST_SPLIT') || exit;

final class App
{
    public const RECIPIENT_HAS_GIFTS = 'Nie można usunąć osoby, dla której są prezenty. Najpierw zmień lub usuń jej zakupy.';

    private ?Repository $repo = null;

    /** @param array{admin_token: string, db_host: string, db_name: string, db_user: string, db_password: string, app_url: string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function handle(string $method, string $path): void
    {
        $routes = [
            ['GET', '#^/$#', fn () => $this->redirect('/admin')],
            ['GET', '#^/admin/login$#', fn () => $this->render('login', ['title' => 'Logowanie administratora'])],
            ['POST', '#^/admin/login$#', fn () => $this->login()],
            ['POST', '#^/admin/logout$#', fn () => $this->logout()],
            ['GET', '#^/admin$#', fn () => $this->adminGroups()],
            ['POST', '#^/admin/groups$#', fn () => $this->createGroup()],
            ['GET', '#^/admin/groups/(\d+)$#', fn (string $id) => $this->adminGroup((int) $id)],
            ['POST', '#^/admin/groups/(\d+)$#', fn (string $id) => $this->renameGroup((int) $id)],
            ['POST', '#^/admin/groups/(\d+)/delete$#', fn (string $id) => $this->deleteGroup((int) $id)],
            ['POST', '#^/admin/groups/(\d+)/persons$#', fn (string $id) => $this->savePersons((int) $id)],
            ['POST', '#^/admin/groups/(\d+)/persons/(\d+)/delete$#', fn (string $id, string $pid) => $this->deletePerson((int) $id, (int) $pid)],
            ['POST', '#^/admin/groups/(\d+)/recipients$#', fn (string $id) => $this->saveRecipients((int) $id)],
            ['POST', '#^/admin/groups/(\d+)/recipients/(\d+)/delete$#', fn (string $id, string $rid) => $this->deleteRecipient((int) $id, (int) $rid)],
            ['GET', '#^/p/([0-9a-f]{32})$#', fn (string $t) => $this->personPage($t)],
            ['POST', '#^/p/([0-9a-f]{32})/purchases$#', fn (string $t) => $this->addPurchase($t)],
            ['POST', '#^/p/([0-9a-f]{32})/repayments$#', fn (string $t) => $this->addRepayment($t)],
        ];
        // Edycja i usuwanie zdarzeń: administrator w całej grupie, osoba tylko w swoich zdarzeniach.
        $scopes = [
            '/admin/groups/(\d+)' => fn (string $id, callable $next) => $this->asAdmin((int) $id, $next),
            '/p/([0-9a-f]{32})' => fn (string $t, callable $next) => $this->asPerson($t, $next),
        ];
        foreach ($scopes as $prefix => $scope) {
            $routes[] = ['GET', "#^{$prefix}/purchases/(\d+)$#", fn (string $s, string $id) => $scope($s, fn (array $ctx) => $this->editPurchase($ctx, (int) $id))];
            $routes[] = ['POST', "#^{$prefix}/purchases/(\d+)$#", fn (string $s, string $id) => $scope($s, fn (array $ctx) => $this->updatePurchase($ctx, (int) $id))];
            $routes[] = ['POST', "#^{$prefix}/purchases/(\d+)/delete$#", fn (string $s, string $id) => $scope($s, fn (array $ctx) => $this->deletePurchase($ctx, (int) $id))];
            $routes[] = ['GET', "#^{$prefix}/repayments/(\d+)$#", fn (string $s, string $id) => $scope($s, fn (array $ctx) => $this->editRepayment($ctx, (int) $id))];
            $routes[] = ['POST', "#^{$prefix}/repayments/(\d+)$#", fn (string $s, string $id) => $scope($s, fn (array $ctx) => $this->updateRepayment($ctx, (int) $id))];
            $routes[] = ['POST', "#^{$prefix}/repayments/(\d+)/delete$#", fn (string $s, string $id) => $scope($s, fn (array $ctx) => $this->deleteRepayment($ctx, (int) $id))];
            $routes[] = ['POST', "#^{$prefix}/repayments/(\d+)/confirm$#", fn (string $s, string $id) => $scope($s, fn (array $ctx) => $this->confirmRepayment($ctx, (int) $id))];
        }

        foreach ($routes as [$routeMethod, $pattern, $handler]) {
            if ($method === $routeMethod && preg_match($pattern, $path, $m)) {
                if ($method === 'POST' && !$this->validCsrf()) {
                    $this->error(400, 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.');
                    return;
                }
                $handler(...array_slice($m, 1));
                return;
            }
        }
        $this->error(404, 'Nie znaleziono strony.');
    }

    // --- Administrator ---

    private function login(): void
    {
        $expected = $this->config['admin_token'];
        $token = (string) ($_POST['token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            $this->render('login', ['title' => 'Logowanie administratora', 'errors' => ['token' => 'Nieprawidłowy token.']], 401);
            return;
        }
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $this->redirect('/admin');
    }

    private function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $this->redirect('/admin/login');
    }

    private function adminGroups(array $errors = [], array $old = [], int $status = 200): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $this->render('admin_groups', [
            'title' => 'Grupy',
            'groups' => $this->repo()->groups(),
            'errors' => $errors,
            'old' => $old,
        ], $status);
    }

    private function createGroup(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $errors = array_filter(['name' => Validation::requiredError($name, 100)]);
        if ($errors) {
            $this->adminGroups($errors, $_POST, 422);
            return;
        }
        $id = $this->repo()->createGroup($name);
        $this->flash('Utworzono grupę. Dodaj osoby.');
        $this->redirect("/admin/groups/{$id}");
    }

    private function adminGroup(int $id, array $errors = [], ?array $old = null, int $status = 200): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $group = $this->repo()->group($id);
        if (!$group) {
            $this->error(404, 'Nie znaleziono grupy.');
            return;
        }
        $this->render('admin_group', [
            'title' => $group['name'],
            'group' => $group,
            'errors' => $errors,
            'old' => $old,
            'baseUrl' => $this->baseUrl(),
            'editBase' => "/admin/groups/{$id}",
            'editorId' => null,
        ] + $this->groupData($group), $status);
    }

    private function savePersons(int $id): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $group = $this->repo()->group($id);
        if (!$group) {
            $this->error(404, 'Nie znaleziono grupy.');
            return;
        }

        $errors = [];
        $input = (array) ($_POST['persons'] ?? []);
        $persons = [];
        $explicit = [];
        foreach ($this->repo()->persons($id) as $person) {
            $row = (array) ($input[$person['id']] ?? []);
            $persons[$person['id']] = $this->parsePersonRow($row, "persons[{$person['id']}]", $errors);
            $explicit[] = $persons[$person['id']]['share_bp'];
        }

        $newRow = (array) ($_POST['new'] ?? []);
        $newPerson = null;
        if (trim((string) ($newRow['name'] ?? '')) !== '') {
            $newPerson = $this->parsePersonRow($newRow, 'new', $errors);
            $explicit[] = $newPerson['share_bp'];
        } elseif (trim((string) ($newRow['email'] ?? '')) !== '' || trim((string) ($newRow['share'] ?? '')) !== '') {
            $errors['new[name]'] = 'Podaj imię nowej osoby.';
        }

        if (!$errors && $explicit && ($error = Validation::sharesSumError(Shares::effective($explicit)))) {
            $errors['shares'] = $error;
        }
        if ($errors) {
            $this->adminGroup($id, $errors, $_POST, 422);
            return;
        }

        $this->repo()->savePersons($id, $persons, $newPerson);
        $this->flash($newPerson ? 'Dodano osobę i zapisano udziały.' : 'Zapisano udziały.');
        $this->redirect("/admin/groups/{$id}");
    }

    private function renameGroup(int $id): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->repo()->group($id)) {
            $this->error(404, 'Nie znaleziono grupy.');
            return;
        }
        $name = trim((string) ($_POST['group_name'] ?? ''));
        if ($error = Validation::requiredError($name, 100)) {
            $this->adminGroup($id, ['group_name' => $error], $_POST, 422);
            return;
        }
        $this->repo()->renameGroup($id, $name);
        $this->flash('Zmieniono nazwę grupy.');
        $this->redirect("/admin/groups/{$id}");
    }

    private function deleteGroup(int $id): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $this->repo()->deleteGroup($id);
        $this->flash('Usunięto grupę.');
        $this->redirect('/admin');
    }

    private function deletePerson(int $groupId, int $personId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $persons = $this->repo()->persons($groupId);
        if (!in_array($personId, array_column($persons, 'id'), true)) {
            $this->error(404, 'Nie znaleziono osoby.');
            return;
        }
        $remaining = array_filter($persons, static fn (array $p) => $p['id'] !== $personId);
        if ($remaining && ($error = Validation::sharesSumError(Shares::effective(array_column($remaining, 'share_bp'))))) {
            $this->adminGroup($groupId, ['shares' => 'Nie można usunąć osoby. ' . $error . ' Najpierw zmień udziały.'], null, 422);
            return;
        }
        $this->repo()->deletePerson($groupId, $personId);
        $this->flash('Usunięto osobę wraz z jej zakupami i zwrotami.');
        $this->redirect("/admin/groups/{$groupId}");
    }

    private function saveRecipients(int $groupId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!$this->repo()->group($groupId)) {
            $this->error(404, 'Nie znaleziono grupy.');
            return;
        }
        $errors = [];
        $input = (array) ($_POST['recipients'] ?? []);
        $names = [];
        foreach ($this->repo()->recipients($groupId) as $r) {
            $names[$r['id']] = trim((string) ($input[$r['id']] ?? ''));
            if ($error = Validation::requiredError($names[$r['id']], 100)) {
                $errors["recipients[{$r['id']}]"] = $error;
            }
        }
        $newName = trim((string) ($_POST['new_recipient'] ?? ''));
        if ($newName !== '' && ($error = Validation::requiredError($newName, 100))) {
            $errors['new_recipient'] = $error;
        }
        if ($errors) {
            $this->adminGroup($groupId, $errors, $_POST, 422);
            return;
        }
        $this->repo()->saveRecipients($groupId, $names, $newName === '' ? null : $newName);
        $this->flash($newName === '' ? 'Zapisano osoby obdarowane.' : 'Dodano osobę obdarowaną.');
        $this->redirect("/admin/groups/{$groupId}");
    }

    private function deleteRecipient(int $groupId, int $recipientId): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        if (!in_array($recipientId, array_column($this->repo()->recipients($groupId), 'id'), true)) {
            $this->error(404, 'Nie znaleziono osoby obdarowanej.');
            return;
        }
        if (!$this->repo()->deleteRecipient($groupId, $recipientId)) {
            $this->adminGroup($groupId, ['recipients' => self::RECIPIENT_HAS_GIFTS], null, 422);
            return;
        }
        $this->flash('Usunięto osobę obdarowaną.');
        $this->redirect("/admin/groups/{$groupId}");
    }

    /**
     * Każdy zakup musi wskazywać osobę obdarowaną z grupy.
     *
     * @return array{0: ?int, 1: ?string} id osoby obdarowanej i komunikat błędu
     */
    private function parseRecipient(int $groupId, mixed $raw): array
    {
        $id = (int) $raw;
        return in_array($id, array_column($this->repo()->recipients($groupId), 'id'), true)
            ? [$id, null]
            : [null, 'Wybierz, dla kogo jest prezent.'];
    }

    /** @return array{name: string, email: ?string, share_bp: ?int} */
    private function parsePersonRow(array $row, string $prefix, array &$errors): array
    {
        $name = trim((string) ($row['name'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        $share = trim((string) ($row['share'] ?? ''));
        if ($error = Validation::requiredError($name, 100)) {
            $errors["{$prefix}[name]"] = $error;
        }
        if ($email !== '' && (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errors["{$prefix}[email]"] = 'Nieprawidłowy adres e-mail.';
        }
        if ($share !== '' && ($error = Validation::percentError($share))) {
            $errors["{$prefix}[share]"] = $error;
        }
        return [
            'name' => $name,
            'email' => $email === '' ? null : $email,
            'share_bp' => $share === '' ? null : Validation::parsePercent($share),
        ];
    }

    // --- Osoba ---

    private function personPage(string $token, array $errors = [], array $old = [], int $status = 200): void
    {
        $person = $this->repo()->personByToken($token);
        if (!$person) {
            $this->error(404, 'Nieprawidłowy link dostępowy.');
            return;
        }
        $group = $this->repo()->group($person['group_id']);
        $this->render('person', [
            'title' => $group['name'],
            'person' => $person,
            'group' => $group,
            'errors' => $errors,
            'old' => $old,
            'editBase' => "/p/{$token}",
            'editorId' => $person['id'],
        ] + $this->groupData($group), $status);
    }

    private function addPurchase(string $token): void
    {
        $person = $this->repo()->personByToken($token);
        if (!$person) {
            $this->error(404, 'Nieprawidłowy link dostępowy.');
            return;
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = (string) ($_POST['amount'] ?? '');
        [$recipientId, $recipientError] = $this->parseRecipient($person['group_id'], $_POST['recipient'] ?? '');
        $errors = array_filter([
            'name' => Validation::requiredError($name, 200),
            'amount' => Validation::amountError($amount),
            'recipient' => $recipientError,
        ]);
        if ($errors) {
            $this->personPage($token, $errors, $_POST, 422);
            return;
        }
        $this->repo()->addPurchase($person['id'], $recipientId, $name, Validation::parseMoney($amount));
        $this->flash('Dodano zakup.');
        $this->redirect("/p/{$token}");
    }

    /**
     * Odbiorca potwierdza odbiór (zwrot od razu potwierdzony) albo dłużnik zgłasza wysłany zwrot,
     * który czeka na potwierdzenie odbiorcy.
     */
    private function addRepayment(string $token): void
    {
        $person = $this->repo()->personByToken($token);
        if (!$person) {
            $this->error(404, 'Nieprawidłowy link dostępowy.');
            return;
        }
        $sent = ($_POST['_form'] ?? '') === 'sent';
        $field = $sent ? 'to' : 'from';
        $otherId = (int) ($_POST[$field] ?? 0);
        $amount = (string) ($_POST['amount'] ?? '');
        $validOther = array_filter(
            $this->repo()->persons($person['group_id']),
            static fn (array $p) => $p['id'] === $otherId && $p['id'] !== $person['id'],
        );
        $errors = array_filter([
            $field => $validOther ? null : 'Wybierz osobę.',
            'amount' => Validation::amountError($amount),
        ]);
        if ($errors) {
            $this->personPage($token, $errors, $_POST, 422);
            return;
        }
        $money = Validation::parseMoney($amount);
        if ($sent) {
            $this->repo()->addRepayment($person['id'], $otherId, $money, false);
            $this->flash('Zgłoszono zwrot. Będzie uwzględniony po potwierdzeniu odbioru.');
        } else {
            $this->repo()->addRepayment($otherId, $person['id'], $money, true);
            $this->flash('Potwierdzono odbiór.');
        }
        $this->redirect("/p/{$token}");
    }

    // --- Edycja zdarzeń ---

    /** @param callable(array{group: array, base: string, actor: ?array}): void $next */
    private function asAdmin(int $groupId, callable $next): void
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $group = $this->repo()->group($groupId);
        if (!$group) {
            $this->error(404, 'Nie znaleziono grupy.');
            return;
        }
        $next(['group' => $group, 'base' => "/admin/groups/{$groupId}", 'actor' => null]);
    }

    /** @param callable(array{group: array, base: string, actor: ?array}): void $next */
    private function asPerson(string $token, callable $next): void
    {
        $person = $this->repo()->personByToken($token);
        if (!$person) {
            $this->error(404, 'Nieprawidłowy link dostępowy.');
            return;
        }
        $next(['group' => $this->repo()->group($person['group_id']), 'base' => "/p/{$token}", 'actor' => $person]);
    }

    /** Zakup może zmienić administrator lub osoba, która go wprowadziła. */
    private function findPurchase(array $ctx, int $id): ?array
    {
        $purchase = $this->repo()->purchase((int) $ctx['group']['id'], $id);
        if (!$purchase || ($ctx['actor'] !== null && $purchase['person_id'] !== $ctx['actor']['id'])) {
            $this->error(404, 'Nie znaleziono zakupu.');
            return null;
        }
        return $purchase;
    }

    /**
     * Zwrot może zmienić administrator, odbiorca, a dopóki nie jest potwierdzony – także osoba, która go zgłosiła.
     */
    private function findRepayment(array $ctx, int $id): ?array
    {
        $repayment = $this->repo()->repayment((int) $ctx['group']['id'], $id);
        if (!$repayment || !self::canEditRepayment($repayment, $ctx['actor']['id'] ?? null)) {
            $this->error(404, 'Nie znaleziono zwrotu.');
            return null;
        }
        return $repayment;
    }

    private function editPurchase(array $ctx, int $id, array $errors = [], ?array $old = null, int $status = 200): void
    {
        if (!$purchase = $this->findPurchase($ctx, $id)) {
            return;
        }
        $this->render('purchase_edit', [
            'title' => 'Edycja zakupu',
            'group' => $ctx['group'],
            'base' => $ctx['base'],
            'isAdminScope' => $ctx['actor'] === null,
            'persons' => $this->repo()->persons((int) $ctx['group']['id']),
            'recipients' => $this->repo()->recipients((int) $ctx['group']['id']),
            'purchase' => $purchase,
            'errors' => $errors,
            'old' => $old ?? [
                'person' => (string) $purchase['person_id'],
                'recipient' => (string) $purchase['recipient_id'],
                'name' => $purchase['name'],
                'amount' => Format::moneyInput($purchase['amount']),
            ],
        ], $status);
    }

    private function updatePurchase(array $ctx, int $id): void
    {
        if (!$purchase = $this->findPurchase($ctx, $id)) {
            return;
        }
        $personId = $ctx['actor'] === null ? (int) ($_POST['person'] ?? 0) : $purchase['person_id'];
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = (string) ($_POST['amount'] ?? '');
        [$recipientId, $recipientError] = $this->parseRecipient((int) $ctx['group']['id'], $_POST['recipient'] ?? '');
        $errors = array_filter([
            'person' => $this->isGroupMember($ctx, $personId) ? null : 'Wybierz osobę.',
            'name' => Validation::requiredError($name, 200),
            'amount' => Validation::amountError($amount),
            'recipient' => $recipientError,
        ]);
        if ($errors) {
            $this->editPurchase($ctx, $id, $errors, $_POST, 422);
            return;
        }
        $this->repo()->updatePurchase($id, $personId, $recipientId, $name, Validation::parseMoney($amount));
        $this->flash('Zapisano zakup.');
        $this->redirect($ctx['base']);
    }

    private function deletePurchase(array $ctx, int $id): void
    {
        if (!$this->findPurchase($ctx, $id)) {
            return;
        }
        $this->repo()->deletePurchase($id);
        $this->flash('Usunięto zakup.');
        $this->redirect($ctx['base']);
    }

    public static function canEditRepayment(array $repayment, ?int $actorId): bool
    {
        return $actorId === null || $repayment['to'] === $actorId || (!$repayment['confirmed'] && $repayment['from'] === $actorId);
    }

    /** Potwierdzić odbiór może tylko odbiorca lub administrator. */
    public static function canConfirmRepayment(array $repayment, ?int $actorId): bool
    {
        return !$repayment['confirmed'] && ($actorId === null || $repayment['to'] === $actorId);
    }

    /** Pole, którego osoba nie może zmienić w zwrocie: swoją stronę przelewu. Administrator zmienia wszystko. */
    private static function fixedRepaymentSide(array $repayment, ?int $actorId): ?string
    {
        return match ($actorId) {
            null => null,
            $repayment['to'] => 'to',
            default => 'from',
        };
    }

    private function editRepayment(array $ctx, int $id, array $errors = [], ?array $old = null, int $status = 200): void
    {
        if (!$repayment = $this->findRepayment($ctx, $id)) {
            return;
        }
        $actorId = $ctx['actor']['id'] ?? null;
        $this->render('repayment_edit', [
            'title' => 'Edycja zwrotu',
            'group' => $ctx['group'],
            'base' => $ctx['base'],
            'fixed' => self::fixedRepaymentSide($repayment, $actorId),
            'canSetStatus' => $actorId === null || $repayment['to'] === $actorId,
            'persons' => $this->repo()->persons((int) $ctx['group']['id']),
            'repayment' => $repayment,
            'errors' => $errors,
            'old' => $old ?? [
                'from' => (string) $repayment['from'],
                'to' => (string) $repayment['to'],
                'amount' => Format::moneyInput($repayment['amount']),
                'confirmed' => $repayment['confirmed'] ? '1' : '0',
            ],
        ], $status);
    }

    private function updateRepayment(array $ctx, int $id): void
    {
        if (!$repayment = $this->findRepayment($ctx, $id)) {
            return;
        }
        $actorId = $ctx['actor']['id'] ?? null;
        $fixed = self::fixedRepaymentSide($repayment, $actorId);
        $fromId = $fixed === 'from' ? $repayment['from'] : (int) ($_POST['from'] ?? 0);
        $toId = $fixed === 'to' ? $repayment['to'] : (int) ($_POST['to'] ?? 0);
        $amount = (string) ($_POST['amount'] ?? '');
        $confirmed = $actorId === null || $repayment['to'] === $actorId
            ? ($_POST['confirmed'] ?? '') === '1'
            : false;
        $errors = array_filter([
            'from' => $this->isGroupMember($ctx, $fromId) ? null : 'Wybierz osobę.',
            'to' => $this->isGroupMember($ctx, $toId) ? null : 'Wybierz osobę.',
            'amount' => Validation::amountError($amount),
        ]);
        if (!$errors && $fromId === $toId) {
            $errors['from'] = 'Wybierz dwie różne osoby.';
        }
        if ($errors) {
            $this->editRepayment($ctx, $id, $errors, $_POST, 422);
            return;
        }
        $this->repo()->updateRepayment($id, $fromId, $toId, Validation::parseMoney($amount), $confirmed);
        $this->flash('Zapisano zwrot.');
        $this->redirect($ctx['base']);
    }

    private function deleteRepayment(array $ctx, int $id): void
    {
        if (!$this->findRepayment($ctx, $id)) {
            return;
        }
        $this->repo()->deleteRepayment($id);
        $this->flash('Usunięto zwrot.');
        $this->redirect($ctx['base']);
    }

    private function confirmRepayment(array $ctx, int $id): void
    {
        $repayment = $this->repo()->repayment((int) $ctx['group']['id'], $id);
        if (!$repayment || !self::canConfirmRepayment($repayment, $ctx['actor']['id'] ?? null)) {
            $this->error(404, 'Nie znaleziono zwrotu do potwierdzenia.');
            return;
        }
        $this->repo()->confirmRepayment($id);
        $this->flash('Potwierdzono odbiór.');
        $this->redirect($ctx['base']);
    }

    private function isGroupMember(array $ctx, int $personId): bool
    {
        return in_array($personId, array_column($this->repo()->persons((int) $ctx['group']['id']), 'id'), true);
    }

    // --- Wspólne ---

    private function groupData(array $group): array
    {
        $persons = $this->repo()->persons((int) $group['id']);
        $purchases = $this->repo()->purchases((int) $group['id']);
        $repayments = $this->repo()->repayments((int) $group['id']);
        $recipients = $this->repo()->recipients((int) $group['id']);

        $ids = array_column($persons, 'id');
        $shares = $ids ? array_combine($ids, Shares::effective(array_column($persons, 'share_bp'))) : [];
        $names = array_column($persons, 'name', 'id');
        $pending = [];
        foreach ($repayments as $r) {
            if (!$r['confirmed']) {
                $pending[$r['from']][$r['to']] = ($pending[$r['from']][$r['to']] ?? 0) + $r['amount'];
            }
        }
        $totals = [];
        foreach ($purchases as $pu) {
            $totals[$pu['person_id']] = ($totals[$pu['person_id']] ?? 0) + $pu['amount'];
        }

        return [
            'persons' => $persons,
            'names' => $names,
            'shares' => $shares,
            'purchases' => $purchases,
            'recipients' => $recipients,
            'gifts' => Gifts::totals($recipients, $purchases),
            'repayments' => $repayments,
            'settlement' => Settlement::compute($shares, $totals, array_filter($repayments, static fn (array $r) => $r['confirmed'])),
            'pending' => $pending,
            'sharesError' => $shares ? Validation::sharesSumError(array_values($shares)) : null,
        ];
    }

    private function repo(): Repository
    {
        return $this->repo ??= Repository::connect($this->config);
    }

    private function requireAdmin(): bool
    {
        if (empty($_SESSION['admin'])) {
            $this->redirect('/admin/login');
            return false;
        }
        return true;
    }

    private function validCsrf(): bool
    {
        return is_string($_POST['_csrf'] ?? null) && hash_equals(self::csrfToken(), $_POST['_csrf']);
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    private function flash(string $message): void
    {
        $_SESSION['flash'] = $message;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $this->baseUrl() . $path, true, 303);
    }

    /** Adres katalogu aplikacji bez końcowego ukośnika, np. https://example.pl/koszty */
    private function baseUrl(): string
    {
        if ($this->config['app_url'] !== '') {
            return rtrim($this->config['app_url'], '/');
        }
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
        $dir = Routing::basePath($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
    }

    private function error(int $status, string $message): void
    {
        $this->render('error', ['title' => 'Błąd', 'message' => $message], $status);
    }

    private function render(string $template, array $vars, int $status = 200): void
    {
        http_response_code($status);
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        $vars += ['errors' => [], 'old' => [], 'flash' => $flash, 'isAdmin' => !empty($_SESSION['admin'])];
        View::render($template, $vars);
    }
}
