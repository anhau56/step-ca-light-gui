<?php
require_once 'auth.php';
require_once 'lib/logger.php';

// Get filter parameters
$filterAction = $_GET['action'] ?? '';
$filterUser = $_GET['user'] ?? '';
$filterCert = $_GET['cert'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;

// Build filters
$filters = [];
if ($filterAction) $filters['action'] = $filterAction;
if ($filterUser) $filters['user'] = $filterUser;
if ($filterCert) $filters['certificate'] = $filterCert;
if ($filterDateFrom) $filters['date_from'] = $filterDateFrom;
if ($filterDateTo) $filters['date_to'] = $filterDateTo;

// Get logs
$offset = ($page - 1) * $perPage;
$result = AuditLogger::getLogs($filters, $perPage, $offset);
$logs = $result['entries'];
$total = $result['total'];
$totalPages = ceil($total / $perPage);

// Get available actions and users for filters
$actions = AuditLogger::getActions();
$users = AuditLogger::getUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Step CA</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container wide">
        <div class="header">
            <div class="header-content">
                <div class="icon">📋</div>
                <h1>Audit Logs</h1>
                <p class="subtitle">View all certificate operations</p>
            </div>
            <button type="button" class="theme-toggle" id="theme-toggle" title="Toggle theme">🌙</button>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters">
            <select name="action">
                <option value="">All Actions</option>
                <?php foreach ($actions as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $filterAction === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="user">
                <option value="">All Users</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= htmlspecialchars($user) ?>" <?= $filterUser === $user ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="cert" placeholder="Certificate name..." value="<?= htmlspecialchars($filterCert) ?>">

            <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" title="From date">
            <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>" title="To date">

            <button type="submit" class="btn btn-sm">Filter</button>
            <a href="logs.php" class="btn btn-sm btn-secondary">Reset</a>
        </form>

        <!-- Results count -->
        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 15px;">
            Showing <?= count($logs) ?> of <?= $total ?> entries
        </p>

        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <p>No log entries found.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Certificate</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?></td>
                                <td><?= htmlspecialchars($log['user']) ?></td>
                                <td>
                                    <span class="validity <?= ($log['details']['status'] ?? '') === 'failed' ? 'expired' : 'valid' ?>">
                                        <?= htmlspecialchars($actions[$log['action']] ?? $log['action']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['certificate'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($log['ip']) ?></td>
                                <td style="font-size: 11px; max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php
                                    $details = $log['details'] ?? [];
                                    unset($details['status']);
                                    echo htmlspecialchars(json_encode($details) ?: '-');
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="margin-top: 20px; text-align: center;">
                    <?php
                    $queryParams = $_GET;
                    for ($i = 1; $i <= $totalPages; $i++):
                        $queryParams['page'] = $i;
                        $url = '?' . http_build_query($queryParams);
                    ?>
                        <?php if ($i === $page): ?>
                            <span class="btn btn-sm" style="opacity: 0.5;"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= $url ?>" class="btn btn-sm btn-secondary"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="nav-links">
            <a href="index.php">← Generate Certificate</a>
            <a href="download-cert.php">My Certificates</a>
            <a href="download-ca.php">CA Certificates</a>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
