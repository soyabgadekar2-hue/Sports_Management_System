<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

function auditLog(
    string $actionType,
    string $entityType,
    ?int $entityId,
    string $actionSummary,
    ?string $previousData = null,
    ?string $newData = null
): void {
    $user = currentUser();

    $actorUserId = $user['id'] ?? null;

    $sql = '
        INSERT INTO audit_logs (
            actor_user_id,
            action_type,
            entity_type,
            entity_id,
            action_summary,
            previous_data,
            new_data
        )
        VALUES (
            :actor_user_id,
            :action_type,
            :entity_type,
            :entity_id,
            :action_summary,
            :previous_data,
            :new_data
        )
    ';

    try {
        $statement = db()->prepare($sql);

        $statement->execute([
            ':actor_user_id' => $actorUserId,
            ':action_type' => $actionType,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':action_summary' => $actionSummary,
            ':previous_data' => $previousData,
            ':new_data' => $newData,
        ]);
    } catch (Throwable $exception) {
        error_log('Audit logging failed: ' . $exception->getMessage());
    }
}