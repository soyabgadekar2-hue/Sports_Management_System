```php
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../config/database.php';

requireAnyRole(['ADMIN', 'SPORTS_COORDINATOR']);

$pdo = db();

/*
|--------------------------------------------------------------------------
| Get Teams
|--------------------------------------------------------------------------
*/

$statement = $pdo->query(
    'SELECT
        t.team_id,
        t.team_name,
        t.team_category,
        t.roster_limit,
        t.team_status,
        s.sport_name,
        u.full_name AS coach_name,
        COUNT(
            CASE
                WHEN tp.membership_status = "ACTIVE"
                THEN tp.team_player_id
            END
        ) AS current_players
     FROM teams t
     INNER JOIN sports s
        ON s.sport_id = t.sport_id
     LEFT JOIN coach_profiles cp
        ON cp.coach_id = t.coach_id
     LEFT JOIN users u
        ON u.user_id = cp.user_id
     LEFT JOIN team_players tp
        ON tp.team_id = t.team_id
     GROUP BY
        t.team_id,
        t.team_name,
        t.team_category,
        t.roster_limit,
        t.team_status,
        s.sport_name,
        u.full_name
     ORDER BY
        s.sport_name ASC,
        t.team_name ASC'
);

$teams = $statement->fetchAll(PDO::FETCH_ASSOC);

$message = $_GET['message'] ?? '';

$pageTitle = 'Team Management';

require_once __DIR__ . '/../includes/header.php';

?>

<style>

/* =========================================================
   TEAM MANAGEMENT PAGE
   ========================================================= */

.team-management-page {
    max-width: 1200px;
    margin: 0 auto;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.team-management-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.team-management-header-content {
    flex: 1;
}

.team-management-label {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    margin-bottom: 10px;
}

.team-management-header h1 {
    margin: 0 0 8px;
    color: #111827;
    font-size: 30px;
    line-height: 1.2;
}

.team-management-header p {
    margin: 0;
    color: #6b7280;
    font-size: 15px;
}

.team-management-header-icon {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 18px;
    background: #eff6ff;
    font-size: 30px;
}


/* =========================================================
   SUCCESS MESSAGE
   ========================================================= */

.team-success-message {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 22px;
    padding: 14px 16px;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    background: #f0fdf4;
    color: #166534;
}

.team-success-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 50%;
    background: #dcfce7;
    font-size: 16px;
}

.team-success-message strong {
    font-size: 14px;
}


/* =========================================================
   ACTION
   ========================================================= */

.team-management-actions {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
}

.create-team-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 18px;
    border: 1px solid #2563eb;
    border-radius: 10px;
    background: #2563eb;
    color: #ffffff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: 0.2s ease;
}

.create-team-button:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    transform: translateY(-1px);
}


/* =========================================================
   TEAMS CARD
   ========================================================= */

.teams-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.teams-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 22px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.teams-card-header-content h2 {
    margin: 0 0 5px;
    color: #111827;
    font-size: 20px;
}

.teams-card-header-content p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}

.team-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 13px;
    font-weight: 700;
}


/* =========================================================
   TABLE
   ========================================================= */

.teams-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.teams-table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

.teams-table thead th {
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #475569;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.teams-table tbody td {
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #374151;
    font-size: 14px;
    vertical-align: middle;
}

.teams-table tbody tr:last-child td {
    border-bottom: none;
}

.teams-table tbody tr {
    transition: background 0.15s ease;
}

.teams-table tbody tr:hover {
    background: #f8fafc;
}


/* =========================================================
   TEAM INFORMATION
   ========================================================= */

.team-id {
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.team-name-cell {
    min-width: 190px;
}

.team-name {
    display: block;
    color: #111827;
    font-weight: 700;
}

.team-name-subtitle {
    display: block;
    margin-top: 3px;
    color: #94a3b8;
    font-size: 12px;
}


/* =========================================================
   BADGES
   ========================================================= */

.sport-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 8px;
    background: #f1f5f9;
    color: #334155;
    font-size: 12px;
    font-weight: 600;
}

.category-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 8px;
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
    font-size: 12px;
    font-weight: 600;
}


/* =========================================================
   COACH
   ========================================================= */

.coach-name {
    color: #374151;
    font-weight: 500;
}

.coach-unassigned {
    color: #9ca3af;
    font-style: italic;
}


/* =========================================================
   PLAYERS
   ========================================================= */

.player-count {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #374151;
    font-weight: 600;
}


/* =========================================================
   ROSTER
   ========================================================= */

.roster-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 90px;
}

.roster-number {
    color: #111827;
    font-weight: 600;
    font-size: 13px;
}

.roster-bar {
    width: 80px;
    height: 5px;
    overflow: hidden;
    border-radius: 999px;
    background: #e5e7eb;
}

.roster-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: #2563eb;
}


/* =========================================================
   STATUS
   ========================================================= */

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.status-active {
    background: #dcfce7;
    color: #15803d;
}

.status-inactive {
    background: #fef2f2;
    color: #b91c1c;
}

.status-other {
    background: #f1f5f9;
    color: #475569;
}


/* =========================================================
   MANAGE BUTTON
   ========================================================= */

.manage-team-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 8px 13px;
    border: 1px solid #dbeafe;
    border-radius: 8px;
    background: #eff6ff;
    color: #2563eb;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: 0.2s ease;
}

.manage-team-button:hover {
    background: #dbeafe;
    border-color: #bfdbfe;
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.teams-empty-state {
    padding: 55px 25px;
    text-align: center;
}

.teams-empty-icon {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    border-radius: 18px;
    background: #eff6ff;
    font-size: 28px;
}

.teams-empty-state h3 {
    margin: 0 0 7px;
    color: #111827;
    font-size: 18px;
}

.teams-empty-state p {
    margin: 0 0 20px;
    color: #6b7280;
    font-size: 14px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 700px) {

    .team-management-header {
        align-items: flex-start;
    }

    .team-management-header h1 {
        font-size: 25px;
    }

    .team-management-header-icon {
        width: 52px;
        height: 52px;
        font-size: 25px;
    }

    .teams-card-header {
        padding: 18px;
    }

    .team-management-actions {
        justify-content: stretch;
    }

    .create-team-button {
        width: 100%;
    }

}

</style>


<div class="dashboard-page">

    <div class="team-management-page">


        <!-- PAGE HEADER -->

        <section class="team-management-header">

            <div class="team-management-header-content">

                <div class="team-management-label">
                    TEAM MANAGEMENT
                </div>

                <h1>
                    Teams
                </h1>

                <p>
                    Create and manage sports teams, coaches, players,
                    and roster limits from one place.
                </p>

            </div>

            <div class="team-management-header-icon">
                👥
            </div>

        </section>


        <!-- SUCCESS MESSAGE -->

        <?php if ($message === 'created'): ?>

            <div class="team-success-message">

                <div class="team-success-icon">
                    ✓
                </div>

                <div>
                    <strong>
                        Team created successfully.
                    </strong>
                </div>

            </div>

        <?php endif; ?>


        <!-- CREATE TEAM -->

        <div class="team-management-actions">

            <a
                href="create-team.php"
                class="create-team-button"
            >
                <span>＋</span>
                Create New Team
            </a>

        </div>


        <!-- TEAMS -->

        <section class="teams-card">

            <div class="teams-card-header">

                <div class="teams-card-header-content">

                    <h2>
                        Existing Teams
                    </h2>

                    <p>
                        View and manage all teams registered in SportSync.
                    </p>

                </div>

                <div class="team-count-badge">
                    <?= count($teams) ?>
                </div>

            </div>


            <?php if (empty($teams)): ?>

                <div class="teams-empty-state">

                    <div class="teams-empty-icon">
                        👥
                    </div>

                    <h3>
                        No teams found
                    </h3>

                    <p>
                        Create your first sports team to get started.
                    </p>

                    <a
                        href="create-team.php"
                        class="create-team-button"
                    >
                        ＋ Create New Team
                    </a>

                </div>


            <?php else: ?>

                <div class="teams-table-wrapper">

                    <table class="teams-table">

                        <thead>

                            <tr>
                                <th>ID</th>
                                <th>Team</th>
                                <th>Sport</th>
                                <th>Category</th>
                                <th>Coach</th>
                                <th>Players</th>
                                <th>Roster</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($teams as $team): ?>

                                <?php

                                $currentPlayers =
                                    (int) $team['current_players'];

                                $rosterLimit =
                                    $team['roster_limit'] !== null
                                        ? (int) $team['roster_limit']
                                        : null;

                                $teamStatus = strtoupper(
                                    (string) $team['team_status']
                                );

                                if (
                                    $rosterLimit !== null &&
                                    $rosterLimit > 0
                                ) {
                                    $rosterPercentage = min(
                                        100,
                                        (
                                            $currentPlayers /
                                            $rosterLimit
                                        ) * 100
                                    );
                                } else {
                                    $rosterPercentage = 0;
                                }

                                if ($teamStatus === 'ACTIVE') {
                                    $statusClass = 'status-active';
                                } elseif (
                                    in_array(
                                        $teamStatus,
                                        ['INACTIVE', 'SUSPENDED'],
                                        true
                                    )
                                ) {
                                    $statusClass = 'status-inactive';
                                } else {
                                    $statusClass = 'status-other';
                                }

                                ?>

                                <tr>

                                    <td>
                                        <span class="team-id">
                                            #<?= (int) $team['team_id'] ?>
                                        </span>
                                    </td>


                                    <td class="team-name-cell">

                                        <span class="team-name">

                                            <?= htmlspecialchars(
                                                (string) $team['team_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                        <span class="team-name-subtitle">
                                            SportSync Team
                                        </span>

                                    </td>


                                    <td>

                                        <span class="sport-badge">

                                            <?= htmlspecialchars(
                                                (string) $team['sport_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="category-badge">

                                            <?= htmlspecialchars(
                                                (string) $team['team_category'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php if (!empty($team['coach_name'])): ?>

                                            <span class="coach-name">

                                                <?= htmlspecialchars(
                                                    (string) $team['coach_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="coach-unassigned">
                                                Not assigned
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <span class="player-count">
                                            👤 <?= $currentPlayers ?>
                                        </span>

                                    </td>


                                    <td>

                                        <div class="roster-info">

                                            <span class="roster-number">

                                                <?php if ($rosterLimit !== null): ?>

                                                    <?= $currentPlayers ?>
                                                    /
                                                    <?= $rosterLimit ?>

                                                <?php else: ?>

                                                    Unlimited

                                                <?php endif; ?>

                                            </span>


                                            <?php if ($rosterLimit !== null): ?>

                                                <div class="roster-bar">

                                                    <div
                                                        class="roster-bar-fill"
                                                        style="width: <?= number_format($rosterPercentage, 2, '.', '') ?>%;"
                                                    ></div>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </td>


                                    <td>

                                        <span
                                            class="status-badge <?= $statusClass ?>"
                                        >

                                            <span class="status-dot"></span>

                                            <?= htmlspecialchars(
                                                $teamStatus,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <a
                                            href="manage-team.php?team_id=<?= (int) $team['team_id'] ?>"
                                            class="manage-team-button"
                                        >
                                            Manage →
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
```
