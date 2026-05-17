<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Employee Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/static/dashboard.css" />
</head>
<body class="login-page">
  <main class="login-shell">
    <section class="login-hero">
      <p class="eyebrow">Separate employee website</p>
      <h1>Bookings dashboard for your rental team.</h1>
      <p class="hero-copy">
        Employees sign in with their work email and password. Managers can invite new staff safely,
        and invited employees create their own dashboard account without manual password hashes.
      </p>
      <div class="hero-orbit orbit-one"></div>
      <div class="hero-orbit orbit-two"></div>
    </section>

    <section class="login-panel">
      <div class="login-card">
        <p class="eyebrow">Employee access</p>
        <h2>Sign in to the dashboard</h2>
        <p class="panel-copy">Use the email address your manager invited for employee dashboard access.</p>

        <?php if (!empty($flashes)): ?>
          <div class="flash-stack">
            <?php foreach ($flashes as $flash): ?>
              <div class="flash-message <?= $h($flash['category'] ?? 'info') ?>"><?= $h($flash['message'] ?? '') ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="login-form">
          <label class="field-group">
            <span>Work email</span>
            <input type="email" name="email" autocomplete="email" value="<?= $h($email ?? '') ?>" placeholder="team@yourcompany.com" required />
          </label>

          <label class="field-group">
            <span>Password</span>
            <input type="password" name="password" autocomplete="current-password" placeholder="Enter password" required />
          </label>

          <button type="submit" class="primary-button">Open dashboard</button>
        </form>

        <div class="auth-links">
          <a href="/register">Create employee account</a>
          <a href="/recover">Forgot password?</a>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
