<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $h($pageTitle ?? 'Dashboard') ?> | Yobobot Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/static/dashboard.css" />
</head>
<body class="dashboard-app">
  <main class="workspace-shell" data-page="<?= $h($page ?? 'overview') ?>">
    <aside class="workspace-sidebar">
      <div class="sidebar-brand">
        <div class="brand-mark">
          <span><?= $h($employeeInitials ?? 'EM') ?></span>
        </div>
        <div>
          <strong data-company-display><?= $h(($employee['company_name'] ?? '') ?: 'Rental HQ') ?></strong>
          <span>Employee dashboard</span>
        </div>
      </div>

      <nav class="sidebar-nav" aria-label="Dashboard sections">
        <a class="sidebar-link <?= ($page ?? '') === 'overview' ? 'is-active' : '' ?>" href="/dashboard">
          <span class="sidebar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M4 13h6V4H4z"></path>
              <path d="M14 20h6v-9h-6z"></path>
              <path d="M14 4h6v5h-6z"></path>
              <path d="M4 20h6v-3H4z"></path>
            </svg>
          </span>
          <span>Dashboard</span>
        </a>

        <a class="sidebar-link <?= ($page ?? '') === 'bookings' ? 'is-active' : '' ?>" href="/dashboard/bookings">
          <span class="sidebar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="5" width="18" height="16" rx="2"></rect>
              <path d="M8 3v4"></path>
              <path d="M16 3v4"></path>
              <path d="M3 10h18"></path>
            </svg>
          </span>
          <span>Bookings</span>
        </a>

        <a class="sidebar-link <?= ($page ?? '') === 'fleet' ? 'is-active' : '' ?>" href="/dashboard/fleet">
          <span class="sidebar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M4 16v-3l2-5h12l2 5v3"></path>
              <path d="M6 16h12"></path>
              <circle cx="8" cy="17" r="1.6"></circle>
              <circle cx="16" cy="17" r="1.6"></circle>
            </svg>
          </span>
          <span>Fleet</span>
        </a>

        <a class="sidebar-link <?= ($page ?? '') === 'availability' ? 'is-active' : '' ?>" href="/dashboard/availability">
          <span class="sidebar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M7 3v4"></path>
              <path d="M17 3v4"></path>
              <rect x="3" y="5" width="18" height="16" rx="2"></rect>
              <path d="M3 10h18"></path>
              <path d="M8 14h3"></path>
              <path d="M13 14h3"></path>
              <path d="M8 18h3"></path>
            </svg>
          </span>
          <span>Availability</span>
        </a>
      </nav>

      <div class="sidebar-settings">
        <button id="settingsToggle" class="settings-trigger" type="button" aria-expanded="true" aria-controls="settingsPanel">
          <span class="sidebar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M4 7h9"></path>
              <path d="M16 7h4"></path>
              <path d="M9 7a2 2 0 1 0 0 0.01"></path>
              <path d="M4 17h4"></path>
              <path d="M15 17h5"></path>
              <path d="M15 17a2 2 0 1 0 0 0.01"></path>
            </svg>
          </span>
          <span>Settings</span>
          <span class="settings-caret" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M5 7.5 10 12.5 15 7.5"></path>
            </svg>
          </span>
        </button>

        <div id="settingsPanel" class="settings-panel">
          <a class="settings-link is-highlighted" href="<?= ($page ?? '') === 'overview' ? '#customizationPanel' : '/dashboard#customizationPanel' ?>">Customization</a>
        </div>
      </div>

      <div class="sidebar-spacer"></div>

      <div class="sidebar-profile-wrap">
        <button id="profileToggle" class="sidebar-profile" type="button" aria-expanded="false" aria-controls="profileMenu">
          <span class="profile-avatar"><?= $h($employeeInitials ?? 'EM') ?></span>
          <span class="profile-copy">
            <strong><?= $h(($employee['full_name'] ?? '') ?: ($employee['email'] ?? '')) ?></strong>
            <span><?= $h($employee['email'] ?? '') ?></span>
          </span>
          <span class="profile-caret" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M5 7.5 10 12.5 15 7.5"></path>
            </svg>
          </span>
        </button>

        <div id="profileMenu" class="profile-menu hidden">
          <div class="profile-menu-head">
            <span class="profile-avatar large"><?= $h($employeeInitials ?? 'EM') ?></span>
            <div>
              <strong><?= $h(($employee['full_name'] ?? '') ?: ($employee['email'] ?? '')) ?></strong>
              <span><?= $h($employee['email'] ?? '') ?></span>
              <span><span data-company-display><?= $h(($employee['company_name'] ?? '') ?: ($employee['biz_id'] ?? '')) ?></span> · <?= $h(ucfirst((string) ($employee['role'] ?? 'staff'))) ?></span>
            </div>
          </div>
          <div class="profile-menu-links">
            <button type="button">Theme</button>
            <button type="button">Profile</button>
            <button type="button">Account</button>
            <button type="button">Billing</button>
          </div>
          <a class="profile-logout" href="/logout">Log out</a>
        </div>
      </div>
    </aside>

    <section class="workspace-main">
      <header class="workspace-header">
        <div>
          <h1><?= $h($pageTitle ?? 'Dashboard') ?></h1>
          <p><?= $h($pageSubtitle ?? '') ?></p>
        </div>

        <div class="workspace-actions">
          <?php if (($page ?? '') === 'overview'): ?>
            <a class="ghost-button" href="/dashboard/bookings">Open Bookings</a>
            <a class="primary-button" href="/dashboard/fleet">Open Fleet</a>
          <?php elseif (($page ?? '') === 'bookings'): ?>
            <button id="downloadBookingsButton" class="ghost-button" type="button">Download</button>
            <a class="primary-button" href="/dashboard/availability">Availability</a>
          <?php elseif (($page ?? '') === 'fleet'): ?>
            <button id="fleetImportTrigger" class="ghost-button" type="button">Import Fleet</button>
            <a class="primary-button" href="/dashboard/availability">Open Availability</a>
          <?php else: ?>
            <button id="availabilityTodayButton" class="ghost-button" type="button">Today</button>
            <a class="primary-button" href="/dashboard/bookings">View Bookings</a>
          <?php endif; ?>
        </div>
      </header>

      <div id="feedbackBanner" class="feedback-banner hidden"></div>

      <?php if (($page ?? '') === 'overview'): ?>
        <section class="overview-grid">
          <div class="metric-row">
            <article class="metric-card">
              <span>Site visits</span>
              <strong id="overviewStatVisits">0</strong>
            </article>
            <article class="metric-card">
              <span>Total bookings</span>
              <strong id="overviewStatTotal">0</strong>
            </article>
            <article class="metric-card">
              <span>Confirmed</span>
              <strong id="overviewStatConfirmed">0</strong>
            </article>
            <article class="metric-card">
              <span>Pending</span>
              <strong id="overviewStatPending">0</strong>
            </article>
            <article class="metric-card">
              <span>Paid</span>
              <strong id="overviewStatPaid">0</strong>
            </article>
            <article class="metric-card">
              <span>Fleet cars</span>
              <strong id="overviewStatFleet">0</strong>
            </article>
          </div>

          <section class="workspace-panel launch-panel">
            <div class="panel-head">
              <div>
                <p class="panel-eyebrow">Setup progress</p>
                <h2>What needs to be finished before your live booking link is unlocked</h2>
              </div>
            </div>
            <div id="setupChecklist" class="setup-checklist"></div>
            <div class="launch-link-panel">
              <div>
                <span class="field-label-inline">Live booking link</span>
                <strong id="setupBookingLink">Locked until setup is complete</strong>
                <p id="setupBookingHint" class="panel-copy compact">Complete setup and confirm your Yobobot demo before your public site goes live.</p>
              </div>
              <a class="ghost-button small" href="<?= $h($upgradeUrl ?? '') ?>" target="_blank" rel="noreferrer">Upgrade custom links</a>
            </div>
          </section>

          <section class="workspace-panel">
            <div class="panel-head">
              <div>
                <p class="panel-eyebrow">Today’s flow</p>
                <h2>Current and upcoming reservations</h2>
              </div>
              <a class="text-link" href="/dashboard/bookings">Open full list</a>
            </div>
            <div id="overviewBookingList" class="booking-preview-list"></div>
            <div id="overviewBookingEmpty" class="empty-state hidden">
              <h3>No live bookings right now.</h3>
              <p>As new reservations land in Supabase, they’ll appear here automatically.</p>
            </div>
          </section>

          <section class="workspace-panel overview-side-panel">
            <div class="panel-head">
              <div>
                <p class="panel-eyebrow">Fleet snapshot</p>
                <h2>Availability this week</h2>
              </div>
            </div>
            <div class="overview-availability-stats">
              <article>
                <span>Cars tracked</span>
                <strong id="overviewCarsTotal">0</strong>
              </article>
              <article>
                <span>Available today</span>
                <strong id="overviewCarsAvailable">0</strong>
              </article>
              <article>
                <span>Booked today</span>
                <strong id="overviewCarsBooked">0</strong>
              </article>
            </div>
            <div id="overviewWeekPreview" class="mini-week-grid"></div>
          </section>

          <?php if (!empty($canManageAccess)): ?>
            <section id="invitePanel" class="workspace-panel invite-panel">
              <div class="panel-head">
                <div>
                  <p class="panel-eyebrow">Access</p>
                  <h2>Invite a new employee</h2>
                </div>
              </div>
              <p class="invite-copy">
                Save the employee’s work email and share their invite link so they can create a more limited dashboard login.
              </p>
              <a class="text-link wrap" href="<?= $h($registerUrl ?? '') ?>" target="_blank" rel="noreferrer"><?= $h($registerUrl ?? '') ?></a>

              <form id="inviteForm" class="invite-form">
                <label class="field-group">
                  <span>Employee name</span>
                  <input id="inviteFullName" type="text" placeholder="Operations Manager" required />
                </label>
                <label class="field-group">
                  <span>Work email</span>
                  <input id="inviteEmail" type="email" placeholder="employee@company.com" required />
                </label>
                <label class="field-group">
                  <span>Role</span>
                  <select id="inviteRole">
                    <?php $role = strtolower((string) ($employee['role'] ?? 'staff')); ?>
                    <?php if ($role === 'owner'): ?>
                      <option value="admin">Admin</option>
                      <option value="manager">Manager</option>
                      <option value="staff" selected>Staff</option>
                    <?php elseif ($role === 'admin'): ?>
                      <option value="manager">Manager</option>
                      <option value="staff" selected>Staff</option>
                    <?php else: ?>
                      <option value="staff" selected>Staff</option>
                    <?php endif; ?>
                  </select>
                </label>
                <button type="submit" class="primary-button">Save Invite</button>
              </form>
            </section>
          <?php endif; ?>

          <section id="customizationPanel" class="workspace-panel customization-panel" data-can-customize="<?= !empty($canCustomize) ? 'true' : 'false' ?>">
            <div class="panel-head">
              <div>
                <p class="panel-eyebrow">Customization</p>
                <h2>Edit your booking brand</h2>
              </div>
            </div>
            <p class="panel-copy">
              Update the business name, preview the public booking link, and refine the colors and fonts
              customers see when they interact with your booking site.
            </p>

            <div class="customization-grid">
              <form id="customizationForm" class="customization-form">
                <label class="field-group">
                  <span>Business name</span>
                  <input id="customizationCompanyName" type="text" placeholder="Your Rental Company" <?= empty($canCustomize) ? 'disabled' : '' ?> />
                </label>

                <label class="field-group">
                  <span>Booking link</span>
                  <div class="customization-link-row">
                    <input id="customizationBookingLink" type="text" readonly />
                    <a class="ghost-button small upgrade-link-button" href="<?= $h($upgradeUrl ?? '') ?>" target="_blank" rel="noreferrer">Upgrade</a>
                  </div>
                </label>

                <label class="field-group">
                  <span>Logo URL</span>
                  <input id="customizationLogoUrl" type="url" placeholder="https://yourcdn.com/logo.png" <?= empty($canCustomize) ? 'disabled' : '' ?> />
                </label>

                <label class="field-group">
                  <span>Primary color</span>
                  <div class="color-field-row">
                    <input id="customizationAccentColor" class="color-input" type="color" value="#72a9ff" <?= empty($canCustomize) ? 'disabled' : '' ?> />
                    <input id="customizationAccentText" type="text" placeholder="#72a9ff" <?= empty($canCustomize) ? 'disabled' : '' ?> />
                  </div>
                </label>

                <label class="field-group">
                  <span>Heading font</span>
                  <select id="customizationHeadingFont" <?= empty($canCustomize) ? 'disabled' : '' ?>></select>
                </label>

                <label class="field-group">
                  <span>Body font</span>
                  <select id="customizationBodyFont" <?= empty($canCustomize) ? 'disabled' : '' ?>></select>
                </label>

                <label class="field-group">
                  <span>Discount type</span>
                  <select id="customizationDiscountType" <?= empty($canCustomize) ? 'disabled' : '' ?>>
                    <option value="none">No discount</option>
                    <option value="percentage">Percentage</option>
                    <option value="fixed">Fixed amount</option>
                  </select>
                </label>

                <label class="field-group">
                  <span>Discount value</span>
                  <input id="customizationDiscountValue" type="number" min="0" step="0.01" placeholder="0" <?= empty($canCustomize) ? 'disabled' : '' ?> />
                </label>

                <label class="field-group onboarding-checkbox">
                  <input id="customizationCustomDomainRequested" type="checkbox" <?= empty($canCustomize) ? 'disabled' : '' ?> />
                  <span>Offer me an upgrade to customize my booking link</span>
                </label>

                <label class="field-group onboarding-checkbox">
                  <input id="customizationDemoCompleted" type="checkbox" <?= empty($canCustomize) ? 'disabled' : '' ?> />
                  <span>We completed the Yobobot demo and launch review</span>
                </label>

                <section class="bot-question-section">
                  <div class="bot-question-head">
                    <div>
                      <span class="panel-eyebrow">Bot Intake</span>
                      <h3>Choose what the bot asks</h3>
                    </div>
                    <span id="botQuestionCounter" class="bot-question-counter">2 / 10 questions</span>
                  </div>
                  <p class="panel-copy">
                    Full name and phone are always collected. Choose the extra questions the bot should ask and
                    edit the wording customers see, with a maximum of 10 total questions.
                  </p>
                  <div id="alwaysCollectedList" class="always-collected-list"></div>
                  <div id="botFieldList" class="bot-field-list"></div>
                </section>

                <div class="customization-actions">
                  <?php if (!empty($canCustomize)): ?>
                    <button type="submit" class="primary-button">Save customization</button>
                    <button id="launchSiteButton" type="button" class="ghost-button">Unlock live booking link</button>
                  <?php else: ?>
                    <p class="customization-note">Only managers can change the business branding.</p>
                  <?php endif; ?>
                </div>
              </form>

              <aside class="customization-preview">
                <p class="panel-eyebrow">Live preview</p>
                <article id="customizationPreviewCard" class="customization-preview-card">
                  <span id="previewWordmark" class="preview-wordmark">Your Brand</span>
                  <h3 id="previewHeading">Make every booking feel on-brand.</h3>
                  <p id="previewCopy">Preview your chosen colors and font pairing before saving the customer-facing booking site style.</p>
                  <div id="previewLogoWrap" class="preview-logo-wrap hidden">
                    <img id="previewLogoImage" class="preview-logo-image" alt="Business logo preview" />
                  </div>
                  <div class="preview-link-row">
                    <span id="previewBookingLink" class="preview-booking-link">https://yourbrand.yobobot.in</span>
                    <span id="previewUpgradeBadge" class="preview-upgrade-badge">Upgrade for custom domain</span>
                  </div>
                </article>
              </aside>
            </div>
          </section>
        </section>
      <?php elseif (($page ?? '') === 'bookings'): ?>
        <section class="workspace-panel bookings-toolbar-panel">
          <div class="toolbar-top">
            <div class="segmented-group" id="timelineTabs">
              <button type="button" class="segment-button is-active" data-timeline="current">Current</button>
              <button type="button" class="segment-button" data-timeline="upcoming">Upcoming</button>
              <button type="button" class="segment-button" data-timeline="past">Past</button>
              <button type="button" class="segment-button" data-timeline="all">All</button>
            </div>
            <div class="toolbar-inline-actions">
              <button id="resetFilters" type="button" class="ghost-button small">Reset filters</button>
            </div>
          </div>

          <div class="toolbar-filters">
            <label class="search-field">
              <span class="sr-only">Search bookings</span>
              <span class="field-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <circle cx="11" cy="11" r="7"></circle>
                  <path d="M20 20l-3.5-3.5"></path>
                </svg>
              </span>
              <input id="searchInput" type="search" placeholder="Search customers, phone, payment status, or car" />
            </label>

            <label class="field-group compact">
              <span>From date</span>
              <input id="startDateFilter" type="date" />
            </label>

            <label class="field-group compact">
              <span>To date</span>
              <input id="endDateFilter" type="date" />
            </label>

            <label class="field-group compact">
              <span>Car model</span>
              <select id="carModelFilter">
                <option value="">All cars</option>
              </select>
            </label>

            <label class="field-group compact">
              <span>Confirmation</span>
              <select id="confirmationFilter">
                <option value="all">All</option>
                <option value="confirmed">Confirmed</option>
                <option value="pending">Pending</option>
              </select>
            </label>

            <label class="field-group compact">
              <span>Payment</span>
              <select id="paymentFilter">
                <option value="all">All</option>
                <option value="paid">Paid</option>
                <option value="pending">Pending</option>
                <option value="partially paid">Partially Paid</option>
                <option value="failed">Failed</option>
              </select>
            </label>
          </div>
        </section>

        <section class="workspace-panel booking-results-panel">
          <div class="panel-head">
            <div>
              <p class="panel-eyebrow">Live bookings</p>
              <h2 id="resultsCount">0 results</h2>
            </div>
            <div class="results-summary">
              <span><strong id="statTotal">0</strong> total</span>
              <span><strong id="statConfirmed">0</strong> confirmed</span>
              <span><strong id="statPending">0</strong> pending</span>
              <span><strong id="statPaid">0</strong> paid</span>
            </div>
          </div>

          <div class="bookings-table-wrap">
            <div id="bookingsTableHead" class="bookings-table-head"></div>
            <div id="rowsContainer" class="bookings-table-body"></div>
            <div id="emptyState" class="empty-state hidden">
              <h3>No bookings found</h3>
              <p>Try widening the date range or clearing one of the filters.</p>
            </div>
          </div>
        </section>
      <?php elseif (($page ?? '') === 'availability'): ?>
        <section class="workspace-panel availability-toolbar-panel">
          <div class="toolbar-top">
            <div class="segmented-group" id="availabilityViewTabs">
              <button type="button" class="segment-button is-active" data-view="board">Availability Board</button>
              <button type="button" class="segment-button" data-view="calendar">Calendar View</button>
            </div>

            <div class="range-controls">
              <button id="availabilityPrevButton" class="icon-button" type="button" aria-label="Previous range">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M12.5 4.5 7 10l5.5 5.5"></path>
                </svg>
              </button>
              <span id="availabilityRangeLabel">Loading range...</span>
              <button id="availabilityNextButton" class="icon-button" type="button" aria-label="Next range">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="m7.5 4.5 5.5 5.5-5.5 5.5"></path>
                </svg>
              </button>
            </div>
          </div>

          <div class="toolbar-filters availability-filters">
            <label class="search-field">
              <span class="sr-only">Search cars</span>
              <span class="field-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <circle cx="11" cy="11" r="7"></circle>
                  <path d="M20 20l-3.5-3.5"></path>
                </svg>
              </span>
              <input id="availabilitySearchInput" type="search" placeholder="Search by car, model, color, or location" />
            </label>

            <label class="field-group compact">
              <span>Date</span>
              <input id="availabilityDateInput" type="date" />
            </label>

            <label class="field-group compact">
              <span>Car</span>
              <select id="availabilityCarFilter">
                <option value="">All cars</option>
              </select>
            </label>

            <label class="field-group compact">
              <span>Model</span>
              <select id="availabilityModelFilter">
                <option value="">All models</option>
              </select>
            </label>

            <label class="field-group compact">
              <span>Color</span>
              <select id="availabilityColorFilter">
                <option value="">All colors</option>
              </select>
            </label>

            <button id="availabilityResetButton" type="button" class="ghost-button small">Reset</button>
          </div>
        </section>

        <section id="availabilityBoardPanel" class="workspace-panel availability-board-panel">
          <div class="panel-head">
            <div>
              <p class="panel-eyebrow">Weekly board</p>
              <h2>Car availability this week</h2>
            </div>
            <div class="results-summary">
              <span><strong id="availabilityTotalCars">0</strong> cars</span>
              <span><strong id="availabilityTodayFree">0</strong> free today</span>
              <span><strong id="availabilityTodayBooked">0</strong> booked today</span>
            </div>
          </div>

          <div class="availability-board-wrap">
            <div class="availability-board-head">
              <div>Car</div>
              <div id="availabilityDayHeader" class="availability-day-header"></div>
            </div>
            <div id="availabilityBoardRows" class="availability-board-rows"></div>
            <div id="availabilityEmptyState" class="empty-state hidden">
              <h3>No cars match those filters.</h3>
              <p>Try removing a color or model filter to widen the schedule.</p>
            </div>
          </div>
        </section>

        <section id="availabilityCalendarPanel" class="workspace-panel availability-calendar-panel hidden">
          <div class="panel-head">
            <div>
              <p class="panel-eyebrow">Month calendar</p>
              <h2 id="availabilityMonthLabel">Loading month...</h2>
            </div>
          </div>

          <div class="calendar-grid">
            <div class="calendar-weekdays">
              <span>Mon</span>
              <span>Tue</span>
              <span>Wed</span>
              <span>Thu</span>
              <span>Fri</span>
              <span>Sat</span>
              <span>Sun</span>
            </div>
            <div id="availabilityCalendarGrid" class="calendar-cells"></div>
          </div>
        </section>
      <?php else: ?>
        <section class="workspace-panel fleet-summary-panel">
          <div class="panel-head">
            <div>
              <p class="panel-eyebrow">Fleet setup</p>
              <h2>Inventory, branch, and pricing details</h2>
            </div>
            <div class="results-summary">
              <span><strong id="fleetCountStat">0</strong> vehicles</span>
              <span><strong id="fleetPricedStat">0</strong> fully priced</span>
            </div>
          </div>
          <p class="panel-copy">Every vehicle saved here is attached to this business’s autogenerated <code>biz_id</code> in Supabase. If this business has no cars yet, the fleet board will stay empty.</p>
        </section>

        <section class="fleet-grid-layout">
          <section class="workspace-panel">
            <div class="panel-head">
              <div>
                <p class="panel-eyebrow">Add or edit</p>
                <h2>Vehicle details</h2>
              </div>
            </div>
            <form id="fleetForm" class="fleet-form">
              <input id="fleetId" type="hidden" />
              <div class="fleet-form-grid">
                <label class="field-group">
                  <span>Make</span>
                  <input id="fleetMake" type="text" placeholder="Toyota" required />
                </label>
                <label class="field-group">
                  <span>Model</span>
                  <input id="fleetModel" type="text" placeholder="Fortuner" required />
                </label>
                <label class="field-group">
                  <span>Colour</span>
                  <input id="fleetColor" type="text" placeholder="White" />
                </label>
                <label class="field-group">
                  <span>Number plate</span>
                  <input id="fleetNumberPlate" type="text" placeholder="MH12AB1234" />
                </label>
                <label class="field-group">
                  <span>Photo URL</span>
                  <input id="fleetPhotoUrl" type="url" placeholder="https://..." />
                </label>
                <label class="field-group">
                  <span>Category</span>
                  <input id="fleetCategory" type="text" placeholder="SUV" />
                </label>
                <label class="field-group">
                  <span>City / branch</span>
                  <input id="fleetBranchLocation" type="text" placeholder="Mumbai - Bandra" />
                </label>
                <label class="field-group">
                  <span>Price per day</span>
                  <input id="fleetPricePerDay" type="number" min="0" step="0.01" placeholder="4500" />
                </label>
                <label class="field-group">
                  <span>Price per week</span>
                  <input id="fleetPricePerWeek" type="number" min="0" step="0.01" placeholder="28000" />
                </label>
                <label class="field-group">
                  <span>Price per month</span>
                  <input id="fleetPricePerMonth" type="number" min="0" step="0.01" placeholder="95000" />
                </label>
                <label class="field-group onboarding-checkbox">
                  <input id="fleetAvailable" type="checkbox" checked />
                  <span>Available for bookings</span>
                </label>
              </div>
              <div class="customization-actions">
                <button type="submit" class="primary-button">Save vehicle</button>
                <button id="fleetResetButton" type="button" class="ghost-button">Clear form</button>
              </div>
            </form>
          </section>

          <section class="workspace-panel">
            <div class="panel-head">
              <div>
                <p class="panel-eyebrow">Bulk upload</p>
                <h2>Import CSV or JSON</h2>
              </div>
            </div>
            <div class="fleet-import-stack">
              <label class="field-group">
                <span>Upload file</span>
                <input id="fleetImportFile" type="file" accept=".csv,.json,application/json,text/csv" />
              </label>
              <label class="field-group onboarding-checkbox">
                <input id="fleetReplaceExisting" type="checkbox" />
                <span>Replace my existing fleet for this business</span>
              </label>
              <p class="panel-copy compact">Supported columns: make, model, colour, number_plate, photo_url, category, city or branch_location, price_per_day, price_per_week, price_per_month.</p>
              <div class="customization-actions">
                <button id="fleetImportButton" type="button" class="primary-button">Import fleet file</button>
              </div>
            </div>
          </section>
        </section>

        <section class="workspace-panel fleet-table-panel">
          <div class="panel-head">
            <div>
              <p class="panel-eyebrow">Saved fleet</p>
              <h2>Vehicles linked to this business</h2>
            </div>
          </div>
          <div id="fleetRows" class="fleet-card-list"></div>
          <div id="fleetEmptyState" class="empty-state hidden">
            <h3>No vehicles added yet</h3>
            <p>Add a car manually or import a CSV/JSON file to start setting up this business fleet.</p>
          </div>
        </section>
      <?php endif; ?>
    </section>
  </main>

  <script>
    window.dashboardConfig = {
      page: <?= json_encode($page ?? 'overview') ?>,
      bookingsUrl: "/api/dashboard/bookings",
      availabilityUrl: "/api/dashboard/availability",
      fleetUrl: "/api/dashboard/fleet",
      fleetImportUrl: "/api/dashboard/fleet/import",
      fleetDeleteUrlTemplate: "/api/dashboard/fleet/__FLEET_ID__",
      customizationUrl: "/api/dashboard/customization",
      updateUrlTemplate: "/api/dashboard/bookings/__BOOKING_ID__/state",
      invitesUrl: "/api/dashboard/invites",
      registerUrl: <?= json_encode($registerUrl ?? '/register') ?>,
      upgradeUrl: <?= json_encode($upgradeUrl ?? '') ?>
    };
  </script>
  <script src="/static/dashboard.js"></script>
</body>
</html>
