<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$error = null;

if (is_post()) {
    validate_csrf();
    $workDateRaw = $_POST['work_date'] ?? '';
    $hoursRaw = $_POST['hours'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $workTypeRaw = $_POST['work_type'] ?? 'office';
    $workType = in_array($workTypeRaw, ['office', 'field'], true) ? $workTypeRaw : null;

    $dateObj = DateTime::createFromFormat('Y-m-d', $workDateRaw);
    $workDate = $dateObj ? $dateObj->format('Y-m-d') : null;
    $hours = is_numeric($hoursRaw) ? (float)$hoursRaw : -1;

    if (!$workDate) {
        $error = 'Enter a valid date.';
    } elseif ($hours <= 0 || $hours > 24) {
        $error = 'Hours must be between 0 and 24.';
    } elseif (strlen($reason) < 3) {
        $error = 'Reason is required.';
    } elseif (!$workType) {
        $error = 'Select whether the overtime is for Office or Field work.';
    } else {
        try {
            $requestId = Overtime::create(Auth::user()['id'], $workDate, $hours, $reason, $workType);

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
                '<p>A new overtime request was submitted.</p><ul><li>Date: %s</li><li>Hours: %s</li><li>Work Type: %s</li><li>Reason: %s</li><li>Requestor: %s</li></ul><p><a href="%s">Review pending requests</a></p>',
                h($workDate),
                h($hours),
                h(ucfirst($workType)),
                nl2br(h($reason)),
                h(Auth::user()['username']),
                h(app_url('review.php'))
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
                <div class="alert alert-info small">
                    <p class="mb-1">Overtime in RMS will not be approved without you filling out this form.</p>
                    <p class="mb-0"><strong>Overtime Rules:</strong> Must be preapproved and must be justified.</p>
                </div>
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
                        <label class="form-label" for="work_type">Worked For</label>
                        <?php $selectedWorkType = $_POST['work_type'] ?? 'office'; ?>
                        <select class="form-select" name="work_type" id="work_type" required>
                            <option value="office" <?php echo ($selectedWorkType === 'office') ? 'selected' : ''; ?>>Office</option>
                            <option value="field" <?php echo ($selectedWorkType === 'field') ? 'selected' : ''; ?>>Field Work</option>
                        </select>
                        <div class="form-text">Pick which group this overtime supports.</div>
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
