<?php
// agent_bank_details.php
//
// Agent manages two separate lists of bank/payment accounts -- PKR
// and SAR. Whatever's here is exactly what the customer sees on
// booking_payment.php once they pick a currency to pay in.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_verify();

    if ($_POST['action'] === 'add') {
        $currency = $_POST['currency'] === 'SAR' ? 'SAR' : 'PKR';
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_title = trim($_POST['account_title'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $iban = trim($_POST['iban'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($bank_name !== '' && $account_title !== '' && $account_number !== '') {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM agent_bank_accounts WHERE currency = ?");
            $stmt->execute([$currency]);
            $next_sort = $stmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO agent_bank_accounts (currency, bank_name, account_title, account_number, iban, notes, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$currency, $bank_name, $account_title, $account_number, $iban ?: null, $notes ?: null, $next_sort]);
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM agent_bank_accounts WHERE id = ?")->execute([$id]);
    }

    header('Location: agent_bank_details.php?saved=1');
    exit();
}

$stmt = $pdo->query("SELECT * FROM agent_bank_accounts ORDER BY currency, sort_order, id");
$all_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pkr_accounts = array_values(array_filter($all_accounts, fn($a) => $a['currency'] === 'PKR'));
$sar_accounts = array_values(array_filter($all_accounts, fn($a) => $a['currency'] === 'SAR'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Details | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; }
        select { background-color: rgba(255,255,255,0.03); color: white; }
        select option { background-color: #10182c; color: white; }
        .container { max-width: 900px; margin: 0 auto; padding: 30px 24px 60px; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; margin: 14px 0 6px; }
        .sub { color: rgba(255,255,255,0.45); font-size: 13.5px; margin-bottom: 26px; }
        .flash { background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.2); color: #34d399; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; }

        .currency-tabs { display: flex; gap: 8px; margin-bottom: 24px; }
        .currency-tab { padding: 10px 22px; border-radius: 10px; font-size: 13.5px; font-weight: 700; cursor: pointer; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: rgba(255,255,255,0.5); }
        .currency-tab.active { background: #d4af37; color: #0a0f1e; border-color: #d4af37; }

        .currency-panel { display: none; }
        .currency-panel.active { display: block; }

        .account-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 14px; padding: 18px 20px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .account-info .bank { font-size: 14.5px; font-weight: 700; color: #d4af37; }
        .account-info .title { font-size: 13px; color: white; margin-top: 4px; }
        .account-info .num { font-size: 12.5px; color: rgba(255,255,255,0.5); margin-top: 2px; font-family: monospace; }
        .account-info .notes { font-size: 11.5px; color: rgba(255,255,255,0.35); margin-top: 4px; }
        .btn-delete-acc { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2); padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; flex-shrink: 0; }
        .btn-delete-acc:hover { background: #dc2626; color: white; }
        .empty-note { color: rgba(255,255,255,0.35); font-size: 13px; padding: 20px 0; text-align: center; }

        .add-form { background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); border-radius: 14px; padding: 22px; margin-top: 20px; }
        .add-form h3 { font-size: 13.5px; color: #d4af37; margin-bottom: 14px; }
        .row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .field { flex: 1; min-width: 160px; }
        .field label { display: block; font-size: 11.5px; color: rgba(255,255,255,0.5); margin-bottom: 5px; }
        .field input { width: 100%; padding: 10px 12px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; font-family: inherit; font-size: 13px; }
        .btn-add { background: #d4af37; color: #0a0f1e; padding: 11px 22px; border-radius: 9px; border: none; font-weight: 700; font-size: 13px; cursor: pointer; margin-top: 4px; }
        .btn-add:hover { background: #b8922e; }
    </style>
</head>
<body>
<div class="container">
    <a href="agent_dashboard.php" class="btn-back">← Back to Dashboard</a>
    <h1>Bank Details</h1>
    <div class="sub">Whatever you add here is exactly what the customer sees on the payment page once they pick PKR or SAR.</div>

    <?php if (isset($_GET['saved'])): ?><div class="flash"><i class="fas fa-circle-check"></i> Saved.</div><?php endif; ?>

    <div class="currency-tabs">
        <div class="currency-tab active" data-tab="PKR" onclick="switchTab('PKR')">🇵🇰 PKR Accounts (<?php echo count($pkr_accounts); ?>)</div>
        <div class="currency-tab" data-tab="SAR" onclick="switchTab('SAR')">🇸🇦 SAR Accounts (<?php echo count($sar_accounts); ?>)</div>
    </div>

    <?php foreach (['PKR' => $pkr_accounts, 'SAR' => $sar_accounts] as $cur => $accounts): ?>
    <div class="currency-panel <?php echo $cur === 'PKR' ? 'active' : ''; ?>" id="panel-<?php echo $cur; ?>">
        <?php if (empty($accounts)): ?>
            <div class="empty-note">No <?php echo $cur; ?> accounts yet — add one below.</div>
        <?php else: ?>
            <?php foreach ($accounts as $acc): ?>
            <div class="account-card">
                <div class="account-info">
                    <div class="bank"><?php echo htmlspecialchars($acc['bank_name']); ?></div>
                    <div class="title"><?php echo htmlspecialchars($acc['account_title']); ?></div>
                    <div class="num"><?php echo htmlspecialchars($acc['account_number']); ?><?php echo $acc['iban'] ? ' — IBAN: ' . htmlspecialchars($acc['iban']) : ''; ?></div>
                    <?php if ($acc['notes']): ?><div class="notes"><?php echo htmlspecialchars($acc['notes']); ?></div><?php endif; ?>
                </div>
                <form method="POST" onsubmit="return confirm('Remove this account? Customers will no longer see it as a payment option.');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$acc['id']; ?>">
                    <button type="submit" class="btn-delete-acc">Remove</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="add-form">
            <h3>Add a <?php echo $cur; ?> Account</h3>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="currency" value="<?php echo $cur; ?>">
                <div class="row">
                    <div class="field"><label>Bank / Provider Name</label><input type="text" name="bank_name" placeholder="e.g. HBL, Easypaisa, Al Rajhi Bank" required></div>
                    <div class="field"><label>Account Title (holder name)</label><input type="text" name="account_title" required></div>
                </div>
                <div class="row">
                    <div class="field"><label>Account Number</label><input type="text" name="account_number" required></div>
                    <div class="field"><label>IBAN <span style="color:rgba(255,255,255,0.3);">(optional)</span></label><input type="text" name="iban"></div>
                </div>
                <div class="field" style="margin-bottom:12px;"><label>Notes <span style="color:rgba(255,255,255,0.3);">(optional, e.g. "Branch: Gulshan-e-Iqbal")</span></label><input type="text" name="notes"></div>
                <button type="submit" class="btn-add">Add Account</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<script>
function switchTab(cur) {
    document.querySelectorAll('.currency-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === cur));
    document.querySelectorAll('.currency-panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + cur));
}
</script>
</body>
</html>