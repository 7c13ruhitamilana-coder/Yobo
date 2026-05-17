<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Employee Account</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/static/dashboard.css" />
</head>
<body class="login-page">
  <main class="login-shell">
    <section class="login-hero">
      <p class="eyebrow">Invite-based signup</p>
      <h1>Create your employee dashboard account.</h1>
      <p class="hero-copy">
        Ask a manager to invite your work email first. After that, you can create your own password
        and sign in safely through Supabase Auth.
      </p>
      <div class="hero-orbit orbit-one"></div>
      <div class="hero-orbit orbit-two"></div>
    </section>

    <section class="login-panel">
      <div class="login-card">
        <p class="eyebrow">Employee setup</p>
        <h2>Create account</h2>
        <p class="panel-copy">Only invited work emails can create a dashboard account.</p>

        <?php if (!empty($flashes)): ?>
          <div class="flash-stack">
            <?php foreach ($flashes as $flash): ?>
              <div class="flash-message <?= $h($flash['category'] ?? 'info') ?>"><?= $h($flash['message'] ?? '') ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="login-form">
          <input type="hidden" name="token" value="<?= $h($invite_token ?? '') ?>" />

          <label class="field-group">
            <span>Full name</span>
            <input type="text" name="full_name" autocomplete="name" value="<?= $h($full_name ?? '') ?>" placeholder="Employee name" required />
          </label>

          <label class="field-group">
            <span>Work email</span>
            <input type="email" name="email" autocomplete="email" value="<?= $h($email ?? '') ?>" placeholder="team@yourcompany.com" required />
          </label>

          <label class="field-group">
            <span>Password</span>
            <input type="password" name="password" autocomplete="new-password" placeholder="At least 10 characters" required />
          </label>

          <label class="field-group">
            <span>Confirm password</span>
            <input type="password" name="confirm_password" autocomplete="new-password" placeholder="Re-enter password" required />
          </label>

          <p class="field-hint">Use a strong password with letters and numbers.</p>
          <button type="submit" class="primary-button">Create account</button>
        </form>

        <div class="auth-links">
          <a href="/login">Back to sign in</a>
          <a href="/recover">Need password help?</a>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
