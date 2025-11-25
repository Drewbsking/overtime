<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$error = null;

if (is_post()) {
    validate_csrf();
    $workDateRaw = $_POST['work_date'] ?? '';
    $hoursRaw = $_POST['hours'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    $dateObj = DateTime::createFromFormat('Y-m-d', $workDateRaw);
    $workDate = $dateObj ? $dateObj->format('Y-m-d') : null;
    $hours = is_numeric($hoursRaw) ? (float)$hoursRaw : -1;

    if (!$workDate) {
        $error = 'Enter a valid date.';
    } elseif ($hours <= 0 || $hours > 24) {
        $error = 'Hours must be between 0 and 24.';
    } elseif (strlen($reason) < 3) {
        $error = 'Reason is required.';
    } else {
        try {
            $requestId = Overtime::create(Auth::user()['id'], $workDate, $hours, $reason);

            $recipients = [];
            if ($userEmail = Auth::user()['email'] ?? null) {
                $recipients[$userEmail] = Auth::user()['username'];
            }
            foreach (['SMTP_APPROVER_1', 'SMTP_APPROVER_2'] as $envKey) {
                $email = env($envKey);
                if ($email) {
                    $recipients[$email] = 'Approver';
                }
            }

            $subject = 'New Overtime Request #' . $requestId;
            $html = sprintf(
                '<p>A new overtime request was submitted.</p><ul><li>Date: %s</li><li>Hours: %s</li><li>Reason: %s</li><li>Requestor: %s</li></ul>',
                h($workDate),
                h($hours),
                nl2br(h($reason)),
                h(Auth::user()['username'])
            );
            Mailer::send($recipients, $subject, $html);

            flash('success', 'Request submitted.');
            redirect('/requests.php');
        } catch (Throwable $e) {
            $error = 'Could not submit request.';
        }
    }
}

$pageTitle = 'Submit Overtime Request';
include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Submit Overtime Request</h1>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="work_date">Work Date</label>
                        <input class="form-control" type="date" name="work_date" id="work_date" required value="<?php echo h($_POST['work_date'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="hours">Hours</label>
                        <input class="form-control" type="number" name="hours" id="hours" min="0.25" max="24" step="0.25" required value="<?php echo h($_POST['hours'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reason">Reason</label>
                        <textarea class="form-control" name="reason" id="reason" rows="4" required><?php echo h($_POST['reason'] ?? ''); ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Submit</button>
                    <a class="btn btn-link" href="/requests.php">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
