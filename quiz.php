<?php
// quiz.php – 5-question security awareness quiz (one page at a time)

session_start();
date_default_timezone_set('Asia/Bahrain');

require __DIR__ . '/db.php'; // $pdo

/* Token */
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = trim($token);

if ($token === '') {
    http_response_code(400);
    echo "Invalid or missing token.";
    exit;
}

/* Look up target */
$stmt = $pdo->prepare("
    SELECT 
        ct.target_id,
        u.name  AS user_name,
        u.email AS user_email,
        c.name  AS campaign_name
    FROM campaign_targets ct
    JOIN users u     ON ct.user_id = u.user_id
    JOIN campaigns c ON ct.campaign_id = c.campaign_id
    WHERE ct.unique_link_token = :token
    LIMIT 1
");
$stmt->execute([':token' => $token]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    http_response_code(404);
    echo "This quiz link is invalid or has expired.";
    exit;
}

$targetId     = (int)$target['target_id'];
$userName     = (string)($target['user_name'] ?? '');
$campaignName = (string)($target['campaign_name'] ?? '');

/* Questions */
$questions = [
    'q1' => [
        'text'    => 'You receive an email saying your password will expire in 30 minutes and you must click a link to keep your account active. What is the safest first step?',
        'options' => [
            'a' => 'Click the link immediately and enter your password',
            'b' => 'Forward the email to all colleagues to warn them',
            'c' => 'Verify the request through the official IT portal or helpdesk',
            'd' => 'Reply to the email and send your current password',
        ],
        'correct' => 'c',
    ],
    'q2' => [
        'text'    => 'Which of these is the BEST sign that an email might be phishing?',
        'options' => [
            'a' => 'The email uses your name correctly',
            'b' => 'The sender’s address is slightly misspelled compared to the real company',
            'c' => 'The email includes the company logo',
            'd' => 'The email is sent during normal working hours',
        ],
        'correct' => 'b',
    ],
    'q3' => [
        'text'    => 'A link in an email looks like “https://portal.company.com”, but when you hover over it, the status bar shows a completely different website. What should you do?',
        'options' => [
            'a' => 'Click it only if you are at home',
            'b' => 'Click it but avoid typing your password',
            'c' => 'Do not click the link and report the email to IT/security',
            'd' => 'Reply and ask the sender if it is safe',
        ],
        'correct' => 'c',
    ],
    'q4' => [
        'text'    => 'You already clicked a suspicious link and entered your password. What is the BEST next action?',
        'options' => [
            'a' => 'Do nothing if nothing strange happened',
            'b' => 'Immediately change your password and inform IT/security',
            'c' => 'Close the browser and hope it is fine',
            'd' => 'Delete your browser history only',
        ],
        'correct' => 'b',
    ],
    'q5' => [
        'text'    => 'Which of these behaviours helps keep the organization safer from phishing?',
        'options' => [
            'a' => 'Ignoring suspicious emails and never telling anyone',
            'b' => 'Using the same password everywhere so it is easy to remember',
            'c' => 'Reporting suspicious emails using the official reporting method',
            'd' => 'Clicking all links quickly to keep inbox clean',
        ],
        'correct' => 'c',
    ],
];

/* Submission */
$submitted     = false;
$score         = 0;
$maxScore      = count($questions);
$passed        = 0;
$resultMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_submitted'])) {
    $submitted = true;
    $score     = 0;

    foreach ($questions as $key => $q) {
        $userAnswer = $_POST[$key] ?? '';
        if ($userAnswer === $q['correct']) {
            $score++;
        }
    }

    $passScore = 3;                  // passing rule
    $passed    = ($score >= $passScore) ? 1 : 0;

    // Save attempt
    $insert = $pdo->prepare("
        INSERT INTO quiz_attempts (target_id, score, passed, created_at)
        VALUES (:tid, :score, :passed, datetime('now','localtime'))
    ");
    $insert->execute([
        ':tid'    => $targetId,
        ':score'  => $score,
        ':passed' => $passed,
    ]);

    $resultMessage = $passed
        ? 'Nice! You passed ✅'
        : 'Not this time ❌ Try again after reviewing the reminders below.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Awareness Quiz</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/quiz.css">
</head>
<body class="quiz-body">

<main class="quiz-wrap">

    <?php if ($submitted): ?>
        <!-- Results -->
        <section class="quiz-card quiz-result" role="main">
            <div class="quiz-top">
                <div class="quiz-pill">Security Quiz</div>
            </div>

            <div class="quiz-result-hero">
                <div class="quiz-hero-left">
                    <h1 class="quiz-title">Results</h1>
                    <p class="quiz-subtitle">
                        Employee: <strong><?= htmlspecialchars($userName, ENT_QUOTES) ?></strong>
                        <span class="dot-sep">•</span>
                        Campaign: <strong><?= htmlspecialchars($campaignName, ENT_QUOTES) ?></strong>
                    </p>
                </div>

                <div class="quiz-score-ring">
                    <div class="ring-num"><?= (int)$score ?></div>
                    <div class="ring-max">/ <?= (int)$maxScore ?></div>
                </div>
            </div>

            <div class="quiz-result-badge <?= $passed ? 'badge-pass' : 'badge-fail' ?>">
                <?= $passed ? '🟢 Passed' : '🔴 Not Passed' ?>
            </div>

            <p class="quiz-result-msg <?= $passed ? 'msg-pass' : 'msg-fail' ?>">
                <?= htmlspecialchars($resultMessage, ENT_QUOTES) ?>
            </p>

            <div class="quiz-reminders">
                <div class="rem-title">Quick reminders</div>
                <ul>
                    <li>Check the sender email carefully.</li>
                    <li>Hover links to preview the real destination.</li>
                    <li>Never type passwords after clicking unknown links.</li>
                    <li>If unsure, report it to IT/security.</li>
                </ul>
            </div>

            <div class="quiz-actions">
                <a class="btn btn-primary" href="https://www.google.com/" target="_blank" rel="noopener">
                    Close
                </a>
            </div>
        </section>

    <?php else: ?>
        <!-- Quiz -->
        <section class="quiz-card" role="main">
            <div class="quiz-top">
                <div class="quiz-pill">Security Quiz</div>
            </div>

            <div class="quiz-hero">
                <div class="quiz-hero-left">
                    <h1 class="quiz-title">Security Awareness Quiz</h1>
                    <p class="quiz-subtitle">
                        5 quick questions. No stress — just practice.
                    </p>
                </div>

                <div class="quiz-hero-right">
                    <div class="quiz-meta">
                        <div class="meta-row">
                            <span class="meta-label">Employee</span>
                            <span class="meta-value"><?= htmlspecialchars($userName, ENT_QUOTES) ?></span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Campaign</span>
                            <span class="meta-value"><?= htmlspecialchars($campaignName, ENT_QUOTES) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" id="quizForm" class="quiz-form">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                <input type="hidden" name="quiz_submitted" value="1">

                <?php
                $index = 0;
                $total = count($questions);
                foreach ($questions as $key => $q):
                    $index++;
                ?>
                    <div class="quiz-question" data-index="<?= $index ?>" style="<?= $index === 1 ? '' : 'display:none;' ?>">
                        <div class="quiz-progress-row">
                            <div class="quiz-progress">
                                Question <?= $index ?> of <?= $total ?>
                            </div>
                            <div class="quiz-progress-bar-container" aria-hidden="true">
                                <div class="quiz-progress-bar"></div>
                            </div>
                        </div>

                        <div class="quiz-question-text">
                            <?= htmlspecialchars($q['text'], ENT_QUOTES) ?>
                        </div>

                        <ul class="quiz-options">
                            <?php foreach ($q['options'] as $optKey => $optText): ?>
                                <li>
                                    <label class="opt">
                                        <input type="radio" name="<?= htmlspecialchars($key, ENT_QUOTES) ?>" value="<?= htmlspecialchars($optKey, ENT_QUOTES) ?>">
                                        <span class="opt-bubble" aria-hidden="true"></span>
                                        <span class="opt-text"><?= htmlspecialchars($optText, ENT_QUOTES) ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="quiz-error">Please select an answer before continuing.</div>
                    </div>
                <?php endforeach; ?>

                <div class="quiz-nav">
                    <button type="button" class="btn btn-secondary" id="btnPrev" disabled>Back</button>
                    <button type="button" class="btn btn-primary" id="btnNext">Next</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmit" style="display:none;">Submit</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

</main>

<footer class="quiz-footer">
    &copy; 2025 EnginGuard. All Rights Reserved.
</footer>

<script>
(function () {
    const questionEls = document.querySelectorAll('.quiz-question');
    if (!questionEls.length) return;

    let currentIndex = 0;
    const total = questionEls.length;

    const btnPrev   = document.getElementById('btnPrev');
    const btnNext   = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmit');

    function setProgress(index){
        const q = questionEls[index];
        const bar = q.querySelector('.quiz-progress-bar');
        if (!bar) return;
        const pct = ((index + 1) / total) * 100;
        bar.style.width = pct + '%';
    }

    function showQuestion(index) {
        questionEls.forEach((q, i) => q.style.display = (i === index) ? '' : 'none');

        btnPrev.disabled = (index === 0);

        if (index === total - 1) {
            btnNext.style.display   = 'none';
            btnSubmit.style.display = '';
        } else {
            btnNext.style.display   = '';
            btnSubmit.style.display = 'none';
        }

        const err = questionEls[index].querySelector('.quiz-error');
        if (err) err.style.display = 'none';

        setProgress(index);
    }

    function hasAnswer(index) {
        const radios = questionEls[index].querySelectorAll('input[type="radio"]');
        for (const r of radios) {
            if (r.checked) return true;
        }
        return false;
    }

    btnNext.addEventListener('click', function () {
        if (!hasAnswer(currentIndex)) {
            const err = questionEls[currentIndex].querySelector('.quiz-error');
            if (err) err.style.display = 'block';
            return;
        }
        if (currentIndex < total - 1) {
            currentIndex++;
            showQuestion(currentIndex);
        }
    });

    btnPrev.addEventListener('click', function () {
        if (currentIndex > 0) {
            currentIndex--;
            showQuestion(currentIndex);
        }
    });

    const form = document.getElementById('quizForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!hasAnswer(currentIndex)) {
                e.preventDefault();
                const err = questionEls[currentIndex].querySelector('.quiz-error');
                if (err) err.style.display = 'block';
            }
        });
    }

    showQuestion(currentIndex);
})();
</script>

</body>
</html>
