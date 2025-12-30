<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$error = null;

$workDateInputs = $_POST['work_date'] ?? [''];
$hoursInputs = $_POST['hours'] ?? [''];
if (!is_array($workDateInputs)) {
    $workDateInputs = [$workDateInputs];
}
if (!is_array($hoursInputs)) {
    $hoursInputs = [$hoursInputs];
}
$rowCount = max(count($workDateInputs), count($hoursInputs), 1);
$workDateInputs = array_values(array_pad($workDateInputs, $rowCount, ''));
$hoursInputs = array_values(array_pad($hoursInputs, $rowCount, ''));

if (is_post()) {
    validate_csrf();
    $reason = trim($_POST['reason'] ?? '');
    $workTypeRaw = $_POST['work_type'] ?? 'office';
    $workType = in_array($workTypeRaw, ['office', 'field'], true) ? $workTypeRaw : null;
    $rows = [];
    foreach (range(0, $rowCount - 1) as $idx) {
        $workDateRaw = $workDateInputs[$idx] ?? '';
        $hoursRaw = $hoursInputs[$idx] ?? '';

        if (trim((string)$workDateRaw) === '' && trim((string)$hoursRaw) === '') {
            continue;
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', (string)$workDateRaw);
        $workDate = $dateObj ? $dateObj->format('Y-m-d') : null;
        $hours = is_numeric($hoursRaw) ? (float)$hoursRaw : -1;

        if (!$workDate) {
            $error = 'Enter a valid date for each row.';
            break;
        }
        if ($hours <= 0 || $hours > 24) {
            $error = 'Hours must be between 0 and 24 for each row.';
            break;
        }

        $rows[] = [
            'work_date' => $workDate,
            'hours' => $hours,
        ];
    }

    if (!$error) {
        if (empty($rows)) {
            $error = 'Add at least one date and hours.';
        } elseif (count($rows) > 10) {
            $error = 'Please submit no more than 10 dates at a time.';
        } elseif (strlen($reason) < 3) {
            $error = 'Reason is required.';
        } elseif (strlen($reason) > 1000) {
            $error = 'Reason is too long.';
        } elseif (!$workType) {
            $error = 'Select whether the overtime is for Office or Field work.';
        }
    }

    if (!$error) {
        try {
            $requestIds = [];
            foreach ($rows as $row) {
                $requestIds[] = Overtime::create(Auth::user()['id'], $row['work_date'], $row['hours'], $reason, $workType);
            }

            $recipients = Overtime::notificationRecipients();
            if ($userEmail = Auth::user()['email'] ?? null) {
                $recipients[$userEmail] = Auth::user()['full_name'] ?? Auth::user()['username'];
            }

            $listItems = array_map(function ($id, $row) {
                return sprintf(
                    '<li>Request #%d — Date: %s, Hours: %s</li>',
                    $id,
                    h($row['work_date']),
                    h($row['hours'])
                );
            }, $requestIds, $rows);

            $subject = 'New Overtime Requests #' . implode(', ', $requestIds);
            $html = sprintf(
                '<p>New overtime requests were submitted.</p><ul>%s</ul><p>Work Type: %s<br>Reason: %s<br>Requestor: %s</p><p><a href="%s">Review pending requests</a></p>',
                implode('', $listItems),
                h(ucfirst($workType)),
                nl2br(h($reason)),
                h(Auth::user()['full_name'] ?? Auth::user()['username']),
                h(app_url('review.php'))
            );
            Mailer::send($recipients, $subject, $html);

            flash('success', 'Submitted ' . count($requestIds) . ' request(s).');
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
                        <label class="form-label">Work Dates and Hours</label>
                        <div id="date-rows">
                            <?php foreach (range(0, $rowCount - 1) as $idx): ?>
                                <div class="row g-2 align-items-end mb-2 date-row">
                                    <div class="col-md-6">
                                        <input class="form-control" type="date" name="work_date[]" value="<?php echo h($workDateInputs[$idx] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <input class="form-control" type="number" name="hours[]" min="0.25" max="24" step="0.25" value="<?php echo h($hoursInputs[$idx] ?? ''); ?>" placeholder="Hours">
                                    </div>
                                    <div class="col-md-2 text-md-end">
                                        <button class="btn btn-outline-danger btn-sm remove-row" type="button"<?php echo $idx === 0 ? ' disabled' : ''; ?>>Remove</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="add-row">Add another date</button>
                        <div class="form-text">Submit up to 10 dates at once. Leave blank rows empty or remove them.</div>
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
<script>
(function() {
    const maxRows = 10;
    const rowsContainer = document.getElementById('date-rows');
    const addRowBtn = document.getElementById('add-row');

    function updateRemoveButtons() {
        const buttons = rowsContainer.querySelectorAll('.remove-row');
        buttons.forEach((btn, idx) => {
            btn.disabled = idx === 0;
        });
    }

    function addRow(dateValue = '', hoursValue = '') {
        if (rowsContainer.children.length >= maxRows) {
            alert('You can add up to 10 dates.');
            return;
        }
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end mb-2 date-row';
        row.innerHTML = `
            <div class="col-md-6">
                <input class="form-control" type="date" name="work_date[]" value="${dateValue}">
            </div>
            <div class="col-md-4">
                <input class="form-control" type="number" name="hours[]" min="0.25" max="24" step="0.25" value="${hoursValue}" placeholder="Hours">
            </div>
            <div class="col-md-2 text-md-end">
                <button class="btn btn-outline-danger btn-sm remove-row" type="button">Remove</button>
            </div>
        `;
        rowsContainer.appendChild(row);
        updateRemoveButtons();
    }

    addRowBtn.addEventListener('click', function() {
        addRow();
    });

    rowsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-row')) {
            const row = e.target.closest('.date-row');
            if (row && rowsContainer.children.length > 1) {
                row.remove();
                updateRemoveButtons();
            }
        }
    });

    updateRemoveButtons();
})();
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>
