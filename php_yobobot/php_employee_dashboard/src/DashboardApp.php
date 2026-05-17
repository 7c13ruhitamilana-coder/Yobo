<?php

declare(strict_types=1);

namespace PhpEmployeeDashboard;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class DashboardApp
{
    private const ACCESS_MANAGER_ROLES = ['owner', 'admin', 'manager'];
    private const ASSIGNABLE_ROLES = ['staff', 'manager', 'admin', 'owner'];
    private const FONT_CHOICES = [
        'playfair' => ['label' => 'Playfair Display', 'css' => "'Playfair Display', serif"],
        'lora' => ['label' => 'Lora', 'css' => "'Lora', serif"],
        'cormorant' => ['label' => 'Cormorant Garamond', 'css' => "'Cormorant Garamond', serif"],
        'poppins' => ['label' => 'Poppins', 'css' => "'Poppins', sans-serif"],
        'manrope' => ['label' => 'Manrope', 'css' => "'Manrope', sans-serif"],
        'dm-sans' => ['label' => 'DM Sans', 'css' => "'DM Sans', sans-serif"],
        'outfit' => ['label' => 'Outfit', 'css' => "'Outfit', sans-serif"],
        'sora' => ['label' => 'Sora', 'css' => "'Sora', sans-serif"],
    ];
    private const DEFAULT_HEADING_FONT = 'playfair';
    private const DEFAULT_BODY_FONT = 'poppins';
    private const BOT_QUESTION_LIMIT = 10;
    private const BOT_FIELDS_EMPTY_SENTINEL = '__bot_fields_empty__';
    private const BOT_ALWAYS_COLLECTED_FIELDS = [
        ['key' => 'full_name', 'label' => 'Full Name'],
        ['key' => 'phone', 'label' => 'Phone Number'],
    ];
    private const BOT_INTAKE_FIELDS = [
        [
            'key' => 'email',
            'stage' => 'email',
            'modes' => ['rental', 'service'],
            'default' => ['rental' => true, 'service' => true],
            'labels' => ['rental' => 'Email', 'service' => 'Email'],
            'description' => 'Collect an email address for confirmations and follow-up.',
        ],
        [
            'key' => 'zipcode',
            'stage' => 'zipcode',
            'modes' => ['rental', 'service'],
            'default' => ['rental' => true, 'service' => true],
            'labels' => ['rental' => 'Pincode / Area', 'service' => 'Area / Zip Code'],
            'description' => 'Capture the customer area or pincode before the team follows up.',
        ],
        [
            'key' => 'current_vehicle_type',
            'stage' => 'vehicle_type',
            'modes' => ['rental', 'service'],
            'default' => ['rental' => true, 'service' => true],
            'labels' => ['rental' => 'Current Vehicle Type', 'service' => 'Vehicle Make and Model'],
            'description' => 'Ask what vehicle the customer currently drives or needs help with.',
        ],
        [
            'key' => 'interest_form_type',
            'stage' => 'interest_type',
            'modes' => ['rental'],
            'default' => ['rental' => true],
            'labels' => ['rental' => 'Interest Form Type'],
            'description' => 'Ask whether the enquiry is daily, weekly, monthly, or yearly.',
        ],
        [
            'key' => 'contract_company',
            'stage' => 'contract_company',
            'modes' => ['rental', 'service'],
            'default' => ['rental' => true, 'service' => true],
            'labels' => ['rental' => 'Current Contract Company', 'service' => 'Issue / Service Details'],
            'description' => 'Capture the current contract company or the workshop issue details.',
        ],
        [
            'key' => 'contract_expiry',
            'stage' => 'contract_expiry',
            'modes' => ['rental'],
            'default' => ['rental' => true],
            'labels' => ['rental' => 'Current Contract Expiry'],
            'description' => 'Ask when the customer\'s current contract expires.',
        ],
        [
            'key' => 'citizen_status',
            'stage' => 'citizen_status',
            'modes' => ['rental'],
            'default' => ['rental' => true],
            'labels' => ['rental' => 'Citizen / Resident Status'],
            'description' => 'Capture citizen or resident status when the business needs it.',
        ],
    ];

    private string $rootDir;
    private string $viewsDir;
    private string $brandingStorePath;
    private string $customDomainUpgradeUrl = 'https://yobobot.in/pricing';

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);
        $this->viewsDir = $this->rootDir . DIRECTORY_SEPARATOR . 'views';
        $this->brandingStorePath = dirname($this->rootDir) . DIRECTORY_SEPARATOR . 'dashboard_branding.json';
    }

    public function handle(): void
    {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($path === '/' && $method === 'GET') {
            $this->home();
            return;
        }
        if ($path === '/login') {
            $this->login($method);
            return;
        }
        if ($path === '/register') {
            $this->register($method);
            return;
        }
        if ($path === '/recover') {
            $this->recover($method);
            return;
        }
        if ($path === '/logout' && $method === 'GET') {
            $this->logout();
            return;
        }
        if ($path === '/dashboard' && $method === 'GET') {
            $this->dashboardPage('overview');
            return;
        }
        if ($path === '/dashboard/bookings' && $method === 'GET') {
            $this->dashboardPage('bookings');
            return;
        }
        if ($path === '/dashboard/availability' && $method === 'GET') {
            $this->dashboardPage('availability');
            return;
        }
        if ($path === '/dashboard/fleet' && $method === 'GET') {
            $this->dashboardPage('fleet');
            return;
        }
        if ($path === '/api/dashboard/bookings' && $method === 'GET') {
            $this->apiDashboardBookings();
            return;
        }
        if ($path === '/api/dashboard/availability' && $method === 'GET') {
            $this->apiDashboardAvailability();
            return;
        }
        if ($path === '/api/dashboard/fleet' && in_array($method, ['GET', 'POST'], true)) {
            $this->apiDashboardFleet($method);
            return;
        }
        if ($path === '/api/dashboard/fleet/import' && $method === 'POST') {
            $this->apiDashboardFleetImport();
            return;
        }
        if (preg_match('#^/api/dashboard/fleet/([^/]+)$#', $path, $matches) === 1 && $method === 'DELETE') {
            $this->apiDashboardDeleteFleet($matches[1]);
            return;
        }
        if ($path === '/api/dashboard/customization' && in_array($method, ['GET', 'POST'], true)) {
            $this->apiDashboardCustomization($method);
            return;
        }
        if (preg_match('#^/api/dashboard/bookings/([^/]+)/state$#', $path, $matches) === 1 && $method === 'POST') {
            $this->apiUpdateBookingState($matches[1]);
            return;
        }
        if ($path === '/api/dashboard/invites' && $method === 'POST') {
            $this->apiCreateEmployeeInvite();
            return;
        }

        http_response_code(404);
        echo 'Not Found';
    }

    private function home(): void
    {
        if ($this->employeeSession()) {
            $this->redirect('/dashboard');
            return;
        }
        $this->redirect('/login');
    }

    private function login(string $method): void
    {
        $employee = $this->employeeSession();
        if ($employee) {
            $this->redirect('/dashboard');
            return;
        }

        $email = $this->normalizeEmail($this->requestValue('email'));
        if ($method === 'POST') {
            $password = (string) ($this->post('password') ?? '');
            if ($email === '' || $password === '') {
                $this->flash('error', 'Enter both email and password to continue.');
                $this->renderLogin(['email' => $email]);
                return;
            }
            if (!$this->isValidEmail($email)) {
                $this->flash('error', 'Enter a valid work email address.');
                $this->renderLogin(['email' => $email]);
                return;
            }

            try {
                $authData = $this->signInEmployee($email, $password);
                $user = is_array($authData['user'] ?? null) ? $authData['user'] : [];
                $profile = $this->fetchEmployeeProfileByUserId((string) ($user['id'] ?? ''));
                if (!$profile) {
                    $profile = $this->fetchEmployeeProfileByEmail($email);
                }
                if (!$profile) {
                    $profile = $this->ensureProfileFromInvite($user, $email);
                }
            } catch (RuntimeException $exception) {
                $this->flash('error', $exception->getMessage());
                $this->renderLogin(['email' => $email]);
                return;
            }

            if (!$profile || !$this->coerceBool($profile['is_active'] ?? true)) {
                $this->flash('error', 'This email does not have dashboard access yet. Ask a manager to invite your work email first.');
                $this->renderLogin(['email' => $email]);
                return;
            }
            if ($this->text($profile['biz_id'] ?? '') === '') {
                $this->flash('error', 'This employee profile is missing a biz_id.');
                $this->renderLogin(['email' => $email]);
                return;
            }

            $_SESSION['employee'] = $this->buildEmployeeSession($profile, $user);
            $this->redirect('/dashboard');
            return;
        }

        $this->renderLogin(['email' => $email]);
    }

    private function register(string $method): void
    {
        if ($this->employeeSession()) {
            $this->redirect('/dashboard');
            return;
        }

        $inviteToken = $this->text($this->requestValue('token'));
        $email = $this->normalizeEmail($this->requestValue('email'));
        $fullName = $this->text($this->post('full_name'));

        if ($method === 'POST') {
            $password = (string) ($this->post('password') ?? '');
            $confirmPassword = (string) ($this->post('confirm_password') ?? '');

            if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
                $this->flash('error', 'Complete every field before creating the account.');
                $this->renderRegister(['email' => $email, 'full_name' => $fullName, 'invite_token' => $inviteToken]);
                return;
            }
            if (!$this->isValidEmail($email)) {
                $this->flash('error', 'Enter a valid work email address.');
                $this->renderRegister(['email' => $email, 'full_name' => $fullName, 'invite_token' => $inviteToken]);
                return;
            }
            if ($password !== $confirmPassword) {
                $this->flash('error', 'The password confirmation does not match.');
                $this->renderRegister(['email' => $email, 'full_name' => $fullName, 'invite_token' => $inviteToken]);
                return;
            }

            $passwordError = $this->validatePassword($password);
            if ($passwordError !== null) {
                $this->flash('error', $passwordError);
                $this->renderRegister(['email' => $email, 'full_name' => $fullName, 'invite_token' => $inviteToken]);
                return;
            }

            try {
                $invite = $this->fetchEmployeeInvite($email, $inviteToken);
                if (!$invite || !$this->coerceBool($invite['is_active'] ?? true)) {
                    throw new RuntimeException('That email has not been invited yet. Ask a manager to create dashboard access for you first.');
                }

                $inviteEmail = $this->normalizeEmail($invite['email'] ?? '');
                if ($inviteEmail !== '' && $inviteEmail !== $email) {
                    throw new RuntimeException('This invite link belongs to a different email address.');
                }
                $storedInviteToken = $this->text($invite['invite_token'] ?? '');
                if ($inviteToken !== '' && $storedInviteToken !== '' && $inviteToken !== $storedInviteToken) {
                    throw new RuntimeException('This invite link is no longer valid. Ask your admin for a new invite.');
                }

                $existingProfile = $this->fetchEmployeeProfileByEmail($email);
                if ($existingProfile) {
                    throw new RuntimeException('That email already has dashboard access. Sign in instead.');
                }

                $authData = $this->signUpEmployee(
                    $email,
                    $password,
                    $fullName,
                    (string) ($invite['company_name'] ?? '')
                );
                $user = is_array($authData['user'] ?? null) ? $authData['user'] : [];
                $userId = (string) ($user['id'] ?? '');
                if ($userId === '') {
                    throw new RuntimeException('Supabase did not return a new employee user.');
                }

                $this->upsertEmployeeProfile($userId, $invite, $email, $fullName);
                $this->markInviteAccepted($email);
            } catch (RuntimeException $exception) {
                $this->flash('error', $exception->getMessage());
                $this->renderRegister(['email' => $email, 'full_name' => $fullName, 'invite_token' => $inviteToken]);
                return;
            }

            $this->flash('success', 'Employee account created. If email confirmation is enabled in Supabase, check your inbox first, then sign in.');
            $this->redirect('/login?email=' . rawurlencode($email));
            return;
        }

        $this->renderRegister(['email' => $email, 'full_name' => $fullName, 'invite_token' => $inviteToken]);
    }

    private function recover(string $method): void
    {
        if ($this->employeeSession()) {
            $this->redirect('/dashboard');
            return;
        }

        $email = $this->normalizeEmail($this->post('email') ?? $this->query('email') ?? '');
        if ($method === 'POST') {
            if ($email === '' || !$this->isValidEmail($email)) {
                $this->flash('error', 'Enter a valid work email address first.');
                $this->renderRecover(['email' => $email]);
                return;
            }
            try {
                $this->requestPasswordRecovery($email);
            } catch (RuntimeException $exception) {
                $this->flash('error', $exception->getMessage());
                $this->renderRecover(['email' => $email]);
                return;
            }

            $this->flash('success', 'If that email has dashboard access, Supabase has sent a recovery email.');
            $this->redirect('/login?email=' . rawurlencode($email));
            return;
        }

        $this->renderRecover(['email' => $email]);
    }

    private function logout(): void
    {
        unset($_SESSION['employee']);
        $this->redirect('/login');
    }

    private function dashboardPage(string $page): void
    {
        $employee = $this->requireEmployeeSession(false);
        if (!is_array($employee)) {
            return;
        }

        $meta = [
            'overview' => [
                'title' => 'Dashboard',
                'subtitle' => 'Stay on top of live bookings, fleet setup, employee access, and launch readiness in one place.',
            ],
            'bookings' => [
                'title' => 'Bookings',
                'subtitle' => 'Review current reservations, search customers, and update payment or confirmation status.',
            ],
            'availability' => [
                'title' => 'Availability',
                'subtitle' => 'Track when each car is free or booked with weekly and monthly calendar views.',
            ],
            'fleet' => [
                'title' => 'Fleet',
                'subtitle' => 'Manage the cars, locations, and pricing tied to this business in Supabase.',
            ],
        ];

        $pageMeta = $meta[$page] ?? $meta['overview'];
        $this->render('dashboard.php', [
            'page' => $page,
            'pageTitle' => $pageMeta['title'],
            'pageSubtitle' => $pageMeta['subtitle'],
            'employee' => $employee,
            'employeeInitials' => $this->employeeInitials($employee),
            'canManageAccess' => $this->employeeCanManageAccess($employee),
            'canCustomize' => $this->employeeCanManageAccess($employee),
            'registerUrl' => $this->absoluteUrl('/register'),
            'upgradeUrl' => $this->customDomainUpgradeUrl,
        ]);
    }

    private function apiDashboardBookings(): void
    {
        $employee = $this->requireEmployeeSession(true);
        if (!is_array($employee)) {
            return;
        }
        $bizId = $this->text($employee['biz_id'] ?? '');
        if ($bizId === '') {
            $this->json(['ok' => false, 'error' => 'This employee account does not have a biz_id.'], 400);
            return;
        }

        try {
            [$items, $warning] = $this->fetchBookingsForDashboard($bizId);
            $filtered = $this->applyDashboardFilters($items);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 500);
            return;
        }

        $models = [];
        foreach ($items as $item) {
            $model = $this->text($item['car_model'] ?? '');
            if ($model !== '') {
                $models[$model] = true;
            }
        }
        ksort($models);

        $this->json([
            'ok' => true,
            'items' => $this->serializeItems($filtered),
            'models' => array_keys($models),
            'summary' => $this->summarizeItems($filtered),
            'warning' => $warning,
        ]);
    }

    private function apiDashboardAvailability(): void
    {
        $employee = $this->requireEmployeeSession(true);
        if (!is_array($employee)) {
            return;
        }
        $bizId = $this->text($employee['biz_id'] ?? '');
        if ($bizId === '') {
            $this->json(['ok' => false, 'error' => 'This employee account does not have a biz_id.'], 400);
            return;
        }
        try {
            [$payload, $warning] = $this->buildAvailabilityPayload($bizId);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 500);
            return;
        }

        $this->json(['ok' => true, 'warning' => $warning] + $payload);
    }

    private function apiDashboardFleet(string $method): void
    {
        $employee = $this->requireEmployeeSession(true);
        if (!is_array($employee)) {
            return;
        }
        $bizId = $this->text($employee['biz_id'] ?? '');
        if ($bizId === '') {
            $this->json(['ok' => false, 'error' => 'This employee account does not have a biz_id.'], 400);
            return;
        }

        if ($method === 'GET') {
            try {
                $items = $this->fetchFleetForManagement($bizId);
            } catch (RuntimeException $exception) {
                $this->json(['ok' => false, 'error' => $exception->getMessage()], 500);
                return;
            }
            $pricedCount = 0;
            foreach ($items as $item) {
                if ((float) ($item['price_per_day'] ?? 0) > 0
                    && (float) ($item['price_per_week'] ?? 0) > 0
                    && (float) ($item['price_per_month'] ?? 0) > 0
                ) {
                    $pricedCount += 1;
                }
            }
            $this->json([
                'ok' => true,
                'items' => $items,
                'summary' => [
                    'fleet_count' => count($items),
                    'priced_count' => $pricedCount,
                ],
            ]);
            return;
        }

        if (!$this->employeeCanManageAccess($employee)) {
            $this->json(['ok' => false, 'error' => 'Only admins can update fleet details.'], 403);
            return;
        }

        $body = $this->jsonBody();
        try {
            $item = $this->saveFleetVehicle($bizId, is_array($body) ? $body : []);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
            return;
        }

        $this->json(['ok' => true, 'item' => $item]);
    }

    private function apiDashboardFleetImport(): void
    {
        $employee = $this->requireEmployeeSession(true);
        if (!is_array($employee)) {
            return;
        }
        $bizId = $this->text($employee['biz_id'] ?? '');
        if ($bizId === '') {
            $this->json(['ok' => false, 'error' => 'This employee account does not have a biz_id.'], 400);
            return;
        }
        if (!$this->employeeCanManageAccess($employee)) {
            $this->json(['ok' => false, 'error' => 'Only admins can import fleet details.'], 403);
            return;
        }

        $body = $this->jsonBody();
        $rows = is_array($body['items'] ?? null) ? $body['items'] : [];
        $replaceExisting = $this->coerceBool($body['replace_existing'] ?? false);

        try {
            $items = $this->importFleetRecords($bizId, $rows, $replaceExisting);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
            return;
        }

        $this->json(['ok' => true, 'items' => $items, 'imported' => count($items)]);
    }

    private function apiDashboardDeleteFleet(string $fleetId): void
    {
        $employee = $this->requireEmployeeSession(true);
        if (!is_array($employee)) {
            return;
        }
        $bizId = $this->text($employee['biz_id'] ?? '');
        if ($bizId === '') {
            $this->json(['ok' => false, 'error' => 'This employee account does not have a biz_id.'], 400);
            return;
        }
        if (!$this->employeeCanManageAccess($employee)) {
            $this->json(['ok' => false, 'error' => 'Only admins can remove fleet details.'], 403);
            return;
        }

        try {
            $this->deleteFleetVehicle($bizId, $fleetId);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
            return;
        }

        $this->json(['ok' => true]);
    }

    private function apiDashboardCustomization(string $method): void
    {
        $employee = $this->requireEmployeeSession(true);
        if (!is_array($employee)) {
            return;
        }
        $bizId = $this->text($employee['biz_id'] ?? '');
        if ($bizId === '') {
            $this->json(['ok' => false, 'error' => 'This employee account does not have a biz_id.'], 400);
            return;
        }

        $business = $this->fetchBusinessRowForCustomization($bizId, '');
        if ($method === 'GET') {
            $customization = $this->getDashboardCustomization(
                $bizId,
                (string) ($business['slug'] ?? ''),
                (string) ($business['biz_name'] ?? ($employee['company_name'] ?? '')),
                (string) ($business['brand_wordmark'] ?? ''),
                (string) ($business['brand_color'] ?? ''),
                (string) ($business['booking_mode'] ?? ($business['business_type'] ?? ''))
            );
            $this->json(['ok' => true, 'customization' => $customization]);
            return;
        }

        if (!$this->employeeCanManageAccess($employee)) {
            $this->json(['ok' => false, 'error' => 'Only managers can update customization.'], 403);
            return;
        }

        $body = $this->jsonBody();
        try {
            $customization = $this->saveDashboardCustomization(
                $bizId,
                (string) ($body['company_name'] ?? ''),
                (string) ($body['accent'] ?? ''),
                (string) ($body['heading_font'] ?? ''),
                (string) ($body['body_font'] ?? ''),
                (string) ($business['slug'] ?? ''),
                (string) (($body['brand_wordmark'] ?? '') ?: ($body['company_name'] ?? '')),
                (string) ($business['booking_mode'] ?? ($business['business_type'] ?? '')),
                $body['bot_fields'] ?? null,
                (string) ($body['logo_url'] ?? ''),
                $body['discount_type'] ?? 'none',
                $body['discount_value'] ?? 0,
                (string) ($body['discount_label'] ?? ''),
                $body['custom_domain_requested'] ?? false,
                $body['demo_completed'] ?? false,
                $body['launch_site'] ?? false,
                (string) ($employee['email'] ?? ''),
                (string) ($employee['full_name'] ?? '')
            );
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
            return;
        }

        $_SESSION['employee'] = $employee + ['company_name' => (string) ($customization['company_name'] ?? '')];
        $this->json(['ok' => true, 'customization' => $customization]);
    }

    private function apiUpdateBookingState(string $bookingId): void
    {
        $employee = $this->requireEmployeeSession(true);
        if (!is_array($employee)) {
            return;
        }
        $bizId = $this->text($employee['biz_id'] ?? '');
        if ($bizId === '' || $this->text($bookingId) === '') {
            $this->json(['ok' => false, 'error' => 'Missing booking or company information.'], 400);
            return;
        }

        $body = $this->jsonBody();
        $payload = [
            'booking_id' => $bookingId,
            'biz_id' => $bizId,
            'updated_by' => (string) (($employee['email'] ?? '') ?: ($employee['full_name'] ?? 'employee')),
        ];
        if (array_key_exists('is_confirmed', $body)) {
            $payload['is_confirmed'] = $this->coerceBool($body['is_confirmed']);
        }
        if (array_key_exists('payment_status', $body)) {
            $payload['payment_status'] = $this->normalizePaymentStatus($body['payment_status']);
        }
        if (count($payload) === 3) {
            $this->json(['ok' => false, 'error' => 'No state changes were provided.'], 400);
            return;
        }

        try {
            $rows = $this->supabaseRestRequest(
                'POST',
                'booking_admin_states',
                ['on_conflict' => 'booking_id,biz_id'],
                $payload,
                'resolution=merge-duplicates,return=representation'
            );
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 500);
            return;
        }

        $row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : $payload;
        $this->json([
            'ok' => true,
            'state' => [
                'booking_id' => $bookingId,
                'payment_status' => $this->normalizePaymentStatus($row['payment_status'] ?? ''),
                'is_confirmed' => $this->coerceBool($row['is_confirmed'] ?? false),
            ],
        ]);
    }

    private function apiCreateEmployeeInvite(): void
    {
        $employee = $this->requireEmployeeSession(true);
        if (!is_array($employee)) {
            return;
        }
        $actorRole = $this->normalizeRole($employee['role'] ?? '');
        if (!$this->employeeCanManageAccess($employee)) {
            $this->json(['ok' => false, 'error' => 'Only managers can create dashboard access.'], 403);
            return;
        }

        $bizId = $this->text($employee['biz_id'] ?? '');
        $companyName = $this->text($employee['company_name'] ?? '');
        $body = $this->jsonBody();
        $email = $this->normalizeEmail($body['email'] ?? '');
        $fullName = $this->text($body['full_name'] ?? '');
        $role = $this->normalizeRole($body['role'] ?? '');

        if ($bizId === '') {
            $this->json(['ok' => false, 'error' => 'This employee account does not have a biz_id.'], 400);
            return;
        }
        if ($fullName === '') {
            $this->json(['ok' => false, 'error' => "Add the employee's full name."], 400);
            return;
        }
        if ($email === '' || !$this->isValidEmail($email)) {
            $this->json(['ok' => false, 'error' => 'Add a valid work email address.'], 400);
            return;
        }
        if (!$this->canAssignRole($actorRole, $role)) {
            $this->json(['ok' => false, 'error' => 'Your role cannot create that level of dashboard access.'], 403);
            return;
        }

        try {
            $existingProfile = $this->fetchEmployeeProfileByEmail($email);
            if ($existingProfile && $this->coerceBool($existingProfile['is_active'] ?? true)) {
                $this->json(['ok' => false, 'error' => 'That email already has an employee dashboard account.'], 409);
                return;
            }

            $inviteToken = $this->generateInviteToken();
            $rows = $this->supabaseRestRequest(
                'POST',
                'employee_invites',
                ['on_conflict' => 'email'],
                [
                    'email' => $email,
                    'biz_id' => $bizId,
                    'company_name' => $companyName,
                    'full_name' => $fullName,
                    'role' => $role,
                    'is_active' => true,
                    'invited_by' => (string) (($employee['email'] ?? '') ?: ($employee['full_name'] ?? 'manager')),
                    'invite_token' => $inviteToken,
                ],
                'resolution=merge-duplicates,return=representation'
            );
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 500);
            return;
        }

        $row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];
        $inviteEmail = (string) ($row['email'] ?? $email);
        $token = (string) ($row['invite_token'] ?? $inviteToken);
        $this->json([
            'ok' => true,
            'invite' => [
                'email' => $inviteEmail,
                'full_name' => (string) ($row['full_name'] ?? $fullName),
                'role' => $this->normalizeRole($row['role'] ?? $role),
            ],
            'register_url' => $this->absoluteUrl('/register?email=' . rawurlencode($inviteEmail) . '&token=' . rawurlencode($token)),
        ]);
    }

    private function renderLogin(array $data): void
    {
        $this->render('login.php', $data);
    }

    private function renderRegister(array $data): void
    {
        $this->render('register.php', $data);
    }

    private function renderRecover(array $data): void
    {
        $this->render('recover.php', $data);
    }

    private function render(string $template, array $data = []): void
    {
        $flashes = $this->consumeFlashMessages();
        $h = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $url = static fn (string $path): string => $path;
        extract($data, EXTR_SKIP);
        require $this->viewsDir . DIRECTORY_SEPARATOR . $template;
    }

    private function employeeSession(): ?array
    {
        return isset($_SESSION['employee']) && is_array($_SESSION['employee']) ? $_SESSION['employee'] : null;
    }

    private function requireEmployeeSession(bool $json): ?array
    {
        $employee = $this->employeeSession();
        if ($employee) {
            return $employee;
        }
        if ($json) {
            $this->json([
                'ok' => false,
                'error' => 'Your dashboard session has expired. Please sign in again.',
                'redirect_to' => '/login',
            ], 401);
            return null;
        }
        $this->redirect('/login');
        return null;
    }

    private function query(string $key, string $default = ''): string
    {
        return isset($_GET[$key]) ? (string) $_GET[$key] : $default;
    }

    private function post(string $key, ?string $default = ''): ?string
    {
        return isset($_POST[$key]) ? (string) $_POST[$key] : $default;
    }

    private function requestValue(string $key): string
    {
        if (isset($_POST[$key])) {
            return (string) $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return (string) $_GET[$key];
        }
        return '';
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function flash(string $category, string $message): void
    {
        $_SESSION['_flashes'] ??= [];
        $_SESSION['_flashes'][] = ['category' => $category, 'message' => $message];
    }

    private function consumeFlashMessages(): array
    {
        $messages = $_SESSION['_flashes'] ?? [];
        unset($_SESSION['_flashes']);
        return is_array($messages) ? $messages : [];
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
        exit;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8600';
        return $scheme . '://' . $host . $path;
    }

    private function loadSecrets(): array
    {
        $path = dirname($this->rootDir) . DIRECTORY_SEPARATOR . 'secrets.toml';
        if (!is_file($path)) {
            return [];
        }
        $rows = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($rows)) {
            return [];
        }
        $values = [];
        foreach ($rows as $row) {
            $line = trim($row);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"' ");
            $values[$key] = $value;
        }
        return $values;
    }

    private function envValue(string $key): ?string
    {
        $value = getenv($key);
        if (is_string($value) && $value !== '') {
            return $value;
        }
        $secrets = $this->loadSecrets();
        return isset($secrets[$key]) && $secrets[$key] !== '' ? (string) $secrets[$key] : null;
    }

    private function getSupabaseUrl(): ?string
    {
        return $this->envValue('SUPABASE_URL');
    }

    private function getSupabaseServiceKey(): ?string
    {
        return $this->envValue('SUPABASE_SERVICE_KEY') ?? $this->envValue('SUPABASE_KEY');
    }

    private function getSupabasePublicKey(): ?string
    {
        return $this->envValue('SUPABASE_PUBLISHABLE_KEY')
            ?? $this->envValue('SUPABASE_ANON_KEY')
            ?? $this->getSupabaseServiceKey();
    }

    private function supabaseRestRequest(
        string $method,
        string $table,
        array $params = [],
        mixed $payload = null,
        ?string $prefer = null
    ): mixed {
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            throw new RuntimeException('Supabase is not configured for the employee dashboard. Set SUPABASE_URL and SUPABASE_SERVICE_KEY.');
        }

        $url = rtrim($supabaseUrl, '/') . '/rest/v1/' . $table;
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
        ];
        if ($prefer !== null && $prefer !== '') {
            $headers[] = 'Prefer: ' . $prefer;
        }

        $response = $this->httpRequest($method, $url, $headers, $payload);
        if (!in_array($response['status'], [200, 201, 204], true)) {
            $detail = $this->extractErrorMessage($response['body'], 'Unknown error');
            throw new RuntimeException("Supabase request to '{$table}' failed with {$response['status']}: {$detail}");
        }
        return $response['body'];
    }

    private function supabaseAuthRequest(string $method, string $path, ?array $payload = null, bool $admin = false): mixed
    {
        $supabaseUrl = $this->getSupabaseUrl();
        if ($supabaseUrl === null) {
            throw new RuntimeException('Supabase is not configured for the employee dashboard. Set SUPABASE_URL and the required auth keys.');
        }

        $key = $admin ? $this->getSupabaseServiceKey() : $this->getSupabasePublicKey();
        if ($key === null) {
            throw new RuntimeException('Supabase auth is missing an API key. Set SUPABASE_ANON_KEY (or SUPABASE_PUBLISHABLE_KEY) and SUPABASE_SERVICE_KEY.');
        }

        $url = rtrim($supabaseUrl, '/') . '/auth/v1/' . ltrim($path, '/');
        $headers = [
            'apikey: ' . $key,
            'Content-Type: application/json',
        ];
        if ($admin) {
            $headers[] = 'Authorization: Bearer ' . $key;
        }

        $response = $this->httpRequest($method, $url, $headers, $payload);
        if (!in_array($response['status'], [200, 201], true)) {
            $detail = $this->extractErrorMessage($response['body'], 'Unknown error');
            throw new RuntimeException("Supabase Auth request to '{$path}' failed with {$response['status']}: {$detail}");
        }
        return $response['body'];
    }

    private function httpRequest(string $method, string $url, array $headers, mixed $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required to reach Supabase.');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $caBundlePath = $this->resolveCaBundlePath();
        if ($caBundlePath !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundlePath);
        }
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($raw === false) {
            throw new RuntimeException('Could not reach Supabase: ' . $error);
        }

        $body = null;
        $trimmed = trim((string) $raw);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            $body = json_last_error() === JSON_ERROR_NONE ? $decoded : $trimmed;
        }

        return ['status' => $status, 'body' => $body];
    }

    private function resolveCaBundlePath(): ?string
    {
        $candidates = [
            getenv('CURL_CA_BUNDLE') ?: null,
            getenv('SSL_CERT_FILE') ?: null,
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            '/etc/ssl/cert.pem',
            '/private/etc/ssl/cert.pem',
            '/opt/homebrew/etc/openssl@3/cert.pem',
            '/usr/local/etc/openssl@3/cert.pem',
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $path = trim($candidate);
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function extractErrorMessage(mixed $payload, string $fallback): string
    {
        if (is_array($payload)) {
            foreach (['msg', 'message', 'error_description', 'error', 'hint'] as $key) {
                if (!empty($payload[$key])) {
                    return (string) $payload[$key];
                }
            }
        }
        if (is_string($payload) && trim($payload) !== '') {
            return trim($payload);
        }
        return $fallback;
    }

    private function normalizeEmail(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizeRole(mixed $value): string
    {
        $role = strtolower(trim((string) $value));
        return in_array($role, self::ASSIGNABLE_ROLES, true) ? $role : 'staff';
    }

    private function isValidEmail(string $value): bool
    {
        return preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $this->normalizeEmail($value)) === 1;
    }

    private function validatePassword(string $password): ?string
    {
        if (strlen($password) < 10) {
            return 'Choose a password with at least 10 characters.';
        }
        if (preg_match('/[A-Za-z]/', $password) !== 1) {
            return 'Choose a password that includes at least one letter.';
        }
        if (preg_match('/\d/', $password) !== 1) {
            return 'Choose a password that includes at least one number.';
        }
        return null;
    }

    private function normalizeLookup(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value))) ?? '';
    }

    private function parseIsoDate(mixed $value): ?DateTimeImmutable
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $slice = substr($text, 0, 10);
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $slice, new DateTimeZone('UTC'));
        return $date instanceof DateTimeImmutable ? $date : null;
    }

    private function todayDate(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function nowIso(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    private function bookingEndDate(?DateTimeImmutable $startDate, ?DateTimeImmutable $endDate): ?DateTimeImmutable
    {
        if ($startDate !== null && $endDate !== null) {
            return $endDate < $startDate ? $startDate : $endDate;
        }
        return $endDate ?? $startDate;
    }

    private function bookingLengthDays(?DateTimeImmutable $startDate, ?DateTimeImmutable $endDate): int
    {
        if ($startDate === null || $endDate === null) {
            return 0;
        }
        return max((int) $startDate->diff($endDate)->format('%a'), 1);
    }

    private function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'y', 'confirmed'], true);
    }

    private function normalizePaymentStatus(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 'Pending';
        }
        $aliases = [
            'paid' => 'Paid',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'partially paid' => 'Partially Paid',
            'partial' => 'Partially Paid',
        ];
        $lower = strtolower($raw);
        return $aliases[$lower] ?? ucwords($raw);
    }

    private function employeeInitials(array $employee): string
    {
        $fullName = trim((string) ($employee['full_name'] ?? ''));
        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $letters = '';
            foreach ($parts as $part) {
                if ($part !== '') {
                    $letters .= strtoupper($part[0]);
                }
                if (strlen($letters) >= 2) {
                    break;
                }
            }
            return $letters !== '' ? $letters : 'EM';
        }
        $email = $this->normalizeEmail((string) ($employee['email'] ?? ''));
        return strtoupper(substr($email !== '' ? $email : 'EM', 0, 2));
    }

    private function employeeCanManageAccess(array $employee): bool
    {
        return in_array($this->normalizeRole($employee['role'] ?? ''), self::ACCESS_MANAGER_ROLES, true);
    }

    private function canAssignRole(string $actorRole, string $targetRole): bool
    {
        $actor = $this->normalizeRole($actorRole);
        $target = $this->normalizeRole($targetRole);
        return match ($actor) {
            'owner' => in_array($target, ['staff', 'manager', 'admin', 'owner'], true),
            'admin' => in_array($target, ['staff', 'manager'], true),
            'manager' => $target === 'staff',
            default => false,
        };
    }

    private function fetchRows(string $table, string $bizId = '', ?string $order = null): array
    {
        $params = ['select' => '*', 'limit' => '1000'];
        if ($bizId !== '') {
            $params['biz_id'] = 'eq.' . $bizId;
        }
        if ($order !== null && $order !== '') {
            $params['order'] = $order;
        }
        $rows = $this->supabaseRestRequest('GET', $table, $params);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function fetchSingleRow(string $table, array $params): ?array
    {
        $rows = $this->supabaseRestRequest('GET', $table, $params + ['limit' => '1']);
        return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
    }

    private function fetchEmployeeProfileByUserId(string $userId): ?array
    {
        if ($userId === '') {
            return null;
        }
        return $this->fetchSingleRow('employee_profiles', [
            'select' => 'id,biz_id,email,full_name,company_name,role,is_active,created_at',
            'id' => 'eq.' . $userId,
        ]);
    }

    private function fetchEmployeeProfileByEmail(string $email): ?array
    {
        $normalized = $this->normalizeEmail($email);
        if ($normalized === '') {
            return null;
        }
        return $this->fetchSingleRow('employee_profiles', [
            'select' => 'id,biz_id,email,full_name,company_name,role,is_active,created_at',
            'email' => 'eq.' . $normalized,
        ]);
    }

    private function fetchEmployeeInvite(string $email, string $token = ''): ?array
    {
        $normalized = $this->normalizeEmail($email);
        $normalizedToken = $this->text($token);
        if ($normalized === '' && $normalizedToken === '') {
            return null;
        }
        $params = [
            'select' => 'email,biz_id,company_name,full_name,role,is_active,accepted_at,invite_token',
        ];
        if ($normalized !== '') {
            $params['email'] = 'eq.' . $normalized;
        }
        if ($normalizedToken !== '') {
            $params['invite_token'] = 'eq.' . $normalizedToken;
        }
        return $this->fetchSingleRow('employee_invites', $params);
    }

    private function upsertEmployeeProfile(string $userId, array $invite, string $email, string $fullName): array
    {
        $payload = [
            'id' => $userId,
            'biz_id' => $this->text($invite['biz_id'] ?? ''),
            'email' => $this->normalizeEmail($email),
            'full_name' => $this->text($fullName) !== '' ? $this->text($fullName) : $this->text($invite['full_name'] ?? ''),
            'company_name' => $this->text($invite['company_name'] ?? ''),
            'role' => $this->normalizeRole($invite['role'] ?? ''),
            'is_active' => $this->coerceBool($invite['is_active'] ?? true),
            'invited_at' => $invite['accepted_at'] ?? $this->nowIso(),
            'updated_at' => $this->nowIso(),
        ];
        $rows = $this->supabaseRestRequest(
            'POST',
            'employee_profiles',
            ['on_conflict' => 'id'],
            $payload,
            'resolution=merge-duplicates,return=representation'
        );
        return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : $payload;
    }

    private function markInviteAccepted(string $email): void
    {
        $this->supabaseRestRequest(
            'PATCH',
            'employee_invites',
            ['email' => 'eq.' . $this->normalizeEmail($email)],
            ['accepted_at' => $this->nowIso(), 'is_active' => true],
            'return=representation'
        );
    }

    private function signUpEmployee(string $email, string $password, string $fullName, string $companyName): array
    {
        $data = $this->supabaseAuthRequest('POST', 'signup', [
            'email' => $this->normalizeEmail($email),
            'password' => $password,
            'data' => [
                'full_name' => trim($fullName),
                'company_name' => trim($companyName),
            ],
        ]);
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['user']) && is_array($data['user'])) {
            return $data;
        }
        if (!empty($data['id'])) {
            return ['user' => $data, 'session' => $data['session'] ?? null];
        }
        return $data;
    }

    private function signInEmployee(string $email, string $password): array
    {
        $data = $this->supabaseAuthRequest(
            'POST',
            'token?grant_type=password',
            ['email' => $this->normalizeEmail($email), 'password' => $password]
        );
        return is_array($data) ? $data : [];
    }

    private function requestPasswordRecovery(string $email): void
    {
        $this->supabaseAuthRequest('POST', 'recover', ['email' => $this->normalizeEmail($email)]);
    }

    private function buildEmployeeSession(array $profile, ?array $authUser = null): array
    {
        $email = $this->normalizeEmail((string) (($authUser['email'] ?? '') ?: ($profile['email'] ?? '')));
        return [
            'id' => (string) ($profile['id'] ?? ''),
            'biz_id' => (string) ($profile['biz_id'] ?? ''),
            'email' => $email,
            'full_name' => $this->text($profile['full_name'] ?? ''),
            'company_name' => $this->text($profile['company_name'] ?? ''),
            'role' => $this->normalizeRole($profile['role'] ?? ''),
        ];
    }

    private function ensureProfileFromInvite(array $authUser, string $email): ?array
    {
        $userId = $this->text($authUser['id'] ?? '');
        $normalizedEmail = $this->normalizeEmail($email);
        if ($userId === '' || $normalizedEmail === '') {
            return null;
        }
        $invite = $this->fetchEmployeeInvite($normalizedEmail);
        if (!$invite || !$this->coerceBool($invite['is_active'] ?? true)) {
            return null;
        }
        $metadata = is_array($authUser['user_metadata'] ?? null) ? $authUser['user_metadata'] : [];
        $fullName = $this->text($metadata['full_name'] ?? '') ?: $this->text($invite['full_name'] ?? '') ?: $normalizedEmail;
        return $this->upsertEmployeeProfile($userId, $invite, $normalizedEmail, $fullName);
    }

    private function fetchBusinessRowForCustomization(string $bizId, string $slug): ?array
    {
        $bizId = $this->text($bizId);
        $slug = strtolower($this->text($slug));
        if ($bizId === '' && $slug === '') {
            return null;
        }
        $params = [
            'select' => 'biz_id,slug,biz_name,brand_color,primary_color,secondary_color,brand_wordmark,booking_mode,business_type,logo_url,discount_type,discount_value,discount_label,custom_domain_requested,demo_completed,setup_completed,site_enabled,owner_email,owner_name',
            'limit' => '1',
        ];
        if ($bizId !== '') {
            $params['biz_id'] = 'eq.' . $bizId;
        } else {
            $params['slug'] = 'eq.' . $slug;
        }
        try {
            $rows = $this->supabaseRestRequest('GET', 'businesses', $params);
        } catch (RuntimeException) {
            return null;
        }
        return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
    }

    private function loadBrandingStore(): array
    {
        if (!is_file($this->brandingStorePath)) {
            return ['businesses' => []];
        }
        $raw = file_get_contents($this->brandingStorePath);
        if (!is_string($raw) || trim($raw) === '') {
            return ['businesses' => []];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !is_array($decoded['businesses'] ?? null)) {
            return ['businesses' => []];
        }
        return ['businesses' => $decoded['businesses']];
    }

    private function saveBrandingStore(array $store): void
    {
        $payload = ['businesses' => is_array($store['businesses'] ?? null) ? $store['businesses'] : []];
        file_put_contents($this->brandingStorePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function buildBookingLink(string $slug): string
    {
        $normalized = strtolower($this->text($slug));
        return $normalized === '' ? '' : 'https://' . $normalized . '.yobobot.in';
    }

    private function generateInviteToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function discountTypeOptions(): array
    {
        return ['none', 'percentage', 'fixed'];
    }

    private function normalizeDiscountType(mixed $value): string
    {
        $normalized = strtolower(trim((string) ($value ?: 'none')));
        return in_array($normalized, $this->discountTypeOptions(), true) ? $normalized : 'none';
    }

    private function normalizeDiscountValue(mixed $value): float
    {
        $number = is_numeric($value) ? (float) $value : 0.0;
        return max(0.0, round($number, 2));
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeHexColor(mixed $value, string $fallback = '#72a9ff'): string
    {
        $candidate = strtolower(trim((string) $value));
        if (preg_match('/^#[0-9a-f]{6}$/', $candidate) === 1) {
            return $candidate;
        }
        if (preg_match('/^[0-9a-f]{6}$/', $candidate) === 1) {
            return '#' . $candidate;
        }
        return $fallback;
    }

    private function mixHexColor(string $color, string $target, float $ratio): string
    {
        $ratio = max(0.0, min(1.0, $ratio));
        $source = ltrim($this->normalizeHexColor($color), '#');
        $blend = ltrim($this->normalizeHexColor($target, '#ffffff'), '#');
        $channels = [];
        foreach ([0, 2, 4] as $index) {
            $sourceValue = hexdec(substr($source, $index, 2));
            $blendValue = hexdec(substr($blend, $index, 2));
            $mixed = (int) round($sourceValue * (1 - $ratio) + $blendValue * $ratio);
            $channels[] = str_pad(dechex($mixed), 2, '0', STR_PAD_LEFT);
        }
        return '#' . implode('', $channels);
    }

    private function normalizeFontChoice(mixed $value, string $fallback): string
    {
        $selected = strtolower(trim((string) $value));
        return array_key_exists($selected, self::FONT_CHOICES) ? $selected : $fallback;
    }

    private function fontCssValue(mixed $fontId, string $fallback = self::DEFAULT_BODY_FONT): string
    {
        $normalized = $this->normalizeFontChoice($fontId, $fallback);
        return (string) self::FONT_CHOICES[$normalized]['css'];
    }

    private function fontChoiceOptions(): array
    {
        $options = [];
        foreach (self::FONT_CHOICES as $id => $meta) {
            $options[] = ['id' => $id, 'label' => (string) $meta['label'], 'css' => (string) $meta['css']];
        }
        return $options;
    }

    private function normalizeBookingMode(mixed $value): string
    {
        $candidate = strtolower(trim((string) $value));
        return in_array($candidate, ['service', 'car_workshop'], true) ? 'service' : 'rental';
    }

    private function botFieldCatalog(mixed $bookingMode): array
    {
        $mode = $this->normalizeBookingMode($bookingMode);
        return array_values(array_filter(
            self::BOT_INTAKE_FIELDS,
            static fn (array $field): bool => in_array($mode, $field['modes'], true)
        ));
    }

    private function defaultBotFieldKeys(mixed $bookingMode): array
    {
        $mode = $this->normalizeBookingMode($bookingMode);
        $keys = [];
        foreach ($this->botFieldCatalog($mode) as $field) {
            if (!empty($field['default'][$mode])) {
                $keys[] = $field['key'];
            }
        }
        return $keys;
    }

    private function normalizeBotFieldSelection(mixed $values, mixed $bookingMode): array
    {
        $catalog = $this->botFieldCatalog($bookingMode);
        $catalogByKey = [];
        $orderedKeys = [];
        foreach ($catalog as $field) {
            $catalogByKey[$field['key']] = $field;
            $orderedKeys[] = $field['key'];
        }
        $selectedMap = [];
        foreach (is_array($values) ? $values : [] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $key = $this->text($raw['key'] ?? '');
            if ($key === '' || !isset($catalogByKey[$key]) || isset($selectedMap[$key])) {
                continue;
            }
            if (!$this->coerceBool($raw['enabled'] ?? true)) {
                continue;
            }
            $mode = $this->normalizeBookingMode($bookingMode);
            $label = $this->text($raw['label'] ?? '');
            if ($label === '') {
                $label = (string) $catalogByKey[$key]['labels'][$mode];
            }
            $selectedMap[$key] = $label;
        }

        $selected = [];
        foreach ($orderedKeys as $key) {
            if (isset($selectedMap[$key])) {
                $selected[] = ['key' => $key, 'label' => $selectedMap[$key]];
            }
        }
        return $selected;
    }

    private function fetchBusinessBookingFieldRows(string $bizId, bool $includeDisabled = false): array
    {
        if ($bizId === '') {
            return [];
        }
        try {
            $rows = $this->supabaseRestRequest('GET', 'business_booking_fields', [
                'select' => 'field_key,field_label,field_order,is_enabled,is_required',
                'biz_id' => 'eq.' . $bizId,
                'order' => 'field_order.asc',
            ]);
        } catch (RuntimeException) {
            return [];
        }
        if (!is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($includeDisabled || $this->coerceBool($row['is_enabled'] ?? true)) {
                $result[] = $row;
            }
        }
        return $result;
    }

    private function getBotIntakeConfiguration(string $bizId, mixed $bookingMode): array
    {
        $mode = $this->normalizeBookingMode($bookingMode);
        $catalog = $this->botFieldCatalog($mode);
        $defaultLabels = [];
        foreach ($catalog as $field) {
            $defaultLabels[$field['key']] = (string) $field['labels'][$mode];
        }

        $rows = $this->fetchBusinessBookingFieldRows($bizId, true);
        $selectedLabels = [];
        if ($rows !== []) {
            foreach ($rows as $row) {
                $key = $this->text($row['field_key'] ?? '');
                if ($key === self::BOT_FIELDS_EMPTY_SENTINEL) {
                    continue;
                }
                if (!$this->coerceBool($row['is_enabled'] ?? true) || !isset($defaultLabels[$key])) {
                    continue;
                }
                $selectedLabels[$key] = $this->text($row['field_label'] ?? '') ?: $defaultLabels[$key];
            }
        } else {
            foreach ($this->defaultBotFieldKeys($mode) as $key) {
                $selectedLabels[$key] = $defaultLabels[$key];
            }
        }

        $optionalFields = [];
        foreach ($catalog as $field) {
            $key = $field['key'];
            $optionalFields[] = [
                'key' => $key,
                'stage' => $field['stage'],
                'default_label' => $defaultLabels[$key],
                'label' => $selectedLabels[$key] ?? $defaultLabels[$key],
                'description' => (string) ($field['description'] ?? ''),
                'enabled' => isset($selectedLabels[$key]),
            ];
        }

        $optionalCount = count(array_filter($optionalFields, static fn (array $field): bool => !empty($field['enabled'])));
        $alwaysCount = count(self::BOT_ALWAYS_COLLECTED_FIELDS);
        return [
            'always_collected' => self::BOT_ALWAYS_COLLECTED_FIELDS,
            'optional_fields' => $optionalFields,
            'max_total_questions' => self::BOT_QUESTION_LIMIT,
            'selected_optional_count' => $optionalCount,
            'always_collected_count' => $alwaysCount,
            'total_question_count' => $alwaysCount + $optionalCount,
            'booking_mode' => $mode,
        ];
    }

    private function saveBotIntakeConfiguration(string $bizId, mixed $bookingMode, mixed $fields): array
    {
        if ($bizId === '') {
            throw new RuntimeException('This employee account does not have a biz_id.');
        }
        $mode = $this->normalizeBookingMode($bookingMode);
        $selectedFields = $this->normalizeBotFieldSelection($fields, $mode);
        $totalCount = count(self::BOT_ALWAYS_COLLECTED_FIELDS) + count($selectedFields);
        if ($totalCount > self::BOT_QUESTION_LIMIT) {
            throw new RuntimeException('Choose at most 10 total bot questions, including full name and phone.');
        }

        $this->supabaseRestRequest('DELETE', 'business_booking_fields', ['biz_id' => 'eq.' . $bizId]);
        if ($selectedFields !== []) {
            $payload = [];
            foreach ($selectedFields as $index => $field) {
                $payload[] = [
                    'biz_id' => $bizId,
                    'field_key' => $field['key'],
                    'field_label' => $field['label'],
                    'field_order' => $index,
                    'is_enabled' => true,
                    'is_required' => false,
                ];
            }
            $this->supabaseRestRequest('POST', 'business_booking_fields', [], $payload, 'return=representation');
        } else {
            $this->supabaseRestRequest('POST', 'business_booking_fields', [], [[
                'biz_id' => $bizId,
                'field_key' => self::BOT_FIELDS_EMPTY_SENTINEL,
                'field_label' => '',
                'field_order' => 0,
                'is_enabled' => false,
                'is_required' => false,
            ]], 'return=representation');
        }

        return $this->getBotIntakeConfiguration($bizId, $mode);
    }

    private function prepareFleetPayload(array $payload): array
    {
        $branchValue = $this->text(
            $payload['branch_location']
            ?? $payload['city_branch']
            ?? $payload['location']
            ?? $payload['branch_city']
            ?? $payload['city']
            ?? ''
        );
        return [
            'name' => $this->text($payload['name'] ?? ''),
            'make' => $this->text($payload['make'] ?? ''),
            'model' => $this->text($payload['model'] ?? ''),
            'color' => $this->text($payload['color'] ?? ''),
            'number_plate' => $this->text($payload['number_plate'] ?? ''),
            'photo_url' => $this->text($payload['photo_url'] ?? ''),
            'category' => $this->text($payload['category'] ?? ''),
            'city' => $this->text(($payload['city'] ?? '') ?: $branchValue),
            'branch_city' => $this->text(($payload['branch_city'] ?? '') ?: $branchValue),
            'branch_location' => $branchValue,
            'location' => $branchValue,
            'price_per_day' => $this->normalizeDiscountValue($payload['price_per_day'] ?? 0),
            'price_per_week' => $this->normalizeDiscountValue($payload['price_per_week'] ?? 0),
            'price_per_month' => $this->normalizeDiscountValue($payload['price_per_month'] ?? 0),
            'available' => $this->coerceBool($payload['available'] ?? true),
        ];
    }

    private function serializeFleetRecord(array $row): array
    {
        return [
            'id' => $this->text($row['id'] ?? ''),
            'name' => $this->text($row['name'] ?? ''),
            'make' => $this->text($row['make'] ?? ''),
            'model' => $this->text($row['model'] ?? ''),
            'color' => $this->text($row['color'] ?? ''),
            'number_plate' => $this->text($row['number_plate'] ?? ''),
            'photo_url' => $this->text($row['photo_url'] ?? ''),
            'category' => $this->text($row['category'] ?? ''),
            'city' => $this->text(($row['city'] ?? '') ?: ($row['branch_city'] ?? '') ?: ($row['location'] ?? '')),
            'branch_city' => $this->text(($row['branch_city'] ?? '') ?: ($row['city'] ?? '') ?: ($row['location'] ?? '')),
            'branch_location' => $this->text(($row['branch_location'] ?? '') ?: ($row['location'] ?? '') ?: ($row['city'] ?? '')),
            'location' => $this->text(($row['location'] ?? '') ?: ($row['branch_location'] ?? '') ?: ($row['city'] ?? '')),
            'price_per_day' => $this->normalizeDiscountValue($row['price_per_day'] ?? 0),
            'price_per_week' => $this->normalizeDiscountValue($row['price_per_week'] ?? 0),
            'price_per_month' => $this->normalizeDiscountValue($row['price_per_month'] ?? 0),
            'available' => $this->coerceBool($row['available'] ?? true),
        ];
    }

    private function fetchFleetForManagement(string $bizId): array
    {
        $rows = $this->fetchRows('fleet', $bizId, 'created_at.desc');
        return array_map(fn (array $row): array => $this->serializeFleetRecord($row), $rows);
    }

    private function fleetSetupSummary(string $bizId): array
    {
        try {
            $fleetRows = $this->fetchFleetForManagement($bizId);
        } catch (RuntimeException) {
            $fleetRows = [];
        }
        $pricingComplete = $fleetRows !== [];
        foreach ($fleetRows as $item) {
            if ((float) ($item['price_per_day'] ?? 0) <= 0
                || (float) ($item['price_per_week'] ?? 0) <= 0
                || (float) ($item['price_per_month'] ?? 0) <= 0
            ) {
                $pricingComplete = false;
                break;
            }
        }
        return [
            'fleet_count' => count($fleetRows),
            'pricing_complete' => $pricingComplete,
            'fleet_ready' => $fleetRows !== [],
            'items' => $fleetRows,
        ];
    }

    private function countBusinessSiteVisits(string $bizId): int
    {
        try {
            return count($this->fetchRows('business_site_visits', $bizId, 'created_at.desc'));
        } catch (RuntimeException) {
            return 0;
        }
    }

    private function businessBookingCount(string $bizId): int
    {
        try {
            return count($this->fetchRows('bookings', $bizId, 'created_at.desc'));
        } catch (RuntimeException) {
            return 0;
        }
    }

    private function setupStatusSnapshot(
        string $bizId,
        string $companyName,
        string $logoUrl,
        bool $demoCompleted,
        bool $customDomainRequested,
        bool $siteEnabled
    ): array {
        $fleetSummary = $this->fleetSetupSummary($bizId);
        $brandingComplete = $this->text($companyName) !== '' && $this->text($logoUrl) !== '';
        $readyForLaunch = $brandingComplete
            && !empty($fleetSummary['fleet_ready'])
            && !empty($fleetSummary['pricing_complete'])
            && $demoCompleted;

        return [
            'branding_complete' => $brandingComplete,
            'fleet_ready' => (bool) ($fleetSummary['fleet_ready'] ?? false),
            'pricing_complete' => (bool) ($fleetSummary['pricing_complete'] ?? false),
            'demo_completed' => $demoCompleted,
            'custom_domain_requested' => $customDomainRequested,
            'fleet_count' => (int) ($fleetSummary['fleet_count'] ?? 0),
            'ready_for_launch' => $readyForLaunch,
            'site_enabled' => $siteEnabled,
        ];
    }

    private function saveFleetVehicle(string $bizId, array $payload): array
    {
        if ($bizId === '') {
            throw new RuntimeException('This employee account does not have a biz_id.');
        }
        $vehicleId = $this->text($payload['id'] ?? '');
        $prepared = ['biz_id' => $bizId] + $this->prepareFleetPayload($payload);
        if ($prepared['make'] === '' || $prepared['model'] === '') {
            throw new RuntimeException('Add both the vehicle make and model.');
        }

        if ($vehicleId !== '') {
            $rows = $this->supabaseRestRequest(
                'PATCH',
                'fleet',
                ['id' => 'eq.' . $vehicleId, 'biz_id' => 'eq.' . $bizId],
                $prepared,
                'return=representation'
            );
        } else {
            $rows = $this->supabaseRestRequest('POST', 'fleet', [], $prepared, 'return=representation');
        }
        $row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : ($prepared + ['id' => $vehicleId]);
        return $this->serializeFleetRecord($row);
    }

    private function deleteFleetVehicle(string $bizId, string $vehicleId): void
    {
        if ($bizId === '' || $this->text($vehicleId) === '') {
            throw new RuntimeException('Missing fleet or business information.');
        }
        $this->supabaseRestRequest(
            'DELETE',
            'fleet',
            ['id' => 'eq.' . $vehicleId, 'biz_id' => 'eq.' . $bizId],
            null,
            'return=representation'
        );
    }

    private function importFleetRecords(string $bizId, array $items, bool $replaceExisting = false): array
    {
        if ($bizId === '') {
            throw new RuntimeException('This employee account does not have a biz_id.');
        }
        $preparedRows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ($this->text($item['make'] ?? '') === '' && $this->text($item['model'] ?? '') === '') {
                continue;
            }
            $preparedRows[] = ['biz_id' => $bizId] + $this->prepareFleetPayload($item);
        }
        if ($preparedRows === []) {
            throw new RuntimeException('Upload at least one valid fleet row.');
        }
        if ($replaceExisting) {
            $this->supabaseRestRequest('DELETE', 'fleet', ['biz_id' => 'eq.' . $bizId], null, 'return=representation');
        }
        $rows = $this->supabaseRestRequest('POST', 'fleet', [], $preparedRows, 'return=representation');
        if (!is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $result[] = $this->serializeFleetRecord($row);
            }
        }
        return $result;
    }

    private function getDashboardCustomization(
        string $bizId,
        string $slug = '',
        string $fallbackCompanyName = '',
        string $fallbackWordmark = '',
        string $fallbackAccent = '',
        string $bookingMode = ''
    ): array {
        $store = $this->loadBrandingStore();
        $stored = is_array($store['businesses'][$bizId] ?? null) ? $store['businesses'][$bizId] : [];
        $supabaseRow = $this->fetchBusinessRowForCustomization($bizId, $slug) ?? [];

        $resolvedSlug = strtolower($this->text($stored['slug'] ?? '') ?: $this->text($slug) ?: $this->text($supabaseRow['slug'] ?? ''));
        $companyName = $this->text($stored['company_name'] ?? '')
            ?: $this->text($fallbackCompanyName)
            ?: $this->text($supabaseRow['biz_name'] ?? '')
            ?: 'Your Rental Company';
        $wordmark = $this->text($stored['brand_wordmark'] ?? '')
            ?: $this->text($fallbackWordmark)
            ?: $this->text($supabaseRow['brand_wordmark'] ?? '')
            ?: $companyName;
        $logoUrl = $this->text($supabaseRow['logo_url'] ?? '') ?: $this->text($stored['logo_url'] ?? '');
        $discountType = $this->normalizeDiscountType(($supabaseRow['discount_type'] ?? null) ?: ($stored['discount_type'] ?? null));
        $discountValue = $this->normalizeDiscountValue(($supabaseRow['discount_value'] ?? null) ?: ($stored['discount_value'] ?? null));
        $discountLabel = $this->text(($supabaseRow['discount_label'] ?? '') ?: ($stored['discount_label'] ?? ''));
        if ($discountLabel === '' && $discountType !== 'none' && $discountValue > 0) {
            $discountLabel = 'Seasonal discount';
        }
        $customDomainRequested = $this->coerceBool(($supabaseRow['custom_domain_requested'] ?? null) ?: ($stored['custom_domain_requested'] ?? null));
        $demoCompleted = $this->coerceBool(($supabaseRow['demo_completed'] ?? null) ?: ($stored['demo_completed'] ?? null));
        $siteEnabled = $this->coerceBool(($supabaseRow['site_enabled'] ?? null) ?: ($stored['site_enabled'] ?? null));
        $accent = $this->normalizeHexColor(
            $stored['accent'] ?? $fallbackAccent ?: ($supabaseRow['brand_color'] ?? '') ?: ($supabaseRow['primary_color'] ?? ''),
            '#72a9ff'
        );
        $headingFont = $this->normalizeFontChoice($stored['heading_font'] ?? '', self::DEFAULT_HEADING_FONT);
        $bodyFont = $this->normalizeFontChoice($stored['body_font'] ?? '', self::DEFAULT_BODY_FONT);
        $resolvedBookingMode = $this->normalizeBookingMode(
            $bookingMode ?: ($supabaseRow['booking_mode'] ?? '') ?: ($supabaseRow['business_type'] ?? '')
        );
        $botQuestions = $this->getBotIntakeConfiguration($bizId, $resolvedBookingMode);
        $setupStatus = $this->setupStatusSnapshot(
            $bizId,
            $companyName,
            $logoUrl,
            $demoCompleted,
            $customDomainRequested,
            $siteEnabled
        );

        return [
            'biz_id' => $bizId,
            'slug' => $resolvedSlug,
            'booking_mode' => $resolvedBookingMode,
            'company_name' => $companyName,
            'brand_wordmark' => $wordmark,
            'booking_link' => $siteEnabled ? $this->buildBookingLink($resolvedSlug) : '',
            'booking_link_locked_message' => $siteEnabled ? '' : 'Complete setup and confirm your Yobobot demo before the live booking link is unlocked.',
            'accent' => $accent,
            'soft_accent' => $this->mixHexColor($accent, '#ffffff', 0.75),
            'heading_font' => $headingFont,
            'body_font' => $bodyFont,
            'heading_font_css' => $this->fontCssValue($headingFont, self::DEFAULT_HEADING_FONT),
            'body_font_css' => $this->fontCssValue($bodyFont, self::DEFAULT_BODY_FONT),
            'logo_url' => $logoUrl,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_label' => $discountLabel,
            'custom_domain_requested' => $customDomainRequested,
            'demo_completed' => $demoCompleted,
            'site_enabled' => $siteEnabled,
            'setup_status' => $setupStatus,
            'overview_metrics' => [
                'site_visits' => $this->countBusinessSiteVisits($bizId),
                'booking_count' => $this->businessBookingCount($bizId),
            ],
            'font_choices' => $this->fontChoiceOptions(),
            'bot_questions' => $botQuestions,
            'upgrade_url' => $this->customDomainUpgradeUrl,
            'discount_type_options' => $this->discountTypeOptions(),
        ];
    }

    private function saveDashboardCustomization(
        string $bizId,
        string $companyName,
        string $accent,
        string $headingFont,
        string $bodyFont,
        string $slug = '',
        string $brandWordmark = '',
        string $bookingMode = '',
        mixed $botFields = null,
        string $logoUrl = '',
        mixed $discountType = 'none',
        mixed $discountValue = 0,
        string $discountLabel = '',
        mixed $customDomainRequested = false,
        mixed $demoCompleted = false,
        mixed $launchSite = false,
        string $ownerEmail = '',
        string $ownerName = ''
    ): array {
        if ($bizId === '') {
            throw new RuntimeException('This employee account does not have a biz_id.');
        }
        $trimmedName = $this->text($companyName);
        if ($trimmedName === '') {
            throw new RuntimeException('Enter the business name before saving customization.');
        }

        $store = $this->loadBrandingStore();
        $store['businesses'] ??= [];
        if (!is_array($store['businesses'])) {
            $store['businesses'] = [];
        }

        $current = $this->getDashboardCustomization(
            $bizId,
            $slug,
            $trimmedName,
            $brandWordmark !== '' ? $brandWordmark : $trimmedName,
            $accent,
            $bookingMode
        );

        $store['businesses'][$bizId] = [
            'company_name' => $trimmedName,
            'brand_wordmark' => trim($brandWordmark !== '' ? $brandWordmark : $trimmedName),
            'slug' => strtolower($this->text($current['slug'] ?? $slug)),
            'accent' => $this->normalizeHexColor($accent, (string) ($current['accent'] ?? '#72a9ff')),
            'heading_font' => $this->normalizeFontChoice($headingFont, self::DEFAULT_HEADING_FONT),
            'body_font' => $this->normalizeFontChoice($bodyFont, self::DEFAULT_BODY_FONT),
            'logo_url' => $this->text($logoUrl),
            'discount_type' => $this->normalizeDiscountType($discountType),
            'discount_value' => $this->normalizeDiscountValue($discountValue),
            'discount_label' => $this->text($discountLabel),
            'custom_domain_requested' => $this->coerceBool($customDomainRequested),
            'demo_completed' => $this->coerceBool($demoCompleted),
            'site_enabled' => $this->coerceBool($current['site_enabled'] ?? false),
            'updated_at' => $this->nowIso(),
        ];
        $this->saveBrandingStore($store);

        if ($botFields !== null) {
            $this->saveBotIntakeConfiguration($bizId, $bookingMode !== '' ? $bookingMode : (string) ($current['booking_mode'] ?? ''), $botFields);
        }

        $setupStatus = $this->setupStatusSnapshot(
            $bizId,
            $trimmedName,
            $this->text($logoUrl),
            $this->coerceBool($demoCompleted),
            $this->coerceBool($customDomainRequested),
            $this->coerceBool($current['site_enabled'] ?? false)
        );
        $siteEnabled = $this->coerceBool($current['site_enabled'] ?? false);
        if ($this->coerceBool($launchSite)) {
            if (empty($setupStatus['ready_for_launch'])) {
                throw new RuntimeException('Finish branding, upload at least one priced fleet vehicle, and complete the demo before unlocking the live booking link.');
            }
            $siteEnabled = true;
        }
        $store['businesses'][$bizId]['site_enabled'] = $siteEnabled;
        $this->saveBrandingStore($store);

        $normalizedAccent = $this->normalizeHexColor($accent, (string) ($current['accent'] ?? '#72a9ff'));
        $businessPayload = [
            'biz_id' => $bizId,
            'slug' => strtolower($this->text($current['slug'] ?? $slug)),
            'biz_name' => $trimmedName,
            'logo_url' => $this->text($logoUrl),
            'brand_color' => $normalizedAccent,
            'primary_color' => $normalizedAccent,
            'secondary_color' => $this->mixHexColor($normalizedAccent, '#ffffff', 0.7),
            'brand_wordmark' => trim($brandWordmark !== '' ? $brandWordmark : $trimmedName),
            'booking_mode' => $bookingMode !== '' ? $bookingMode : (string) ($current['booking_mode'] ?? ''),
            'owner_email' => $this->text($ownerEmail),
            'owner_name' => $this->text($ownerName),
            'discount_type' => $this->normalizeDiscountType($discountType),
            'discount_value' => $this->normalizeDiscountValue($discountValue),
            'discount_label' => $this->text($discountLabel),
            'custom_domain_requested' => $this->coerceBool($customDomainRequested),
            'demo_completed' => $this->coerceBool($demoCompleted),
            'setup_completed' => !empty($setupStatus['ready_for_launch']),
            'site_enabled' => $siteEnabled,
        ];
        $this->supabaseRestRequest(
            'POST',
            'businesses',
            ['on_conflict' => 'biz_id'],
            $businessPayload,
            'resolution=merge-duplicates,return=representation'
        );

        return $this->getDashboardCustomization(
            $bizId,
            strtolower($this->text($current['slug'] ?? $slug)),
            $trimmedName,
            $brandWordmark !== '' ? $brandWordmark : $trimmedName,
            $accent,
            $bookingMode !== '' ? $bookingMode : (string) ($current['booking_mode'] ?? '')
        );
    }

    private function fleetDisplayName(?array $fleetRow): string
    {
        if ($fleetRow === null) {
            return 'Unassigned vehicle';
        }
        $make = $this->text($fleetRow['make'] ?? '');
        $model = $this->text($fleetRow['model'] ?? '');
        $explicit = $this->text(($fleetRow['name'] ?? '') ?: ($fleetRow['car_name'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }
        $resolved = trim($make . ' ' . $model);
        if ($resolved !== '') {
            return $resolved;
        }
        $identifier = $this->text($fleetRow['id'] ?? '');
        return $identifier !== '' ? 'Car #' . $identifier : 'Unnamed vehicle';
    }

    private function fleetColorName(?array $fleetRow): string
    {
        $color = $this->text($fleetRow['color'] ?? '');
        return $color !== '' ? ucwords($color) : 'Not set';
    }

    private function fleetIsActive(?array $fleetRow): bool
    {
        if ($fleetRow === null) {
            return true;
        }
        $value = $fleetRow['available'] ?? null;
        if ($value === null || $value === '') {
            return true;
        }
        return $this->coerceBool($value);
    }

    private function buildFleetLookup(array $fleetRows): array
    {
        $lookup = [];
        foreach ($fleetRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $labels = array_unique([
                $this->normalizeLookup($this->fleetDisplayName($row)),
                $this->normalizeLookup($row['model'] ?? ''),
                $this->normalizeLookup($row['make'] ?? ''),
                $this->normalizeLookup(trim(($row['make'] ?? '') . ' ' . ($row['model'] ?? ''))),
                $this->normalizeLookup($row['name'] ?? ''),
                $this->normalizeLookup($row['car_name'] ?? ''),
            ]);
            foreach ($labels as $label) {
                if ($label === '') {
                    continue;
                }
                $lookup[$label] ??= [];
                $lookup[$label][] = $row;
            }
        }
        return $lookup;
    }

    private function resolveFleetRowForBooking(array $booking, array $fleetIndex, array $fleetLookup): ?array
    {
        $carId = $this->text($booking['car_id'] ?? '');
        if ($carId !== '' && isset($fleetIndex[$carId])) {
            return $fleetIndex[$carId];
        }
        $labels = [
            $booking['car_model'] ?? '',
            $booking['vehicle_model'] ?? '',
            $booking['car_name'] ?? '',
            $booking['service_name'] ?? '',
            $booking['appointment_type'] ?? '',
        ];
        foreach ($labels as $label) {
            $normalized = $this->normalizeLookup($label);
            if ($normalized !== '' && isset($fleetLookup[$normalized][0]) && is_array($fleetLookup[$normalized][0])) {
                return $fleetLookup[$normalized][0];
            }
        }
        return null;
    }

    private function resolveCarModel(array $booking, array $fleetIndex): string
    {
        $explicit = $this->text(
            ($booking['service_name'] ?? '')
            ?: ($booking['appointment_type'] ?? '')
            ?: ($booking['car_model'] ?? '')
            ?: ($booking['vehicle_model'] ?? '')
            ?: ($booking['car_name'] ?? '')
        );
        if ($explicit !== '') {
            return $explicit;
        }
        $carId = $this->text($booking['car_id'] ?? '');
        $fleetRow = $carId !== '' && isset($fleetIndex[$carId]) ? $fleetIndex[$carId] : null;
        if (!$fleetRow) {
            return $carId !== '' ? 'Car #' . $carId : 'Not assigned';
        }
        $resolved = trim($this->text($fleetRow['make'] ?? '') . ' ' . $this->text($fleetRow['model'] ?? ''));
        return $resolved !== '' ? $resolved : ('Car #' . $carId);
    }

    private function resolveConfirmationValue(array $booking, ?array $state): bool
    {
        if ($state !== null && array_key_exists('is_confirmed', $state)) {
            return $this->coerceBool($state['is_confirmed']);
        }
        if (array_key_exists('is_confirmed', $booking)) {
            return $this->coerceBool($booking['is_confirmed']);
        }
        if (array_key_exists('confirmed', $booking)) {
            return $this->coerceBool($booking['confirmed']);
        }
        $statusText = strtolower($this->text($booking['confirmation_status'] ?? ''));
        return $statusText !== '' ? $statusText === 'confirmed' : false;
    }

    private function buildDashboardItem(array $booking, ?array $state, array $fleetIndex, array $fleetLookup): array
    {
        $startDate = $this->parseIsoDate($booking['start_date'] ?? '');
        $endDate = $this->bookingEndDate($startDate, $this->parseIsoDate($booking['end_date'] ?? ''));
        $fleetRow = $this->resolveFleetRowForBooking($booking, $fleetIndex, $fleetLookup);
        $isConfirmed = $this->resolveConfirmationValue($booking, $state);
        $paymentStatus = $this->normalizePaymentStatus(($state['payment_status'] ?? '') ?: ($booking['payment_status'] ?? ''));
        $carModel = $fleetRow ? $this->fleetDisplayName($fleetRow) : $this->resolveCarModel($booking, $fleetIndex);
        $customerName = $this->text($booking['customer_name'] ?? '') ?: 'Unnamed customer';
        $appointmentTime = $this->text($booking['appointment_time'] ?? '');
        $carMake = $this->text($fleetRow['make'] ?? '');
        $carColor = $this->fleetColorName($fleetRow);
        $fleetId = $this->text(($fleetRow['id'] ?? '') ?: ($booking['car_id'] ?? ''));
        $customFields = $booking['custom_fields'] ?? [];
        if (is_string($customFields) && $customFields !== '') {
            $decoded = json_decode($customFields, true);
            $customFields = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($customFields)) {
            $customFields = [];
        }
        $customFieldLabels = $booking['custom_field_labels'] ?? [];
        if (is_string($customFieldLabels) && $customFieldLabels !== '') {
            $decoded = json_decode($customFieldLabels, true);
            $customFieldLabels = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($customFieldLabels)) {
            $customFieldLabels = [];
        }

        $lengthLabel = $appointmentTime !== ''
            ? $appointmentTime
            : (($startDate && $endDate) ? $this->bookingLengthDays($startDate, $endDate) . ' day(s)' : 'Dates pending');

        $item = [
            'id' => $this->text($booking['id'] ?? ''),
            'customer_name' => $customerName,
            'phone' => $this->text($booking['phone'] ?? ''),
            'city' => $this->text($booking['city'] ?? ''),
            'location' => $this->text($booking['location'] ?? ''),
            'insurance' => $this->text($booking['insurance'] ?? '') ?: 'No insurance',
            'from_date' => $startDate?->format('Y-m-d') ?? '',
            'to_date' => $endDate?->format('Y-m-d') ?? '',
            'booking_length_days' => $this->bookingLengthDays($startDate, $endDate),
            'booking_length_label' => $lengthLabel,
            'fleet_id' => $fleetId,
            'car_make' => $carMake,
            'car_color' => $carColor,
            'car_model' => $carModel,
            'payment_status' => $paymentStatus,
            'is_confirmed' => $isConfirmed,
            'confirmation_label' => $isConfirmed ? 'Confirmed' : 'Pending',
            'total_price' => $booking['total_price'] ?? 0,
            'custom_fields' => $customFields,
            'custom_field_labels' => $customFieldLabels,
            '_start_date' => $startDate,
            '_end_date' => $endDate,
        ];
        $searchFields = [
            $item['customer_name'],
            $item['phone'],
            $item['city'],
            $item['location'],
            $item['insurance'],
            $appointmentTime,
            $item['car_make'],
            $item['car_color'],
            $item['car_model'],
            $item['payment_status'],
            $item['confirmation_label'],
            $item['id'],
        ];
        foreach ($customFields as $value) {
            $text = $this->text($value);
            if ($text !== '') {
                $searchFields[] = $text;
            }
        }
        $item['_search_blob'] = strtolower(implode(' ', $searchFields));
        return $item;
    }

    private function fetchDashboardSources(string $bizId): array
    {
        $bookings = $this->fetchRows('bookings', $bizId, 'start_date.asc');
        $warningParts = [];
        try {
            $fleetRows = $this->fetchRows('fleet', $bizId, 'model.asc');
        } catch (RuntimeException $exception) {
            $fleetRows = [];
            $warningParts[] = $exception->getMessage();
        }
        try {
            $stateRows = $this->fetchRows('booking_admin_states', $bizId, 'updated_at.desc');
        } catch (RuntimeException $exception) {
            $stateRows = [];
            $warningParts[] = 'Booking status table is missing or unavailable. Run php_employee_dashboard/supabase_schema.sql to enable confirmation and payment tracking.';
            $warningParts[] = $exception->getMessage();
        }

        $warningParts = array_values(array_unique(array_filter($warningParts)));
        $warning = $warningParts !== [] ? implode("\n", $warningParts) : null;
        return [$bookings, $fleetRows, $stateRows, $warning];
    }

    private function fetchBookingsForDashboard(string $bizId): array
    {
        [$bookings, $fleetRows, $stateRows, $warning] = $this->fetchDashboardSources($bizId);
        $fleetIndex = [];
        foreach ($fleetRows as $row) {
            if (is_array($row) && isset($row['id']) && $row['id'] !== null && $row['id'] !== '') {
                $fleetIndex[(string) $row['id']] = $row;
            }
        }
        $fleetLookup = $this->buildFleetLookup($fleetRows);
        $stateIndex = [];
        foreach ($stateRows as $row) {
            if (is_array($row) && isset($row['booking_id']) && $row['booking_id'] !== null && $row['booking_id'] !== '') {
                $stateIndex[(string) $row['booking_id']] = $row;
            }
        }
        $items = [];
        foreach ($bookings as $booking) {
            if (!is_array($booking)) {
                continue;
            }
            $items[] = $this->buildDashboardItem(
                $booking,
                $stateIndex[(string) ($booking['id'] ?? '')] ?? null,
                $fleetIndex,
                $fleetLookup
            );
        }

        usort($items, function (array $left, array $right): int {
            $leftDate = $left['_start_date'] ?? null;
            $rightDate = $right['_start_date'] ?? null;
            if (!$leftDate instanceof DateTimeImmutable && !$rightDate instanceof DateTimeImmutable) {
                return strcmp(strtolower((string) $left['customer_name']), strtolower((string) $right['customer_name']));
            }
            if (!$leftDate instanceof DateTimeImmutable) {
                return 1;
            }
            if (!$rightDate instanceof DateTimeImmutable) {
                return -1;
            }
            return $leftDate <=> $rightDate ?: strcmp(strtolower((string) $left['customer_name']), strtolower((string) $right['customer_name']));
        });

        return [$items, $warning];
    }

    private function applyDashboardFilters(array $items): array
    {
        $searchValue = strtolower(trim($this->query('q')));
        $startFilter = $this->parseIsoDate($this->query('start_date'));
        $endFilter = $this->parseIsoDate($this->query('end_date'));
        $carModelFilter = strtolower(trim($this->query('car_model')));
        $timelineFilter = strtolower(trim($this->query('timeline', 'current')));
        $confirmationFilter = strtolower(trim($this->query('confirmation', 'all')));
        $paymentFilter = strtolower(trim($this->query('payment', 'all')));
        $anchor = $this->todayDate();

        $filtered = $items;
        if ($searchValue !== '') {
            $filtered = array_values(array_filter($filtered, static fn (array $item): bool => str_contains((string) ($item['_search_blob'] ?? ''), $searchValue)));
        }
        if ($carModelFilter !== '') {
            $filtered = array_values(array_filter($filtered, static fn (array $item): bool => strtolower(trim((string) ($item['car_model'] ?? ''))) === $carModelFilter));
        }
        if ($confirmationFilter === 'confirmed') {
            $filtered = array_values(array_filter($filtered, fn (array $item): bool => $this->coerceBool($item['is_confirmed'] ?? false)));
        } elseif ($confirmationFilter === 'pending') {
            $filtered = array_values(array_filter($filtered, fn (array $item): bool => !$this->coerceBool($item['is_confirmed'] ?? false)));
        }
        if ($paymentFilter !== 'all') {
            $filtered = array_values(array_filter($filtered, static fn (array $item): bool => strtolower(trim((string) ($item['payment_status'] ?? ''))) === $paymentFilter));
        }
        if ($timelineFilter !== 'all') {
            $filtered = array_values(array_filter($filtered, function (array $item) use ($timelineFilter, $anchor): bool {
                $start = $item['_start_date'] ?? null;
                $end = $this->bookingEndDate($start instanceof DateTimeImmutable ? $start : null, $item['_end_date'] instanceof DateTimeImmutable ? $item['_end_date'] : null);
                if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
                    return false;
                }
                return match ($timelineFilter) {
                    'current' => $start <= $anchor && $anchor <= $end,
                    'upcoming' => $start > $anchor,
                    'past' => $end < $anchor,
                    default => true,
                };
            }));
        }
        if ($startFilter instanceof DateTimeImmutable || $endFilter instanceof DateTimeImmutable) {
            $filtered = array_values(array_filter($filtered, function (array $item) use ($startFilter, $endFilter): bool {
                $start = $item['_start_date'] ?? null;
                $end = $this->bookingEndDate($start instanceof DateTimeImmutable ? $start : null, $item['_end_date'] instanceof DateTimeImmutable ? $item['_end_date'] : null);
                if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
                    return false;
                }
                if ($startFilter instanceof DateTimeImmutable && $end < $startFilter) {
                    return false;
                }
                if ($endFilter instanceof DateTimeImmutable && $start > $endFilter) {
                    return false;
                }
                return true;
            }));
        }
        return $filtered;
    }

    private function summarizeItems(array $items): array
    {
        $confirmed = 0;
        $paid = 0;
        foreach ($items as $item) {
            if ($this->coerceBool($item['is_confirmed'] ?? false)) {
                $confirmed += 1;
            }
            if (strtolower((string) ($item['payment_status'] ?? '')) === 'paid') {
                $paid += 1;
            }
        }
        return [
            'total' => count($items),
            'confirmed' => $confirmed,
            'pending' => count($items) - $confirmed,
            'paid' => $paid,
        ];
    }

    private function serializeItems(array $items): array
    {
        $serialized = [];
        foreach ($items as $item) {
            $row = [];
            foreach ($item as $key => $value) {
                if (!str_starts_with((string) $key, '_')) {
                    $row[$key] = $value;
                }
            }
            $serialized[] = $row;
        }
        return $serialized;
    }

    private function buildAvailabilityEvent(array $booking, ?array $state, array $fleetIndex, array $fleetLookup): ?array
    {
        $startDate = $this->parseIsoDate($booking['start_date'] ?? '');
        $endDate = $this->bookingEndDate($startDate, $this->parseIsoDate($booking['end_date'] ?? ''));
        if (!$startDate instanceof DateTimeImmutable || !$endDate instanceof DateTimeImmutable) {
            return null;
        }
        $fleetRow = $this->resolveFleetRowForBooking($booking, $fleetIndex, $fleetLookup);
        $fleetId = $this->text(($fleetRow['id'] ?? '') ?: ($booking['car_id'] ?? ''));
        $carLabel = $fleetRow ? $this->fleetDisplayName($fleetRow) : $this->resolveCarModel($booking, $fleetIndex);
        return [
            'id' => $this->text($booking['id'] ?? ''),
            'fleet_id' => $fleetId,
            'car_label' => $carLabel,
            'customer_name' => $this->text($booking['customer_name'] ?? '') ?: 'Unnamed customer',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_confirmed' => $this->resolveConfirmationValue($booking, $state),
            'payment_status' => $this->normalizePaymentStatus(($state['payment_status'] ?? '') ?: ($booking['payment_status'] ?? '')),
        ];
    }

    private function applyAvailabilityFilters(array $resources): array
    {
        $searchValue = strtolower(trim($this->query('q')));
        $carIdFilter = trim($this->query('car_id'));
        $modelFilter = strtolower(trim($this->query('model')));
        $colorFilter = strtolower(trim($this->query('color')));
        $makeFilter = strtolower(trim($this->query('make')));

        $filtered = $resources;
        if ($searchValue !== '') {
            $filtered = array_values(array_filter($filtered, static fn (array $resource): bool => str_contains((string) ($resource['_search_blob'] ?? ''), $searchValue)));
        }
        if ($carIdFilter !== '') {
            $filtered = array_values(array_filter($filtered, static fn (array $resource): bool => (string) ($resource['id'] ?? '') === $carIdFilter));
        }
        if ($modelFilter !== '') {
            $filtered = array_values(array_filter($filtered, static fn (array $resource): bool => strtolower(trim((string) ($resource['model'] ?? ''))) === $modelFilter));
        }
        if ($colorFilter !== '') {
            $filtered = array_values(array_filter($filtered, static fn (array $resource): bool => strtolower(trim((string) ($resource['color'] ?? ''))) === $colorFilter));
        }
        if ($makeFilter !== '') {
            $filtered = array_values(array_filter($filtered, static fn (array $resource): bool => strtolower(trim((string) ($resource['make'] ?? ''))) === $makeFilter));
        }
        return $filtered;
    }

    private function buildAvailabilityPayload(string $bizId): array
    {
        [$bookings, $fleetRows, $stateRows, $warning] = $this->fetchDashboardSources($bizId);
        $anchorDate = $this->parseIsoDate($this->query('date')) ?? $this->todayDate();

        $weekDays = [];
        for ($offset = 0; $offset < 7; $offset += 1) {
            $weekDays[] = $anchorDate->add(new DateInterval('P' . $offset . 'D'));
        }
        $weekStart = $weekDays[0];
        $weekEnd = $weekDays[6];

        $monthStart = $anchorDate->modify('first day of this month');
        $monthEnd = $anchorDate->modify('last day of this month');
        $calendarStart = $monthStart->modify('-' . (((int) $monthStart->format('N')) - 1) . ' days');
        $calendarEnd = $monthEnd->modify('+' . (7 - ((int) $monthEnd->format('N'))) % 7 . ' days');
        $calendarDays = $this->dateRange($calendarStart, $calendarEnd);

        $fleetIndex = [];
        foreach ($fleetRows as $row) {
            if (is_array($row) && isset($row['id']) && $row['id'] !== null && $row['id'] !== '') {
                $fleetIndex[(string) $row['id']] = $row;
            }
        }
        $fleetLookup = $this->buildFleetLookup($fleetRows);
        $stateIndex = [];
        foreach ($stateRows as $row) {
            if (is_array($row) && isset($row['booking_id']) && $row['booking_id'] !== null && $row['booking_id'] !== '') {
                $stateIndex[(string) $row['booking_id']] = $row;
            }
        }

        $resources = [];
        foreach ($fleetRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $identifier = $this->text($row['id'] ?? '');
            if ($identifier === '') {
                continue;
            }
            $label = $this->fleetDisplayName($row);
            $make = $this->text($row['make'] ?? '');
            $modelName = $this->text($row['model'] ?? '');
            $color = $this->fleetColorName($row);
            $location = $this->text(($row['branch_location'] ?? '') ?: ($row['branch_city'] ?? '') ?: ($row['city'] ?? '') ?: ($row['location'] ?? ''));
            $resources[] = [
                'id' => $identifier,
                'label' => $label,
                'make' => $make !== '' ? $make : 'Not set',
                'model' => $modelName !== '' ? $modelName : $label,
                'color' => $color,
                'location' => $location !== '' ? $location : 'No location',
                'is_active' => $this->fleetIsActive($row),
                '_search_blob' => strtolower(trim(implode(' ', array_filter([$label, $make, $modelName, $color, $location, $identifier])))),
            ];
        }

        $resources = $this->applyAvailabilityFilters($resources);
        $resourceIds = array_column($resources, 'id');
        $eventMap = [];
        foreach ($resourceIds as $resourceId) {
            $eventMap[(string) $resourceId] = [];
        }
        $unassignedEvents = [];
        foreach ($bookings as $booking) {
            if (!is_array($booking)) {
                continue;
            }
            $event = $this->buildAvailabilityEvent($booking, $stateIndex[(string) ($booking['id'] ?? '')] ?? null, $fleetIndex, $fleetLookup);
            if ($event === null) {
                continue;
            }
            if (($event['fleet_id'] ?? '') !== '' && array_key_exists((string) $event['fleet_id'], $eventMap)) {
                $eventMap[(string) $event['fleet_id']][] = $event;
            } elseif (($event['fleet_id'] ?? '') === '' && $this->dateOverlapsRange($event['start_date'], $event['end_date'], $weekStart, $weekEnd)) {
                $unassignedEvents[] = $event;
            }
        }

        $serializedResources = [];
        $monthBookingCounts = [];
        $monthAvailableCounts = [];
        foreach ($calendarDays as $day) {
            $key = $day->format('Y-m-d');
            $monthBookingCounts[$key] = 0;
            $monthAvailableCounts[$key] = 0;
        }

        foreach ($resources as $resource) {
            $resourceEvents = $eventMap[(string) $resource['id']] ?? [];
            $rowDays = [];
            $bookedTotal = 0;
            foreach ($weekDays as $day) {
                $bookingsForDay = array_values(array_filter($resourceEvents, static fn (array $event): bool => $event['start_date'] <= $day && $day <= $event['end_date']));
                if ($bookingsForDay !== []) {
                    $status = 'booked';
                    $headline = (string) ($bookingsForDay[0]['customer_name'] ?? 'Booked');
                    $subtext = count($bookingsForDay) . ' booking' . (count($bookingsForDay) !== 1 ? 's' : '');
                    $bookedTotal += count($bookingsForDay);
                } elseif (!empty($resource['is_active'])) {
                    $status = 'available';
                    $headline = 'Available';
                    $subtext = 'Ready to book';
                } else {
                    $status = 'unavailable';
                    $headline = 'Unavailable';
                    $subtext = 'Manually disabled';
                }
                $rowDays[] = [
                    'date' => $day->format('Y-m-d'),
                    'label' => $day->format('D'),
                    'day_number' => (int) $day->format('j'),
                    'status' => $status,
                    'headline' => $headline,
                    'subtext' => $subtext,
                ];
            }

            foreach ($calendarDays as $day) {
                $dayKey = $day->format('Y-m-d');
                $hasBooking = false;
                foreach ($resourceEvents as $event) {
                    if ($event['start_date'] <= $day && $day <= $event['end_date']) {
                        $hasBooking = true;
                        break;
                    }
                }
                if ($hasBooking) {
                    $monthBookingCounts[$dayKey] += 1;
                } elseif (!empty($resource['is_active'])) {
                    $monthAvailableCounts[$dayKey] += 1;
                }
            }

            $serializedResources[] = [
                'id' => $resource['id'],
                'label' => $resource['label'],
                'make' => $resource['make'],
                'model' => $resource['model'],
                'color' => $resource['color'],
                'location' => $resource['location'],
                'is_active' => $resource['is_active'],
                'booked_total' => $bookedTotal,
                'days' => $rowDays,
            ];
        }

        $weeks = [];
        for ($weekIndex = 0; $weekIndex < count($calendarDays); $weekIndex += 7) {
            $cells = [];
            foreach (array_slice($calendarDays, $weekIndex, 7) as $day) {
                $dayKey = $day->format('Y-m-d');
                $booked = $monthBookingCounts[$dayKey] ?? 0;
                $available = $monthAvailableCounts[$dayKey] ?? 0;
                if ($booked > 0 && $available === 0) {
                    $status = 'booked';
                } elseif ($booked > 0 && $available > 0) {
                    $status = 'mixed';
                } elseif ($available > 0) {
                    $status = 'available';
                } else {
                    $status = 'unavailable';
                }
                $cells[] = [
                    'date' => $dayKey,
                    'day_number' => (int) $day->format('j'),
                    'is_current_month' => $day->format('n') === $anchorDate->format('n'),
                    'booked_count' => $booked,
                    'available_count' => $available,
                    'status' => $status,
                ];
            }
            $weeks[] = $cells;
        }

        $availableToday = 0;
        $bookedToday = 0;
        $unavailableToday = 0;
        foreach ($serializedResources as $resource) {
            $todayStatus = $resource['days'][0]['status'] ?? '';
            if ($todayStatus === 'available') {
                $availableToday += 1;
            } elseif ($todayStatus === 'booked') {
                $bookedToday += 1;
            } elseif ($todayStatus === 'unavailable') {
                $unavailableToday += 1;
            }
        }

        return [[
            'selected_date' => $anchorDate->format('Y-m-d'),
            'summary' => [
                'total_cars' => count($serializedResources),
                'available_today' => $availableToday,
                'booked_today' => $bookedToday,
                'unavailable_today' => $unavailableToday,
            ],
            'filters' => [
                'cars' => array_map(static fn (array $resource): array => ['id' => $resource['id'], 'label' => $resource['label']], $serializedResources),
                'models' => array_values(array_unique(array_map(static fn (array $resource): string => (string) $resource['model'], array_filter($resources, static fn (array $resource): bool => trim((string) $resource['model']) !== '')))),
                'colors' => array_values(array_unique(array_map(static fn (array $resource): string => (string) $resource['color'], array_filter($resources, static fn (array $resource): bool => trim((string) $resource['color']) !== '')))),
                'makes' => array_values(array_unique(array_map(static fn (array $resource): string => (string) $resource['make'], array_filter($resources, static fn (array $resource): bool => trim((string) $resource['make']) !== '')))),
            ],
            'week' => [
                'label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d, Y'),
                'days' => array_map(static fn (DateTimeImmutable $day): array => [
                    'date' => $day->format('Y-m-d'),
                    'weekday' => $day->format('D'),
                    'day_number' => (int) $day->format('j'),
                ], $weekDays),
            ],
            'resources' => $serializedResources,
            'calendar' => [
                'month_label' => $anchorDate->format('F Y'),
                'weeks' => $weeks,
            ],
            'unassigned_count' => count($unassignedEvents),
        ], $warning];
    }

    private function dateOverlapsRange(?DateTimeImmutable $startDate, ?DateTimeImmutable $endDate, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): bool
    {
        $actualStart = $startDate ?? $endDate;
        $actualEnd = $this->bookingEndDate($startDate, $endDate);
        if (!$actualStart instanceof DateTimeImmutable || !$actualEnd instanceof DateTimeImmutable) {
            return false;
        }
        return $actualStart <= $rangeEnd && $actualEnd >= $rangeStart;
    }

    private function dateRange(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        if ($endDate < $startDate) {
            $endDate = $startDate;
        }
        $days = [];
        $current = $startDate;
        while ($current <= $endDate) {
            $days[] = $current;
            $current = $current->add(new DateInterval('P1D'));
        }
        return $days;
    }
}
