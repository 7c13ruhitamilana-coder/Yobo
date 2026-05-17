<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recover Employee Access</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/static/dashboard.css" />
</head>
<body class="login-page">
  <main class="login-shell">
    <section class="login-hero">
      <p class="eyebrow">Password recovery</p>
      <h1>Reset your employee dashboard password.</h1>
      <p class="hero-copy">
        Enter the invited work email on your dashboard account and Supabase will send the recovery email.
      </p>
      <div class="hero-orbit orbit-one"></div>
      <div class="hero-orbit orbit-two"></div>
    </section>

    <section class="login-panel">
      <div class="login-card">
        <p class="eyebrow">Recover access</p>
        <h2>Send recovery email</h2>
        <p class="panel-copy">Use the same work email your manager invited for dashboard access.</p>

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

          <button type="submit" class="primary-button">Send recovery email</button>
        </form>

        <div class="auth-links">
          <a href="/login">Back to sign in</a>
          <a href="/register">Create account</a>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
