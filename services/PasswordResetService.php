<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/EmailService.php';

/**
 * Erzeugt einen Passwort-Reset-Token und verschickt den Link per E-Mail.
 * Wird von "Passwort vergessen" UND vom "Passwort per Link zurücksetzen"
 * Button im eingeloggten Profil genutzt.
 */
class PasswordResetService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param string $typ 'employees' oder 'user'
     */
    public function sendResetLink(
        string $typ,
        int $id,
        string $email,
        string $vorName
    ): bool {
        $typ = $typ === 'employees' ? 'employees' : 'user';
        $tabelleSicher = $typ === 'employees' ? 'employees' : '`user`';

        $tokenRoh = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $tokenRoh);
        $ablauf = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            "UPDATE {$tabelleSicher}
             SET reset_token = :token, reset_token_ablauf = :ablauf
             WHERE id = :id"
        );
        $statement->execute([
            'token' => $tokenHash,
            'ablauf' => $ablauf,
            'id' => $id,
        ]);

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $resetLink = 'http://' . $host . Auth::baseUrl()
            . '/pages/reset_password.php?token=' . $tokenRoh
            . '&typ=' . $typ;

        $emailService = new EmailService();

        return $emailService->sendPasswortReset($email, $vorName, $resetLink);
    }

    /**
     * Findet Account (employees oder user) anhand der E-Mail.
     * Gibt ['id' => ..., 'vor_name' => ..., 'email' => ..., 'typ' => ...] zurück oder null.
     */
    public function findKontoPerEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, vor_name, email FROM employees WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $treffer = $statement->fetch(PDO::FETCH_ASSOC);

        if ($treffer !== false) {
            $treffer['typ'] = 'employees';
            return $treffer;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, vor_name, email FROM `user` WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $treffer = $statement->fetch(PDO::FETCH_ASSOC);

        if ($treffer !== false) {
            $treffer['typ'] = 'user';
            return $treffer;
        }

        return null;
    }
}
