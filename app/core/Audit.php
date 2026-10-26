<?php
declare(strict_types=1);

/** Append-only audit trail. There is deliberately no update/delete API and no UI for either. */
final class Audit
{
    public static function log(string $action, string $module, int|string|null $recordId, string $description, ?array $details = null, ?int $userId = null, ?string $username = null): void
    {
        $u = Auth::user();
        db_run('INSERT INTO audit_logs (user_id, username, action, module, record_id, description, details, ip_address)
                VALUES (?,?,?,?,?,?,?,?)', [
            $userId ?? ($u['id'] ?? null),
            substr($username ?? ($u['username'] ?? 'system'), 0, 60),
            $action, $module, $recordId === null ? null : (string)$recordId,
            mb_substr($description, 0, 500),
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            client_ip(),
        ]);
    }
    /** @return array<string,array{0:mixed,1:mixed}> changed fields as [old, new] */
    public static function diff(array $old, array $new, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $a = $old[$f] ?? null;
            $b = $new[$f] ?? null;
            if ((string)$a !== (string)$b) {
                $out[$f] = [$a, $b];
            }
        }
        return $out;
    }
}
