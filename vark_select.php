<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/helpers.php";

require_login();

$uid = (int)$_SESSION["user_id"];

$stmt = $conn->prepare("SELECT vark_style FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if ($u && $u["vark_style"] !== null) {
  header("Location: home.php");
  exit;
}

$err = "";
$step = $_GET["step"] ?? "questions";

if ($step === "questions") {
  
  $questions = [
    // V (Visual) - 4 questions
    ["text" => "I prefer to see diagrams, charts, and graphs when learning new information.", "type" => "V", "icon" => "📊"],
    ["text" => "I find it easier to remember information by visualizing it in my mind.", "type" => "V", "icon" => "🧠"],
    ["text" => "I like using color-coded notes and mind maps.", "type" => "V", "icon" => "🎨"],
    ["text" => "Watching a video demonstration helps me understand better than reading text.", "type" => "V", "icon" => "📹"],
    
    // A (Auditory) - 4 questions
    ["text" => "I prefer listening to lectures or podcasts rather than reading.", "type" => "A", "icon" => "🎧"],
    ["text" => "I like discussing topics with others to understand them better.", "type" => "A", "icon" => "💬"],
    ["text" => "I remember information by saying it out loud.", "type" => "A", "icon" => "🗣️"],
    ["text" => "I prefer verbal instructions over written ones.", "type" => "A", "icon" => "📢"],
    
    // R (Reading/Writing) - 3 questions
    ["text" => "I prefer reading textbooks and written notes to learn.", "type" => "R", "icon" => "📚"],
    ["text" => "I like taking detailed notes during lectures.", "type" => "R", "icon" => "✍️"],
    ["text" => "I find lists, bullet points, and written summaries helpful.", "type" => "R", "icon" => "📝"],
    
    // K (Kinesthetic) - 4 questions
    ["text" => "I prefer hands-on activities and lab work.", "type" => "K", "icon" => "🔬"],
    ["text" => "I learn better when I can move around while studying.", "type" => "K", "icon" => "🏃"],
    ["text" => "I like building models or practicing physical tasks.", "type" => "K", "icon" => "🛠️"],
    ["text" => "I remember things better when I actually do the activity myself.", "type" => "K", "icon" => "✨"]
  ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH - VARK Learning Style Assessment</title>
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    .vark-header {
      text-align: center;
      margin-bottom: 28px;
    }
    .vark-header h1 {
      font-size: 32px;
      background: linear-gradient(135deg, var(--brand-cyan), var(--brand-purple));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-bottom: 8px;
    }
    .vark-badges {
      display: flex;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 16px;
    }
    .vark-badge {
      padding: 8px 16px;
      border-radius: 40px;
      font-weight: 600;
      font-size: 14px;
    }
    .vark-badge.V { background: rgba(110,231,255,0.15); border: 1px solid rgba(110,231,255,0.4); color: #6ee7ff; }
    .vark-badge.A { background: rgba(52,211,153,0.15); border: 1px solid rgba(52,211,153,0.4); color: #34d399; }
    .vark-badge.R { background: rgba(251,191,36,0.15); border: 1px solid rgba(251,191,36,0.4); color: #fbbf24; }
    .vark-badge.K { background: rgba(167,139,250,0.15); border: 1px solid rgba(167,139,250,0.4); color: #a78bfa; }
    
    .progress-container {
      background: rgba(255,255,255,0.08);
      border-radius: 60px;
      height: 10px;
      margin: 24px 0 20px;
      overflow: hidden;
    }
    .progress-fill {
      background: linear-gradient(90deg, var(--brand-cyan), var(--brand-purple));
      border-radius: 60px;
      height: 10px;
      width: 0%;
      transition: width 0.4s ease;
    }
    .stats {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .answered-count {
      font-size: 14px;
      color: var(--text-muted);
    }
    .answered-count span {
      color: var(--brand-cyan);
      font-weight: 800;
      font-size: 18px;
    }
    .question-grid {
      display: flex;
      flex-direction: column;
      gap: 16px;
      max-height: 550px;
      overflow-y: auto;
      padding-right: 8px;
    }
    .question-grid::-webkit-scrollbar {
      width: 6px;
    }
    .question-grid::-webkit-scrollbar-track {
      background: rgba(255,255,255,0.05);
      border-radius: 10px;
    }
    .question-grid::-webkit-scrollbar-thumb {
      background: var(--brand-cyan);
      border-radius: 10px;
    }
    .question-item {
      background: rgba(17,31,56,0.6);
      backdrop-filter: blur(4px);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 20px;
      padding: 16px 20px;
      transition: all 0.25s ease;
    }
    .question-item:hover {
      border-color: rgba(110,231,255,0.3);
      background: rgba(17,31,56,0.8);
    }
    .question-text {
      font-weight: 600;
      font-size: 15px;
      line-height: 1.4;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .question-icon {
      font-size: 24px;
    }
    .vark-tag {
      font-size: 10px;
      padding: 2px 8px;
      border-radius: 20px;
      background: rgba(255,255,255,0.08);
    }
    .option-buttons {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    .option {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      padding: 6px 16px;
      border-radius: 40px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      transition: all 0.2s;
      font-weight: 500;
      font-size: 14px;
    }
    .option:hover {
      background: rgba(110,231,255,0.15);
      border-color: rgba(110,231,255,0.4);
    }
    .option input {
      accent-color: var(--brand-cyan);
      width: 16px;
      height: 16px;
      margin: 0;
      cursor: pointer;
    }
    .option.selected-yes { background: rgba(52,211,153,0.2); border-color: #34d399; }
    .option.selected-sometimes { background: rgba(251,191,36,0.2); border-color: #fbbf24; }
    .option.selected-no { background: rgba(251,113,133,0.15); border-color: #fb7185; }
    
    .btn-submit {
      width: 100%;
      padding: 14px;
      font-size: 16px;
      font-weight: 700;
      margin-top: 24px;
    }
    .reset-btn {
      background: rgba(255,255,255,0.05);
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-in {
      animation: fadeInUp 0.4s ease forwards;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="vark-header" style="padding: 28px 28px 0 28px;">
        <h1>🎓 Discover Your Learning Style</h1>
        <p class="p" style="max-width: 500px; margin: 8px auto 0;">
          Answer 15 quick questions to find out your VARK learning style.
        </p>
        <div class="vark-badges">
          <span class="vark-badge V">📊 Visual</span>
          <span class="vark-badge A">🎧 Auditory</span>
          <span class="vark-badge R">📚 Reading/Writing</span>
          <span class="vark-badge K">🔬 Kinesthetic</span>
        </div>
      </div>

      <div class="bd" style="padding: 20px 28px 32px;">
        <div class="progress-container">
          <div class="progress-fill" id="progressFill"></div>
        </div>
        
        <div class="stats">
          <div class="answered-count">
            📝 Answered: <span id="answeredCount">0</span> / 15
          </div>
          <button type="button" class="btn reset-btn" id="resetAllBtn" style="padding: 6px 14px;">
            🔄 Reset All
          </button>
        </div>

        <form method="POST" action="vark_select.php?step=calculate" id="varkForm">
          <div class="question-grid">
            <?php foreach ($questions as $idx => $q): ?>
              <div class="question-item animate-in" style="animation-delay: <?= $idx * 0.02 ?>s;">
                <div class="question-text">
                  <span class="question-icon"><?= htmlspecialchars($q["icon"]) ?></span>
                  <span><?= ($idx+1) ?>. <?= htmlspecialchars($q["text"]) ?></span>
                  <span class="vark-tag"><?= $q["type"] ?></span>
                </div>
                <div class="option-buttons" data-qidx="<?= $idx ?>">
                  <label class="option" data-opt="yes">
                    <input type="radio" name="q<?= $idx ?>" value="2"> ✅ Yes
                  </label>
                  <label class="option" data-opt="sometimes">
                    <input type="radio" name="q<?= $idx ?>" value="1"> 🔄 Sometimes
                  </label>
                  <label class="option" data-opt="no">
                    <input type="radio" name="q<?= $idx ?>" value="0"> ❌ No
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <button type="submit" class="btn btn-primary btn-submit" id="submitBtn">
            ✨ Analyze My Results ✨
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
    const totalQuestions = 15;
    
    function updateProgress() {
      let answered = 0;
      for (let i = 0; i < totalQuestions; i++) {
        const radios = document.querySelectorAll(`input[name="q${i}"]`);
        let isChecked = false;
        radios.forEach(r => { if (r.checked) isChecked = true; });
        if (isChecked) answered++;
      }
      const percent = (answered / totalQuestions) * 100;
      document.getElementById('progressFill').style.width = percent + '%';
      document.getElementById('answeredCount').innerText = answered;
      
      // Update button styles
      for (let i = 0; i < totalQuestions; i++) {
        const radios = document.querySelectorAll(`input[name="q${i}"]`);
        const options = document.querySelectorAll(`.option-buttons[data-qidx="${i}"] .option`);
        let selectedValue = null;
        radios.forEach(r => { if (r.checked) selectedValue = r.value; });
        
        options.forEach(opt => {
          opt.classList.remove('selected-yes', 'selected-sometimes', 'selected-no');
          const input = opt.querySelector('input');
          if (input && input.value === selectedValue) {
            if (selectedValue === '2') opt.classList.add('selected-yes');
            else if (selectedValue === '1') opt.classList.add('selected-sometimes');
            else if (selectedValue === '0') opt.classList.add('selected-no');
          }
        });
      }
    }
    
    function resetAllAnswers() {
      if (confirm('Reset all your answers? This cannot be undone.')) {
        for (let i = 0; i < totalQuestions; i++) {
          const radios = document.querySelectorAll(`input[name="q${i}"]`);
          radios.forEach(r => r.checked = false);
        }
        updateProgress();
      }
    }
    
    for (let i = 0; i < totalQuestions; i++) {
      const radios = document.querySelectorAll(`input[name="q${i}"]`);
      radios.forEach(r => {
        r.addEventListener('change', updateProgress);
      });
    }
    
    document.getElementById('resetAllBtn').addEventListener('click', resetAllAnswers);
    updateProgress();
    
    document.getElementById('varkForm').addEventListener('submit', function(e) {
      for (let i = 0; i < totalQuestions; i++) {
        const radios = document.querySelectorAll(`input[name="q${i}"]`);
        let checked = false;
        radios.forEach(r => { if (r.checked) checked = true; });
        if (!checked) {
          e.preventDefault();
          alert(`⚠️ Please answer question ${i+1} before submitting.`);
          document.querySelector(`.question-item`).scrollIntoView({ behavior: 'smooth', block: 'center' });
          return;
        }
      }
    });
  </script>
</body>
</html>

<?php
} 

elseif ($step === "calculate" && $_SERVER["REQUEST_METHOD"] === "POST") {
  
  $scores = ["V" => 0, "A" => 0, "R" => 0, "K" => 0];
  
  $questionTypes = [
    0 => "V", 1 => "V", 2 => "V", 3 => "V",
    4 => "A", 5 => "A", 6 => "A", 7 => "A",
    8 => "R", 9 => "R", 10 => "R",
    11 => "K", 12 => "K", 13 => "K", 14 => "K"
  ];
  
  $allAnswered = true;
  
  for ($i = 0; $i < 15; $i++) {
    $answer = $_POST["q$i"] ?? null;
    if ($answer === null) {
      $allAnswered = false;
      break;
    }
    $type = $questionTypes[$i];
    $scores[$type] += (int)$answer;
  }
  
  if (!$allAnswered) {
    header("Location: vark_select.php?step=questions&error=1");
    exit;
  }
  
  // Find the highest score
  $maxScore = max($scores);
  $dominantStyles = [];
  foreach ($scores as $style => $score) {
    if ($score == $maxScore) {
      $dominantStyles[] = $style;
    }
  }
  
  $priorityOrder = ["V", "A", "R", "K"];
  $selectedStyle = "V";
  foreach ($priorityOrder as $style) {
    if (in_array($style, $dominantStyles)) {
      $selectedStyle = $style;
      break;
    }
  }
  
  // Save to database
  $stmt = $conn->prepare("UPDATE users SET vark_style = ? WHERE id = ?");
  $stmt->bind_param("si", $selectedStyle, $uid);
  
  if ($stmt->execute()) {
    $_SESSION["vark_scores"] = $scores;
    
    header("Location: home.php?vark_set=1&style=" . $selectedStyle);
    exit;
  } else {
    $err = "Failed to save your learning style. Please try again.";
    ?>
    <!DOCTYPE html>
    <html>
    <head>
      <title>CSH - Error</title>
      <link rel="stylesheet" href="assets/css/theme.css">
    </head>
    <body>
      <div class="container">
        <div class="card">
          <div class="hd">
            <h1 class="h1">⚠️ Oops!</h1>
          </div>
          <div class="bd">
            <div class="notice bad"><?= htmlspecialchars($err) ?></div>
            <a class="btn btn-primary" href="vark_select.php?step=questions">Try Again</a>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
  }
}
?>