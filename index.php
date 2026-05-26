<?php
require_once __DIR__ . "/includes/db.php";
if (session_status() === PHP_SESSION_NONE) session_start();

// If already logged in, redirect to home
if (!empty($_SESSION['user_id'])) {
  $uid = (int)$_SESSION['user_id'];
  $stmt = $conn->prepare("SELECT vark_style FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();
  
  if ($u && $u["vark_style"] === null) {
    header("Location: vark_select.php");
  } else {
    header("Location: home.php");
  }
  exit;
}

// Handle login form submission
$login_error = false;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login_action'])) {
  $email = trim($_POST["email"] ?? "");
  $pass = $_POST["password"] ?? "";
  
  if ($email !== "" && $pass !== "") {
    $stmt = $conn->prepare("SELECT id, password_hash, vark_style FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user && password_verify($pass, $user["password_hash"])) {
      $_SESSION["user_id"] = (int)$user["id"];
      if ($user["vark_style"] === null) {
        header("Location: vark_select.php");
      } else {
        header("Location: home.php");
      }
      exit;
    }
  }
  $login_error = true;
}

// Get platform stats from database
$stats = [
  'users' => 0,
  'groups' => 0
];

$result = $conn->query("SELECT COUNT(*) AS count FROM users");
if ($result) $stats['users'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) AS count FROM `groups`");
if ($result) $stats['groups'] = $result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH | Collaborative Study Hub</title>
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    .hero {
      text-align: center;
      padding: 60px 20px 40px;
      position: relative;
    }
    
    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 80%;
      height: 100%;
      background: radial-gradient(ellipse at 50% 0%, rgba(110,231,255,0.15), transparent 70%);
      pointer-events: none;
    }
    
    .hero-title {
      font-size: clamp(36px, 6vw, 64px);
      font-weight: 800;
      background: linear-gradient(135deg, #fff, #6ee7ff, #a78bfa, #34d399);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-bottom: 20px;
      animation: titleGlow 3s ease-in-out infinite;
    }
    
    @keyframes titleGlow {
      0%, 100% { filter: drop-shadow(0 0 10px rgba(110,231,255,0.3)); }
      50% { filter: drop-shadow(0 0 20px rgba(110,231,255,0.5)); }
    }
    
    .hero-subtitle {
      font-size: clamp(16px, 4vw, 20px);
      color: rgba(255,255,255,0.7);
      max-width: 600px;
      margin: 0 auto 32px;
    }
    
    .hero-buttons {
      display: flex;
      gap: 18px;
      justify-content: center;
      flex-wrap: wrap;
    }
    
    .btn-large {
      padding: 14px 34px;
      font-size: 18px;
      font-weight: 700;
      border-radius: 50px;
    }
    
    .stats-hologram {
      margin: 40px 0 60px;
    }
    
    .hologram-container {
      display: flex;
      justify-content: center;
      gap: 40px;
      flex-wrap: wrap;
    }
    
    .hologram-card {
      position: relative;
      width: 280px;
      padding: 32px 24px;
      background: rgba(10, 20, 40, 0.4);
      backdrop-filter: blur(12px);
      border-radius: 32px;
      overflow: hidden;
      transition: all 0.4s ease;
    }
    
    .hologram-border {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      border-radius: 32px;
      padding: 2px;
      background: linear-gradient(135deg, rgba(110,231,255,0.6), rgba(167,139,250,0.4), rgba(52,211,153,0.3));
      mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor;
      mask-composite: exclude;
      animation: borderShift 4s ease infinite;
    }
    
    @keyframes borderShift {
      0% { background: linear-gradient(135deg, rgba(110,231,255,0.6), rgba(167,139,250,0.3)); }
      50% { background: linear-gradient(225deg, rgba(52,211,153,0.6), rgba(110,231,255,0.4)); }
      100% { background: linear-gradient(135deg, rgba(110,231,255,0.6), rgba(167,139,250,0.3)); }
    }
    
    .hologram-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 20px 40px rgba(110,231,255,0.15);
    }
    
    .hologram-content {
      position: relative;
      z-index: 2;
      text-align: center;
    }
    
    .hologram-icon {
      font-size: 48px;
      margin-bottom: 16px;
      animation: iconFloat 3s ease-in-out infinite;
    }
    
    @keyframes iconFloat {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-6px); }
    }
    
    .hologram-number {
      font-size: 56px;
      font-weight: 800;
      background: linear-gradient(135deg, #fff, #6ee7ff, #a78bfa);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-bottom: 8px;
    }
    
    .hologram-card[data-card="groups"] .hologram-number {
      background: linear-gradient(135deg, #fff, #34d399, #6ee7ff);
      -webkit-background-clip: text;
      background-clip: text;
    }
    
    .hologram-label {
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 3px;
      color: rgba(255,255,255,0.6);
      margin-bottom: 12px;
    }
    
    .hologram-trend {
      font-size: 12px;
      color: rgba(255,255,255,0.5);
    }
    
    .trend-up {
      color: #34d399;
      font-weight: 600;
    }
    
    .hologram-reflection {
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.06), transparent);
      transform: skewX(-20deg);
      transition: left 0.5s ease;
      pointer-events: none;
    }
    
    .hologram-card:hover .hologram-reflection {
      left: 150%;
    }
    
    .section-title {
      font-size: clamp(24px, 5vw, 32px);
      text-align: center;
      margin: 50px 0 30px;
      font-weight: 700;
      background: linear-gradient(135deg, #fff, #6ee7ff);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 28px;
      margin: 30px 0;
    }
    
    .feature-card {
      background: rgba(17, 31, 56, 0.5);
      border-radius: 28px;
      padding: 28px 24px;
      border: 1px solid rgba(255,255,255,0.08);
      transition: all 0.3s ease;
    }
    
    .feature-card:hover {
      transform: translateY(-6px);
      border-color: rgba(110,231,255,0.4);
      background: rgba(17, 31, 56, 0.7);
    }
    
    .feature-icon {
      font-size: 48px;
      margin-bottom: 18px;
    }
    
    .feature-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 12px;
    }
    
    .feature-desc {
      color: rgba(255,255,255,0.65);
      line-height: 1.55;
    }
    
    .steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 30px;
      margin: 40px 0;
    }
    
    .step {
      text-align: center;
      padding: 20px;
    }
    
    .step-number {
      width: 55px;
      height: 55px;
      background: linear-gradient(135deg, #6ee7ff, #a78bfa);
      border-radius: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      font-weight: 800;
      margin: 0 auto 20px;
    }
    
    .testimonial {
      background: linear-gradient(135deg, rgba(110,231,255,0.08), rgba(167,139,250,0.08));
      border-radius: 32px;
      padding: 40px 32px;
      margin: 50px 0;
      text-align: center;
      border: 1px solid rgba(110,231,255,0.15);
    }
    
    .cta-section {
      text-align: center;
      background: radial-gradient(ellipse at 50% 0%, rgba(110,231,255,0.08), transparent);
      border-radius: 40px;
      padding: 55px 30px;
      margin: 50px 0 30px;
    }
    
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.7);
      backdrop-filter: blur(10px);
      z-index: 1000;
      display: none;
      align-items: center;
      justify-content: center;
    }
    
    .modal-card {
      max-width: 420px;
      width: 90%;
      background: rgba(17, 31, 56, 0.95);
      border: 1px solid rgba(110,231,255,0.3);
      border-radius: 32px;
      animation: modalFadeIn 0.3s ease;
    }
    
    @keyframes modalFadeIn {
      from { opacity: 0; transform: scale(0.95) translateY(20px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .modal-close {
      background: none;
      border: none;
      color: rgba(255,255,255,0.6);
      font-size: 32px;
      cursor: pointer;
    }
    
    .modal-body {
      padding: 24px;
    }

    .footer {
      margin-top: 50px;
      padding: 25px 0;
      text-align: center;
      border-top: 1px solid rgba(255,255,255,0.08);
    }
    
    .footer-text {
      color: rgba(255,255,255,0.4);
      font-size: 13px;
    }
    
    .topbar .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .brand-logo {
      width: 36px;
      height: 36px;
      border-radius: 12px;
      background: linear-gradient(135deg, #6ee7ff, #a78bfa);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      font-weight: 800;
      box-shadow: 0 0 15px rgba(110,231,255,0.3);
    }
    
    @media (max-width: 700px) {
      .hologram-container { gap: 24px; }
      .hologram-card { width: 240px; padding: 24px 16px; }
      .hologram-number { font-size: 42px; }
    }
    
    @media (max-width: 550px) {
      .hologram-container { flex-direction: column; align-items: center; }
      .hologram-card { width: 260px; }
    }
    
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 10px; }
    ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #6ee7ff, #a78bfa); border-radius: 10px; }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="nav">
      <div class="brand">
        <img src="assets/images/csh-logo.png" alt="CSH" style="width: 36px; height: 36px; border-radius: 12px;">
        <div>
          CSH <span style="color: var(--text-muted); font-weight: 600;">Collaborative Study Hub</span>
        </div>
      </div>
      <div class="navlinks">
        <a href="#features">Features</a>
        <a href="#how-it-works">How It Works</a>
        <a href="index.php">Home</a>
      </div>
    </div>
  </div>

  <div class="container">
    <div class="hero">
      <h1 class="hero-title">Study Smarter, Together</h1>
      <p class="hero-subtitle">
        Connect with peers who share your learning style. Collaborate in real-time,
        share resources and ace your courses together.
      </p>
      <div class="hero-buttons">
        <a href="register.php" class="btn btn-primary btn-large">Get Started</a>
        <a href="#" class="btn btn-large" onclick="openLoginModal(); return false;">Sign In</a>
      </div>
    </div>

    <div class="stats-hologram">
      <div class="hologram-container">
        <div class="hologram-card" data-card="users">
          <div class="hologram-border"></div>
          <div class="hologram-content">
            <div class="hologram-icon">👥</div>
            <div class="hologram-number" id="statUsers"><?= number_format($stats['users']) ?></div>
            <div class="hologram-label">Active Students</div>
            <div class="hologram-trend">
              <span class="trend-up">↑ +12%</span> this month
            </div>
          </div>
          <div class="hologram-reflection"></div>
        </div>

        <div class="hologram-card" data-card="groups">
          <div class="hologram-border"></div>
          <div class="hologram-content">
            <div class="hologram-icon">👥</div>
            <div class="hologram-number" id="statGroups"><?= number_format($stats['groups']) ?></div>
            <div class="hologram-label">Study Groups</div>
            <div class="hologram-trend">
              <span class="trend-up">↑ +8%</span> this month
            </div>
          </div>
          <div class="hologram-reflection"></div>
        </div>
      </div>
    </div>

    <h2 class="section-title" id="features">✨ What Makes CSH Different?</h2>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">🧠</div>
        <div class="feature-title">VARK-Based Matching</div>
        <div class="feature-desc">We match you with study partners who share your learning style.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">💬</div>
        <div class="feature-title">Real-Time Chat</div>
        <div class="feature-desc">Group chat with file sharing and message history.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📚</div>
        <div class="feature-title">Shared Resources</div>
        <div class="feature-desc">Upload notes and links. Organize by subject.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🔒</div>
        <div class="feature-title">Privacy First</div>
        <div class="feature-desc">Your learning style is never shared with others.</div>
      </div>
    </div>

    <h2 class="section-title" id="how-it-works">🚀 How It Works</h2>
    <div class="steps">
      <div class="step"><div class="step-number">1</div><div class="feature-title">Create Account</div><div class="feature-desc">Sign up in 30 seconds.</div></div>
      <div class="step"><div class="step-number">2</div><div class="feature-title">Take VARK Quiz</div><div class="feature-desc">Answer 15 questions.</div></div>
      <div class="step"><div class="step-number">3</div><div class="feature-title">Join or Create Groups</div><div class="feature-desc">Find study groups matched to you.</div></div>
      <div class="step"><div class="step-number">4</div><div class="feature-title">Collaborate & Succeed</div><div class="feature-desc">Chat, share and study together.</div></div>
    </div>

    <div class="testimonial">
      <div class="testimonial-text">
        "CSH completely changed how I study. Being matched with learners like me made group projects so much easier!"
      </div>
      <div class="p" style="margin: 0;">Computer Science Student</div>
    </div>

    <!-- CTA -->
    <div class="cta-section">
      <h2 style="margin-bottom: 16px;">Ready to transform the way you study?</h2>
      <p class="p" style="max-width: 500px; margin: 0 auto 24px;">
        Join hundreds of students who are already collaborating smarter, not harder.
      </p>
      <a href="register.php" class="btn btn-primary btn-large">Create Account →</a>
      <div style="margin-top: 20px;">
        <a href="#" onclick="openLoginModal(); return false;" style="color: var(--text-muted);">Already have an account? Sign in</a>
      </div>
    </div>
  </div>

  <div id="loginModal" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h2 style="margin: 0;">Welcome Back</h2>
        <button class="modal-close" onclick="closeLoginModal()">&times;</button>
      </div>
      <div class="modal-body">
        <?php if ($login_error): ?>
          <div class="notice bad" style="margin-bottom: 16px;">Invalid email or password.</div>
        <?php endif; ?>
        <form method="POST" action="index.php">
          <input type="hidden" name="login_action" value="1">
          <input class="input" name="email" type="email" placeholder="Email address" required style="margin-bottom: 14px; width: 100%;">
          <input class="input" name="password" type="password" placeholder="Password" required style="margin-bottom: 20px; width: 100%;">
          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">Sign In</button>
        </form>
        <div class="p" style="margin-top: 20px; text-align: center;">
          Don't have an account? <a href="register.php" style="color: #6ee7ff;">Create one</a>
        </div>
      </div>
    </div>
  </div>

  <div class="footer">
    <div class="container">
      <div class="footer-text">
        © <?= date('Y') ?> Collaborative Study Hub — BSc Computer Science Project
      </div>
    </div>
  </div>

  <script>
    function openLoginModal() {
      document.getElementById('loginModal').style.display = 'flex';
    }
    function closeLoginModal() {
      document.getElementById('loginModal').style.display = 'none';
    }
    document.getElementById('loginModal').addEventListener('click', function(e) {
      if (e.target === this) closeLoginModal();
    });
    
    function animateNumber(element, start, end, duration) {
      if (!element) return;
      let startTime = null;
      const step = (timestamp) => {
        if (!startTime) startTime = timestamp;
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const current = Math.floor(progress * (end - start) + start);
        element.textContent = current.toLocaleString();
        if (progress < 1) requestAnimationFrame(step);
        else element.textContent = end.toLocaleString();
      };
      requestAnimationFrame(step);
    }
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const usersNum = document.getElementById('statUsers');
          const groupsNum = document.getElementById('statGroups');
          if (usersNum && !usersNum.hasAttribute('data-animated')) {
            animateNumber(usersNum, 0, <?= (int)$stats['users'] ?>, 1500);
            usersNum.setAttribute('data-animated', 'true');
          }
          if (groupsNum && !groupsNum.hasAttribute('data-animated')) {
            animateNumber(groupsNum, 0, <?= (int)$stats['groups'] ?>, 1500);
            groupsNum.setAttribute('data-animated', 'true');
          }
        }
      });
    }, { threshold: 0.3 });
    
    document.querySelectorAll('.hologram-card').forEach(card => observer.observe(card));
  </script>
</body>
</html>