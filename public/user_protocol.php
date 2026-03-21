<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Controllers\UserController;
use App\Helpers\Auth;

Auth::requireEditor();

function getSafeProtocolReturnTo(?string $candidate, int $userId): string {
    $candidate = trim((string) $candidate);
    if ($candidate !== '') {
        $parts = parse_url($candidate);
        if ($parts !== false
            && !isset($parts['scheme'])
            && !isset($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass'])) {
            $path = ltrim((string) ($parts['path'] ?? ''), '/');
            if (in_array($path, ['assets.php', 'user_edit.php'], true)) {
                $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
                return $path . $query;
            }
        }
    }

    return 'user_edit.php?id=' . $userId;
}

if ((!isset($_GET['id']) || !is_numeric($_GET['id']))
    && (!isset($_GET['history_id']) || !is_numeric($_GET['history_id']))
    && !isset($_GET['history_ids'])) {
    header('Location: users.php');
    exit;
}

$protocolType = ($_GET['type'] ?? 'handover') === 'return' ? 'return' : 'handover';
$userId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

$db = Database::getInstance();
$userController = new UserController($db);
$assetController = new AssetController($db);

$historyId = isset($_GET['history_id']) && is_numeric($_GET['history_id']) ? (int) $_GET['history_id'] : null;
$historyIds = [];
if (isset($_GET['history_ids'])) {
    $historyIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $_GET['history_ids'])), static fn($id) => $id > 0)));
}
$singleAssetId = isset($_GET['asset_id']) && is_numeric($_GET['asset_id']) ? (int) $_GET['asset_id'] : null;

$historyEntry = $historyId ? $assetController->getAssignmentById($historyId) : null;
$historyEntries = [];
if (!$historyEntry && !empty($historyIds)) {
    $historyEntries = $assetController->getAssignmentsByIds($historyIds);
}

if ($historyEntry) {
    $userId = (int) $historyEntry['user_id'];
} elseif (!empty($historyEntries)) {
    $userId = (int) ($historyEntries[0]['user_id'] ?? 0);
}

if ($userId <= 0) {
    header('Location: users.php');
    exit;
}

$user = $userController->getUserById($userId);
if (!$user) {
    header('Location: users.php');
    exit;
}

$assets = [];
if ($historyEntry) {
    $assets[] = [
        'id' => $historyEntry['asset_id'],
        'name' => $historyEntry['asset_name'],
        'model_name' => $historyEntry['model_name'],
        'serial' => $historyEntry['serial'],
        'asset_tag' => $historyEntry['asset_tag'],
        'event_date' => $protocolType === 'return' ? ($historyEntry['checkin_at'] ?? $historyEntry['checkout_at']) : $historyEntry['checkout_at'],
    ];
} elseif (!empty($historyEntries)) {
    foreach ($historyEntries as $entry) {
        $assets[] = [
            'id' => $entry['asset_id'],
            'name' => $entry['asset_name'],
            'model_name' => $entry['model_name'],
            'serial' => $entry['serial'],
            'asset_tag' => $entry['asset_tag'],
            'event_date' => $protocolType === 'return' ? ($entry['checkin_at'] ?? $entry['checkout_at']) : $entry['checkout_at'],
        ];
    }
} else {
    $assets = $assetController->getAssetsByUserId($userId);
    if ($singleAssetId) {
        $assets = array_values(array_filter($assets, static fn($asset) => (int) $asset['id'] === $singleAssetId));
    }
}
$settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch() ?: [];

$eventDateRaw = null;
if ($historyEntry) {
    $eventDateRaw = $protocolType === 'return' ? ($historyEntry['checkin_at'] ?? null) : ($historyEntry['checkout_at'] ?? null);
} elseif (!empty($historyEntries)) {
    $firstEntry = $historyEntries[0];
    $eventDateRaw = $protocolType === 'return' ? ($firstEntry['checkin_at'] ?? null) : ($firstEntry['checkout_at'] ?? null);
}
$protocolDate = $eventDateRaw ? date('d.m.Y', strtotime($eventDateRaw)) : date('d.m.Y');
$protocolTitle = $protocolType === 'return' ? 'RUECKGABEPROTOKOLL IT-HARDWARE' : 'AUSGABEPROTOKOLL IT-HARDWARE';
$protocolVerb = $protocolType === 'return' ? 'Rueckgabe' : 'Ausgabe';
$companyAddress = trim((string) ($settings['company_address'] ?? ''));
$headerText = trim((string) ($settings['protocol_header_text'] ?? ''));
$footerText = trim((string) ($settings['protocol_footer_text'] ?? ''));

if ($headerText === '') {
    $headerText = 'Die unten aufgefuehrte IT-Hardware wird hiermit dokumentiert. Mit Ihrer Unterschrift bestaetigen Sie den ordnungsgemaessen Vorgang.';
}
if ($footerText === '') {
    $footerText = 'IT-Protokoll. Fuer Ihre/unsere Unterlagen.';
}

$typeExplanation = $protocolType === 'return'
    ? 'Mit diesem Protokoll bestaetigen Sie die vollstaendige Rueckgabe der unten aufgefuehrten IT-Hardware an die IT-Abteilung.'
    : 'Mit diesem Protokoll bestaetigen Sie den Erhalt der unten aufgefuehrten IT-Hardware zur dienstlichen Nutzung.';

$fullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($fullName === '') {
    $fullName = (string) ($user['username'] ?? 'Unbekannter Benutzer');
}

$returnTo = getSafeProtocolReturnTo($_GET['return_to'] ?? '', $userId);
$returnLabel = str_starts_with($returnTo, 'assets.php') ? 'Zurueck zu den Assets' : 'Zurueck zum Benutzer';

$locationLines = array_filter([
    $user['location_name'] ?? '',
    $user['location_address'] ?? '',
    $user['location_city'] ?? '',
]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($protocolVerb . 'protokoll'); ?> - <?php echo htmlspecialchars($fullName); ?></title>
    <style>
        :root {
            --border-main: #d6dce5;
            --text-main: #101827;
            --text-muted: #475569;
            --panel-bg: #ffffff;
            --page-bg: #eef2f7;
            --accent: #0f172a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--page-bg);
            color: var(--text-main);
        }
        .toolbar {
            max-width: 960px;
            margin: 1rem auto 0;
            padding: 0 1rem;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        .toolbar button,
        .toolbar a {
            border: 0;
            background: #0f172a;
            color: #fff;
            padding: 0.7rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.95rem;
            cursor: pointer;
        }
        .toolbar a { background: #475569; }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 1rem auto 2rem;
            background: var(--panel-bg);
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.18);
            padding: 14mm 12mm 14mm;
            display: flex;
            flex-direction: column;
        }
        .company-address {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-bottom: 0.85rem;
            white-space: pre-line;
        }
        h1 {
            font-size: 1.55rem;
            margin: 0 0 0.7rem;
            letter-spacing: 0.02em;
        }
        .meta {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
            align-items: start;
        }
        .box {
            border: 1px solid var(--border-main);
            border-radius: 0.6rem;
            padding: 0.75rem 0.85rem;
            min-height: 4.25rem;
        }
        .box-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 0.45rem;
        }
        .box strong {
            display: block;
            font-size: 1.05rem;
            margin-bottom: 0.3rem;
        }
        .date-box {
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            font-size: 0.92rem;
            padding-top: 1.8rem;
        }
        .intro {
            border: 1px solid var(--border-main);
            border-radius: 0.6rem;
            padding: 0.8rem;
            margin-bottom: 0.9rem;
            white-space: pre-line;
            line-height: 1.35;
            font-size: 0.86rem;
        }
        .intro .type-note {
            display: block;
            margin-top: 0.8rem;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
            table-layout: fixed;
        }
        th,
        td {
            border-bottom: 1px solid var(--border-main);
            padding: 0.44rem 0.32rem;
            text-align: left;
            font-size: 0.79rem;
            vertical-align: top;
            overflow-wrap: anywhere;
        }
        th {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .signature-section {
            margin-top: 1.1rem;
            border: 1px solid var(--border-main);
            border-radius: 0.6rem;
            padding: 0.9rem 0.85rem 0.8rem;
            min-height: 82px;
            font-size: 0.82rem;
        }
        .signature-line {
            margin-top: 1.9rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 1rem;
        }
        .signature-line span:last-child {
            display: inline-block;
            width: 260px;
            border-bottom: 1px solid #1f2937;
            height: 1.4rem;
        }
        .closing-section {
            margin-top: auto;
            padding-top: 0.8rem;
        }
        .footer-note {
            text-align: center;
            font-size: 0.74rem;
            color: var(--text-muted);
            border: 1px solid var(--border-main);
            border-radius: 0.6rem;
            padding: 0.55rem 0.8rem;
            margin-top: 0.95rem;
            white-space: pre-line;
        }
        .empty-row {
            color: var(--text-muted);
            text-align: center;
            padding: 1.25rem 0.5rem;
        }
        @media print {
            body { background: #fff; font-size: 9.5pt; }
            .toolbar { display: none; }
            .page {
                width: auto;
                min-height: 269mm;
                margin: 0;
                box-shadow: none;
                padding: 0;
                display: flex;
                flex-direction: column;
            }
            .company-address {
                font-size: 7pt;
                margin-bottom: 0.4rem;
            }
            h1 {
                font-size: 15pt;
                margin-bottom: 0.35rem;
            }
            .meta {
                margin-bottom: 0.75rem;
                gap: 0.65rem;
            }
            .box {
                padding: 0.4rem 0.55rem;
                min-height: 2.8rem;
            }
            .box-label {
                font-size: 6.5pt;
                margin-bottom: 0.25rem;
            }
            .box strong {
                font-size: 0.88rem;
                margin-bottom: 0.15rem;
            }
            .date-box {
                padding-top: 0.6rem;
                font-size: 0.8rem;
            }
            .intro {
                padding: 0.45rem 0.6rem;
                margin-bottom: 0.45rem;
                font-size: 7.5pt;
                line-height: 1.25;
            }
            table thead {
                display: table-header-group;
            }
            th {
                font-size: 6.5pt;
                padding: 0.25rem 0.28rem;
            }
            td {
                font-size: 7.5pt;
                padding: 0.28rem 0.28rem;
            }
            table tr,
            table td,
            table th {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .closing-section {
                margin-top: auto;
                padding-top: 0.8rem;
            }
            .closing-section,
            .signature-section,
            .footer-note {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .signature-section {
                padding: 0.55rem 0.7rem;
                min-height: 60px;
                margin-top: 0.45rem;
            }
            .footer-note {
                font-size: 6.5pt;
                padding: 0.3rem 0.6rem;
                margin-top: 0.35rem;
            }
            @page {
                size: A4;
                margin: 14mm 12mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="<?php echo htmlspecialchars($returnTo); ?>"><?php echo htmlspecialchars($returnLabel); ?></a>
        <button type="button" onclick="window.print()">Drucken / Als PDF speichern</button>
    </div>

    <div class="page">
        <?php if ($companyAddress !== ''): ?>
            <div class="company-address"><?php echo nl2br(htmlspecialchars($companyAddress)); ?></div>
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($protocolTitle); ?></h1>

        <div class="meta">
            <div>
                <div class="box" style="margin-bottom: 0.9rem;">
                    <div class="box-label">Mitarbeiter/in</div>
                    <strong><?php echo htmlspecialchars($fullName); ?></strong>
                    <div><?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                </div>

                <div class="box">
                    <div class="box-label">Standort / Firma</div>
                    <?php if (!empty($locationLines)): ?>
                        <?php foreach ($locationLines as $line): ?>
                            <div><?php echo htmlspecialchars($line); ?></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div>Kein Standort hinterlegt</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="date-box">
                <div><strong>Datum:</strong> <?php echo htmlspecialchars($protocolDate); ?></div>
            </div>
        </div>

        <div class="intro">
            <?php echo nl2br(htmlspecialchars($headerText)); ?>
            <span class="type-note"><?php echo htmlspecialchars($typeExplanation); ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">Pos</th>
                    <th style="width: 38%;">Asset</th>
                    <th style="width: 24%;">Seriennummer</th>
                    <th style="width: 18%;">Inventar-Nr</th>
                    <th style="width: 12%;">Datum</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assets)): ?>
                    <tr>
                        <td colspan="5" class="empty-row">Diesem Benutzer sind aktuell keine Assets zugewiesen.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assets as $index => $asset): ?>
                        <?php $assetLabel = trim((string) ($asset['name'] ?: $asset['model_name'] ?: 'Unbekanntes Asset')); ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($assetLabel); ?></td>
                            <td><?php echo htmlspecialchars($asset['serial'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($asset['asset_tag'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars(isset($asset['event_date']) && $asset['event_date'] ? date('d.m.Y', strtotime($asset['event_date'])) : $protocolDate); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="closing-section">
            <div class="signature-section">
                <div>Ich bestaetige die <?php echo htmlspecialchars($protocolType === 'return' ? 'vollstaendige Rueckgabe' : 'ordnungsgemaesse Uebergabe'); ?> der oben aufgefuehrten IT-Hardware.</div>
                <div class="signature-line">
                    <span>Unterschrift:</span>
                    <span></span>
                </div>
            </div>

            <div class="footer-note"><?php echo nl2br(htmlspecialchars($footerText)); ?></div>
        </div>
    </div>
</body>
</html>
