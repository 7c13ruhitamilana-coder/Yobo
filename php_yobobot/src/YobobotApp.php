<?php

declare(strict_types=1);

namespace PhpYobobot;

use DateTimeImmutable;
use DateTimeZone;
use PhpEmployeeDashboard\DashboardApp;
use RuntimeException;

final class YobobotApp
{
    private const FONT_CHOICES = [
        'playfair' => "'Playfair Display', serif",
        'lora' => "'Lora', serif",
        'cormorant' => "'Cormorant Garamond', serif",
        'poppins' => "'Poppins', sans-serif",
        'manrope' => "'Manrope', sans-serif",
        'dm-sans' => "'DM Sans', sans-serif",
        'outfit' => "'Outfit', sans-serif",
        'sora' => "'Sora', sans-serif",
    ];

    private string $rootDir;
    private string $viewsDir;
    private string $publicDir;
    private string $dataDir;
    private string $portalUsersPath;
    private string $brandingPath;
    private string $secretsPath;
    private string $platformDataPath;
    private DashboardApp $dashboardApp;
    private ?array $platformData = null;
    private ?array $brandingStore = null;
    private ?array $secrets = null;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);
        $this->viewsDir = $this->rootDir . DIRECTORY_SEPARATOR . 'views';
        $this->publicDir = $this->rootDir . DIRECTORY_SEPARATOR . 'public';
        $this->dataDir = $this->resolveDataDir();
        $this->portalUsersPath = $this->resolveDataFilePath(['PORTAL_USERS_PATH', 'YOBOBOT_PORTAL_USERS_PATH'], 'portal_users.json');
        $this->brandingPath = $this->resolveDataFilePath(['BRANDING_STORE_PATH', 'YOBOBOT_BRANDING_STORE_PATH'], 'dashboard_branding.json');
        $this->secretsPath = $this->resolveSecretsPath();
        $this->platformDataPath = $this->rootDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'platform_data.json';
        $this->dashboardApp = new DashboardApp($this->rootDir);
    }

    public function handle(): void
    {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($path === '/dashboard/access') {
            $this->dashboardAccess();
            return;
        }

        if ($this->shouldDelegateToDashboard($path)) {
            $this->delegateToDashboard($path);
            return;
        }

        if (($path === '/' || $path === '') && $method === 'GET') {
            $hostSlug = $this->requestBusinessSlugFromHost();
            if ($hostSlug !== '') {
                $this->renderBusinessRoute($hostSlug, 'business_home', 'home');
                return;
            }
            $this->home();
            return;
        }

        if ($path === '/create-account') {
            $this->createAccount($method);
            return;
        }
        if ($path === '/account/login') {
            $this->portalLogin($method);
            return;
        }
        if ($path === '/account/logout' && $method === 'GET') {
            $this->portalLogout();
            return;
        }
        if ($path === '/pricing' && $method === 'GET') {
            $this->pricing();
            return;
        }
        if (preg_match('#^/checkout/([^/]+)$#', $path, $matches) === 1 && $method === 'GET') {
            $this->checkout($matches[1]);
            return;
        }
        if ($path === '/integrations' && $method === 'GET') {
            $this->integrations();
            return;
        }
        if (preg_match('#^/integrations/([^/]+)$#', $path, $matches) === 1 && $method === 'GET') {
            $this->integrationDetail($matches[1]);
            return;
        }
        if ($path === '/docs' && $method === 'GET') {
            $this->docs();
            return;
        }
        if ($path === '/who-can-use' && $method === 'GET') {
            $this->whoCanUse();
            return;
        }
        if ($path === '/onboarding' && $method === 'GET') {
            $this->onboarding();
            return;
        }
        if ($path === '/onboarding' && $method === 'POST') {
            $this->onboardingSubmit();
            return;
        }
        if ($path === '/workspace') {
            $this->workspace($method);
            return;
        }
        if (($path === '/see-it-in-action' || $path === '/demo') && $method === 'GET') {
            $this->demoHome();
            return;
        }
        if ($path === '/demo/assistant' && $method === 'GET') {
            $this->demoAssistant();
            return;
        }
        if ($path === '/demo/fleet' && $method === 'GET') {
            $this->demoFleet();
            return;
        }
        if (preg_match('#^/booking-site/([^/]+)/?$#', $path, $matches) === 1 && $method === 'GET') {
            $this->portalBookingSiteHome($matches[1]);
            return;
        }
        if (preg_match('#^/booking-site/([^/]+)/assistant$#', $path, $matches) === 1 && $method === 'GET') {
            $this->portalBookingSiteAssistant($matches[1]);
            return;
        }
        if (preg_match('#^/booking-site/([^/]+)/fleet$#', $path, $matches) === 1 && $method === 'GET') {
            $this->portalBookingSiteFleet($matches[1]);
            return;
        }
        if (preg_match('#^/b/([^/]+)/?$#', $path, $matches) === 1 && $method === 'GET') {
            $this->renderBusinessRoute($matches[1], 'business_home', 'home');
            return;
        }
        if (($path === '/assistant' || $path === '/assistant-classic' || $path === '/details' || $path === '/quote' || $path === '/insurance' || $path === '/location' || $path === '/summary') && $method === 'GET') {
            if ($path !== '/assistant') {
                $this->redirectToAssistant('');
                return;
            }
            $hostSlug = $this->requestBusinessSlugFromHost();
            if ($hostSlug !== '') {
                $this->renderBusinessRoute($hostSlug, 'business_assistant', 'assistant');
                return;
            }
            $this->assistantPage();
            return;
        }
        if (preg_match('#^/b/([^/]+)/(assistant|assistant-classic|details|quote|insurance|location|summary)$#', $path, $matches) === 1 && $method === 'GET') {
            if ($matches[2] !== 'assistant') {
                $this->redirectToAssistant($matches[1]);
                return;
            }
            $this->renderBusinessRoute($matches[1], 'business_assistant', 'assistant');
            return;
        }
        if ($path === '/fleet' && $method === 'GET') {
            $hostSlug = $this->requestBusinessSlugFromHost();
            if ($hostSlug !== '') {
                $this->renderBusinessRoute($hostSlug, 'business_fleet', 'fleet');
                return;
            }
            $this->fleetPage();
            return;
        }
        if (preg_match('#^/b/([^/]+)/fleet$#', $path, $matches) === 1 && $method === 'GET') {
            $this->renderBusinessRoute($matches[1], 'business_fleet', 'fleet');
            return;
        }
        if ($path === '/api/config' && $method === 'GET') {
            $this->apiConfig();
            return;
        }
        if ($path === '/api/fleet' && $method === 'GET') {
            $this->apiFleet();
            return;
        }
        if ($path === '/api/fleet/debug' && $method === 'GET') {
            $this->apiFleetDebug();
            return;
        }
        if ($path === '/api/debug/runtime' && $method === 'GET') {
            $this->apiRuntimeDebug();
            return;
        }
        if ($path === '/api/estimate' && $method === 'POST') {
            $this->apiEstimate();
            return;
        }
        if ($path === '/api/insurance' && $method === 'GET') {
            $this->apiInsurance();
            return;
        }
        if ($path === '/api/chat' && $method === 'POST') {
            $this->apiChat();
            return;
        }
        if ($path === '/api/bookings' && $method === 'POST') {
            $this->apiBookings();
            return;
        }

        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not Found';
    }

    private function shouldDelegateToDashboard(string $path): bool
    {
        if (in_array($path, ['/login', '/register', '/recover', '/logout', '/dashboard/login', '/dashboard/register', '/dashboard/recover', '/dashboard/logout'], true)) {
            return true;
        }

        return $path === '/dashboard'
            || str_starts_with($path, '/dashboard/bookings')
            || str_starts_with($path, '/dashboard/availability')
            || str_starts_with($path, '/dashboard/fleet')
            || str_starts_with($path, '/dashboard/integrations')
            || str_starts_with($path, '/api/dashboard/');
    }

    private function delegateToDashboard(string $path): void
    {
        $query = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY) ?: '';
        $map = [
            '/dashboard/login' => '/login',
            '/dashboard/register' => '/register',
            '/dashboard/recover' => '/recover',
            '/dashboard/logout' => '/logout',
        ];
        $translatedPath = $map[$path] ?? $path;
        $_SERVER['REQUEST_URI'] = $translatedPath . ($query !== '' ? '?' . $query : '');
        $this->dashboardApp->handle();
    }

    private function home(): void
    {
        $this->render('home', $this->buildMarketingPageContext(
            activeNav: 'home',
            pageTitle: 'Yobobot | Booking Bots, Onboarding, and Business Operations',
        ));
    }

    private function createAccount(string $method): void
    {
        $existingUser = $this->getLoggedInPortalUser();
        if ($existingUser !== null) {
            $this->redirect($this->portalDashboardTarget($existingUser));
            return;
        }

        $error = '';
        $fullName = '';
        $email = '';

        if ($method === 'POST') {
            $fullName = $this->text($this->post('full_name'));
            $email = $this->normalizeEmail($this->post('email'));
            $password = (string) ($this->post('password') ?? '');
            [$ok, $result] = $this->createPortalUser(
                fullName: $fullName,
                email: $email,
                password: $password,
            );
            if ($ok) {
                $_SESSION['portal_user_email'] = $result;
                $this->redirect('/onboarding');
                return;
            }
            $error = $result;
        }

        $this->render('create_account', [
            'formFullName' => $fullName,
            'formEmail' => $email,
            'authError' => $error,
            ...$this->buildMarketingPageContext(
                activeNav: 'home',
                pageTitle: 'Create Account | Yobobot',
            ),
        ]);
    }

    private function portalLogin(string $method): void
    {
        $existingUser = $this->getLoggedInPortalUser();
        if ($existingUser !== null) {
            $this->redirect($this->portalDashboardTarget($existingUser));
            return;
        }

        $error = '';
        $email = '';
        $nextPath = $this->text($this->query('next') ?? $this->post('next'));

        if ($method === 'POST') {
            $email = $this->normalizeEmail($this->post('email'));
            $password = (string) ($this->post('password') ?? '');
            $user = $this->getPortalUserByEmail($email);
            if ($user === null) {
                $error = 'Incorrect email or password.';
            } else {
                [$ok, $message] = $this->verifyPortalPassword($user, $password);
                if (!$ok) {
                    $error = $message;
                } else {
                    $_SESSION['portal_user_email'] = $email;
                    if ($nextPath !== '' && str_starts_with($nextPath, '/')) {
                        $this->redirect($nextPath);
                        return;
                    }
                    $this->redirect($this->portalDashboardTarget($user));
                    return;
                }
            }
        }

        $this->render('portal_login', [
            'formEmail' => $email,
            'authError' => $error,
            'nextPath' => $nextPath,
            ...$this->buildMarketingPageContext(
                activeNav: 'home',
                pageTitle: 'Log In | Yobobot',
            ),
        ]);
    }

    private function portalLogout(): void
    {
        $this->clearAuthSessions();
        $this->redirect('/');
    }

    private function dashboardAccess(): void
    {
        $user = $this->getLoggedInPortalUser();
        if ($user === null) {
            $this->redirect('/account/login?next=' . rawurlencode('/dashboard/access'));
            return;
        }
        if (!$this->portalProfileIsComplete($user)) {
            $this->redirect('/onboarding');
            return;
        }
        $employee = $_SESSION['employee'] ?? null;
        if (is_array($employee) && $this->normalizeEmail($employee['email'] ?? '') === $this->normalizeEmail($user['email'] ?? '')) {
            $this->redirect('/dashboard');
            return;
        }
        $this->redirect($this->dashboardAccessHrefForPortalUser($user));
    }

    private function pricing(): void
    {
        $context = $this->buildMarketingPageContext(
            activeNav: 'pricing',
            pageTitle: 'Yobobot Pricing',
        );
        $context['plans'] = $this->buildPricingPlanCards();
        $this->render('pricing', $context);
    }

    private function checkout(string $planSlug): void
    {
        $plan = $this->findMarketingPlan($planSlug);
        if ($plan === null) {
            $this->abort404();
            return;
        }
        $this->render('checkout', [
            'plan' => $plan,
            'paymentUrl' => $this->getPlanPaymentUrl($planSlug),
            ...$this->buildMarketingPageContext(
                activeNav: 'pricing',
                pageTitle: ($plan['name'] ?? 'Plan') . ' Checkout | Yobobot',
            ),
        ]);
    }

    private function integrations(): void
    {
        $context = $this->buildMarketingPageContext(
            activeNav: 'integrations',
            pageTitle: 'Yobobot Integrations',
        );
        $context['integrations'] = $this->integrationGuides();
        $this->render('integrations', $context);
    }

    private function integrationDetail(string $slug): void
    {
        $guide = $this->findIntegrationGuide($slug);
        if ($guide === null) {
            $this->abort404();
            return;
        }
        $this->render('integration_detail', [
            'integration' => $guide,
            ...$this->buildMarketingPageContext(
                activeNav: 'integrations',
                pageTitle: ($guide['title'] ?? 'Integration') . ' Integration | Yobobot',
            ),
        ]);
    }

    private function docs(): void
    {
        $this->render('docs', $this->buildDocsPageContext());
    }

    private function whoCanUse(): void
    {
        $this->render('who_can_use', $this->buildMarketingPageContext(
            activeNav: 'home',
            pageTitle: 'Who Can Use Yobobot',
        ));
    }

    private function onboarding(): void
    {
        $user = $this->requirePortalUser();
        if ($user === null) {
            return;
        }
        if ($this->portalProfileIsComplete($user)) {
            $this->redirect('/workspace');
            return;
        }
        $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : [];
        $businessType = $this->text($profile['business_type'] ?? 'car_rental') ?: 'car_rental';

        $this->render('onboarding', [
            'portalProfile' => $profile,
            'customerFieldOptions' => $this->customerFieldOptions($businessType, $profile['customer_fields'] ?? null),
            'onboardingError' => '',
            ...$this->buildMarketingPageContext(
                activeNav: 'home',
                pageTitle: 'Collect Business Details | Yobobot',
            ),
        ]);
    }

    private function onboardingSubmit(): void
    {
        $user = $this->requirePortalUser();
        if ($user === null) {
            return;
        }

        $phoneNumber = $this->text($this->post('phone_number'));
        $businessName = $this->text($this->post('business_name'));
        $businessType = $this->text($this->post('business_type'));
        $subdomain = $this->text($this->post('subdomain'));
        $brandColor = $this->text($this->post('brand_color'));
        $integrations = $this->postList('integrations');
        $customerFields = $this->postList('customer_fields');

        [$ok, $result] = $this->updatePortalBusinessProfile(
            $user['email'] ?? '',
            phoneNumber: $phoneNumber,
            businessName: $businessName,
            businessType: $businessType,
            subdomain: $subdomain,
            brandColor: $brandColor,
            integrations: $integrations,
            customerFields: $customerFields,
        );

        if ($ok) {
            $this->redirect('/workspace');
            return;
        }

        $this->render('onboarding', [
            'portalProfile' => [
                'phone_number' => $phoneNumber,
                'business_name' => $businessName,
                'business_type' => $businessType,
                'subdomain' => $subdomain,
                'brand_color' => $brandColor,
                'integrations' => $integrations,
                'customer_fields' => $customerFields,
            ],
            'customerFieldOptions' => $this->customerFieldOptions($businessType, $customerFields),
            'onboardingError' => $result,
            ...$this->buildMarketingPageContext(
                activeNav: 'home',
                pageTitle: 'Collect Business Details | Yobobot',
            ),
        ]);
    }

    private function workspace(string $method): void
    {
        $user = $this->requirePortalUser();
        if ($user === null) {
            return;
        }
        if (!$this->portalProfileIsComplete($user)) {
            $this->redirect('/onboarding');
            return;
        }

        $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : [];
        $workspaceMessage = '';
        $workspaceError = '';

        if ($method === 'POST') {
            $phoneNumber = $this->text($this->post('phone_number'));
            $businessName = $this->text($this->post('business_name'));
            $businessType = $this->text($this->post('business_type'));
            $subdomain = $this->text($this->post('subdomain'));
            $brandColor = $this->text($this->post('brand_color'));
            $integrations = $this->postList('integrations');
            $customerFields = $this->postList('customer_fields');
            [$ok, $result] = $this->updatePortalBusinessProfile(
                $user['email'] ?? '',
                phoneNumber: $phoneNumber,
                businessName: $businessName,
                businessType: $businessType,
                subdomain: $subdomain,
                brandColor: $brandColor,
                integrations: $integrations,
                customerFields: $customerFields,
            );
            if ($ok) {
                $user = $this->getLoggedInPortalUser() ?? $user;
                $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : [];
                $workspaceMessage = 'Business details confirmed and updated.';
                $fieldSyncMessage = $this->text($profile['supabase_fields_sync_message'] ?? '');
                if ($fieldSyncMessage !== '' && !$this->coerceBool($profile['supabase_fields_sync_ok'] ?? false)) {
                    $workspaceError = $fieldSyncMessage;
                }
            } else {
                $workspaceError = $result;
                $profile = [
                    'phone_number' => $phoneNumber,
                    'business_name' => $businessName,
                    'business_type' => $businessType,
                    'subdomain' => $subdomain,
                    'brand_color' => $brandColor,
                    'integrations' => $integrations,
                    'customer_fields' => $customerFields,
                ];
            }
        }

        if ($workspaceMessage === '' && $workspaceError === '') {
            $syncMessage = $this->text($profile['supabase_sync_message'] ?? '');
            $fieldSyncMessage = $this->text($profile['supabase_fields_sync_message'] ?? '');
            if ($syncMessage !== '') {
                if ($this->coerceBool($profile['supabase_sync_ok'] ?? false)) {
                    $workspaceMessage = $syncMessage;
                } else {
                    $workspaceError = $syncMessage;
                }
            }
            if ($fieldSyncMessage !== '' && !$this->coerceBool($profile['supabase_fields_sync_ok'] ?? false)) {
                $workspaceError = $fieldSyncMessage;
            }
        }

        $businessType = $this->text($profile['business_type'] ?? 'car_rental') ?: 'car_rental';
        $this->render('workspace', [
            'portalProfile' => $profile,
            'customerFieldOptions' => $this->customerFieldOptions($businessType, $profile['customer_fields'] ?? null),
            'bookingSiteHref' => $this->portalBookingSiteHref($profile),
            'requestedDomainHref' => $this->portalRequestedDomainHref($profile),
            'dashboardAccessHref' => $this->dashboardAccessHrefForPortalUser($user),
            'workspaceMessage' => $workspaceMessage,
            'workspaceError' => $workspaceError,
            ...$this->buildMarketingPageContext(
                activeNav: 'home',
                pageTitle: 'Yobobot Workspace',
            ),
        ]);
    }

    private function demoHome(): void
    {
        $this->render('business_home', $this->buildSiteDemoContext());
    }

    private function demoAssistant(): void
    {
        $this->render('business_assistant', $this->buildSiteDemoContext());
    }

    private function demoFleet(): void
    {
        $this->render('business_fleet', $this->buildSiteDemoContext());
    }

    private function portalBookingSiteHome(string $subdomain): void
    {
        $context = $this->buildPortalBookingSiteContext($subdomain);
        if ($context === null) {
            $this->abort404();
            return;
        }
        $this->render('business_home', $context);
    }

    private function portalBookingSiteAssistant(string $subdomain): void
    {
        $context = $this->buildPortalBookingSiteContext($subdomain);
        if ($context === null) {
            $this->abort404();
            return;
        }
        $this->render('business_assistant', $context);
    }

    private function portalBookingSiteFleet(string $subdomain): void
    {
        $context = $this->buildPortalBookingSiteContext($subdomain);
        if ($context === null) {
            $this->abort404();
            return;
        }
        $this->render('business_fleet', $context);
    }

    private function assistantPage(): void
    {
        $this->render('business_assistant', $this->buildBusinessPageContext());
    }

    private function fleetPage(): void
    {
        $this->render('business_fleet', $this->buildBusinessPageContext());
    }

    private function redirectToAssistant(string $slug): void
    {
        if ($slug !== '') {
            if (!$this->companyProfileExists(slug: $slug)) {
                $this->abort404();
                return;
            }
            $context = $this->buildBusinessPageContext(slug: $slug);
            $this->redirect((string) ($context['assistantHref'] ?? '/assistant'));
            return;
        }
        $hostSlug = $this->requestBusinessSlugFromHost();
        if ($hostSlug !== '') {
            if (!$this->companyProfileExists(slug: $hostSlug)) {
                $this->abort404();
                return;
            }
            $context = $this->buildBusinessPageContext(slug: $hostSlug);
            $this->redirect((string) ($context['assistantHref'] ?? '/assistant'));
            return;
        }
        $this->redirect('/assistant');
    }

    private function renderBusinessRoute(string $slug, string $view, string $visitSource): void
    {
        $normalizedSlug = strtolower(trim($slug));
        if ($normalizedSlug === '' || !$this->companyProfileExists(slug: $normalizedSlug)) {
            $this->abort404();
            return;
        }
        $config = $this->resolveCompanyConfig(slug: $normalizedSlug);
        if ($this->businessSiteIsLocked($config)) {
            $this->render('booking_site_locked', $this->buildBusinessPageContext(slug: $normalizedSlug));
            return;
        }
        $this->recordBusinessSiteVisit($this->text($config['biz_id'] ?? ''), $visitSource);
        $this->render($view, $this->buildBusinessPageContext(slug: $normalizedSlug));
    }

    private function apiConfig(): void
    {
        $config = $this->getCompanyConfig();
        $this->json([
            'company_name' => $config['company_name'] ?? '',
            'brand_wordmark' => $config['brand_wordmark'] ?? '',
            'assistant_name' => $config['assistant_name'] ?? '',
            'booking_mode' => $config['booking_mode'] ?? 'rental',
            'hero_title' => $config['hero_title'] ?? '',
            'hero_subtitle' => $config['hero_subtitle'] ?? '',
            'accent' => $config['accent'] ?? '',
            'currency' => $config['currency'] ?? 'SGD',
            'hero_image' => $config['hero_image'] ?? '',
            'processing_fee' => $config['processing_fee'] ?? 50,
            'vat_rate' => $config['vat_rate'] ?? 0.09,
            'slug' => $config['slug'] ?? '',
            'biz_id' => $this->requestedBizId(),
        ]);
    }

    private function apiFleet(): void
    {
        $bizId = $this->requestedBizId();
        if ($bizId === '') {
            $this->json(['ok' => false, 'data' => [], 'demo' => false, 'error' => 'Missing biz_id.'], 400);
            return;
        }

        $config = $this->resolveCompanyConfig(bizId: $bizId);
        $bookingMode = $this->normalizeBookingMode($config['booking_mode'] ?? '');
        if ($bookingMode === 'service') {
            $catalog = $this->normalizeServiceCatalog(
                $config['service_catalog'] ?? [],
                (string) ($config['currency'] ?? 'SGD'),
            );
            $this->json(['ok' => true, 'data' => $catalog, 'demo' => false, 'error' => null]);
            return;
        }

        $startRaw = $this->text($this->query('start_date'));
        $endRaw = $this->text($this->query('end_date'));
        $cityRaw = strtolower($this->text($this->query('city')));
        $luxuryRaw = strtolower($this->text($this->query('luxury')));
        [$data, $demo, $error] = $this->fetchFleetFromSupabase($bizId);

        $startDate = $this->parseDate($startRaw);
        $endDate = $this->parseDate($endRaw);
        if ($startDate !== null && $endDate !== null) {
            $bookedIds = $this->fetchBookedCarIds($bizId, $startDate, $endDate);
            $data = array_values(array_filter($data, fn (array $car): bool => !in_array((string) ($car['id'] ?? ''), $bookedIds, true)));
        }

        if ($cityRaw !== '') {
            $normalizeCity = static function ($value): string {
                return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value))) ?: '';
            };
            $cityFields = ['city', 'branch_city', 'branch_location', 'location'];
            $cityMatches = array_values(array_filter($data, static function (array $car) use ($cityFields): bool {
                foreach ($cityFields as $field) {
                    if (!empty($car[$field])) {
                        return true;
                    }
                }
                return false;
            }));
            if ($cityMatches !== []) {
                $normalizedRequested = $normalizeCity($cityRaw);
                $filtered = array_values(array_filter($data, static function (array $car) use ($cityFields, $normalizeCity, $normalizedRequested): bool {
                    foreach ($cityFields as $field) {
                        if (!empty($car[$field]) && $normalizeCity($car[$field]) === $normalizedRequested) {
                            return true;
                        }
                    }
                    return false;
                }));
                $configuredMarketCity = $normalizeCity((string) ($config['market_city'] ?? ''));
                if ($filtered !== [] || $normalizedRequested !== $configuredMarketCity) {
                    $data = $filtered;
                }
            }
        }

        if ($luxuryRaw !== '') {
            $normalizeLuxury = static function ($value): string {
                if (is_bool($value)) {
                    return $value ? 'luxury' : 'standard';
                }
                $lowered = strtolower(trim((string) $value));
                return match ($lowered) {
                    'true', '1', 'yes', 'luxury' => 'luxury',
                    'false', '0', 'no', 'standard' => 'standard',
                    default => $lowered,
                };
            };
            $hasLuxury = false;
            foreach ($data as $car) {
                if (array_key_exists('luxury', $car)) {
                    $hasLuxury = true;
                    break;
                }
            }
            if ($hasLuxury && in_array($luxuryRaw, ['luxury', 'standard'], true)) {
                $data = array_values(array_filter($data, static fn (array $car): bool => $normalizeLuxury($car['luxury'] ?? null) === $luxuryRaw));
            }
        }

        $this->json(['ok' => true, 'data' => $data, 'demo' => $demo, 'error' => $error]);
    }

    private function apiFleetDebug(): void
    {
        $bizId = $this->requestedBizId();
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            $this->json([
                'ok' => false,
                'error' => 'Supabase not configured.',
                'biz_id' => $bizId,
                'url' => $supabaseUrl,
                'has_key' => (bool) $supabaseKey,
            ]);
            return;
        }

        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/fleet?' . http_build_query([
            'select' => 'id,make,model,available,photo_url,biz_id',
            'available' => 'eq.true',
            'biz_id' => 'eq.' . $bizId,
        ]);
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
        ];
        try {
            $response = $this->httpRequest('GET', $endpoint, $headers);
            $this->json([
                'ok' => in_array($response['status'], [200, 201], true),
                'status' => $response['status'],
                'biz_id' => $bizId,
                'url' => $supabaseUrl,
                'body' => $response['raw'],
            ]);
        } catch (RuntimeException $exception) {
            $this->json([
                'ok' => false,
                'error' => 'Supabase error: ' . $exception->getMessage(),
                'biz_id' => $bizId,
                'url' => $supabaseUrl,
            ]);
        }
    }

    private function apiRuntimeDebug(): void
    {
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        $bizId = $this->getDefaultBizId();
        $host = '';
        $hostResolution = ['ok' => false, 'error' => 'No SUPABASE_URL configured.'];
        if ($supabaseUrl !== null && $supabaseUrl !== '') {
            $host = preg_replace('#^https?://#', '', $supabaseUrl) ?? '';
            $host = explode('/', $host, 2)[0] ?? '';
            $resolved = gethostbyname($host);
            if ($resolved !== $host) {
                $hostResolution = ['ok' => true, 'host' => $host, 'ip' => $resolved];
            } else {
                $hostResolution = ['ok' => false, 'host' => $host, 'error' => 'DNS resolution failed.'];
            }
        }

        $this->json([
            'cwd' => getcwd(),
            'app_file' => __FILE__,
            'port_env' => getenv('PORT') ?: '',
            'data_dir' => $this->dataDir,
            'portal_users_path' => $this->portalUsersPath,
            'branding_path' => $this->brandingPath,
            'secrets_path' => $this->secretsPath,
            'secrets_file_exists' => is_file($this->secretsPath),
            'secrets_keys' => array_keys($this->loadSecrets()),
            'supabase_url_present' => $supabaseUrl !== null && $supabaseUrl !== '',
            'supabase_url_host' => $host,
            'supabase_key_present' => $supabaseKey !== null && $supabaseKey !== '',
            'supabase_key_prefix' => substr((string) ($supabaseKey ?? ''), 0, 12),
            'supabase_biz_id' => $bizId,
            'host_resolution' => $hostResolution,
        ]);
    }

    private function apiEstimate(): void
    {
        $body = $this->jsonBody();
        $startDate = $this->parseDate((string) ($body['start_date'] ?? ''));
        $endDate = $this->parseDate((string) ($body['end_date'] ?? ''));
        $pricePerDay = (int) round((float) ($body['price_per_day'] ?? 0));
        if ($startDate === null || $endDate === null) {
            $this->json(['ok' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
            return;
        }
        $days = max((int) $endDate->diff($startDate)->format('%r%a'), 1);
        $days = abs($days);
        if ($days < 1) {
            $days = 1;
        }
        $this->json(['ok' => true, 'days' => $days, 'estimated_total' => max($pricePerDay, 0) * $days]);
    }

    private function apiInsurance(): void
    {
        $bizId = $this->requestedBizId();
        $data = $this->fetchInsuranceFromSupabase($bizId);
        $this->json(['ok' => true, 'data' => $data, 'demo' => $data === $this->demoInsuranceItems()]);
    }

    private function apiChat(): void
    {
        $body = $this->jsonBody();
        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            $this->json(['ok' => false, 'error' => 'Message is required.'], 400);
            return;
        }

        $history = is_array($body['history'] ?? null) ? $body['history'] : [];
        $bizId = $this->text($body['biz_id'] ?? '') ?: $this->getDefaultBizId();
        $config = $this->resolveCompanyConfig(bizId: $bizId);
        $bookingMode = $this->normalizeBookingMode($config['booking_mode'] ?? '');
        $context = is_array($body['context'] ?? null) ? $body['context'] : [];
        $contextStart = trim((string) ($context['start_date'] ?? ''));
        $contextEnd = trim((string) ($context['end_date'] ?? ''));
        $contextCity = trim((string) ($context['city'] ?? ''));

        [$startDate, $endDate] = $this->parseChatDates($message, $contextStart, $contextEnd);
        $availableFleet = $this->getAvailableFleetForDates($bizId, $startDate, $endDate);
        $availableLines = $this->formatFleetLines($availableFleet);
        $serviceCatalog = $this->normalizeServiceCatalog($config['service_catalog'] ?? [], (string) ($config['currency'] ?? 'SGD'));
        $serviceLines = $this->formatServiceLines($serviceCatalog, (string) ($config['currency'] ?? 'SGD'));

        [$apiKey, $model] = $this->getGeminiConfig();
        if ($apiKey === null || $apiKey === '') {
            $this->json([
                'ok' => false,
                'error' => 'Gemini API key not configured.',
                'reply' => "I'm not connected yet. Please try again later.",
            ]);
            return;
        }

        if ($bookingMode === 'service') {
            $systemText = sprintf(
                "You are %s, the %s booking assistant. Help customers understand the available services and request a mechanic appointment. Only mention services from the list below. If the customer asks to browse services, tell them you can open the services page. Ask for the preferred appointment date if it is missing. Keep responses brief and helpful.\n\nSelected location: %s\nPreferred appointment date: %s\nAvailable services:\n- %s",
                $this->text($config['assistant_name'] ?? 'Yobo') ?: 'Yobo',
                $this->text($config['company_name'] ?? 'service') ?: 'service',
                $contextCity !== '' ? $contextCity : ($this->text($config['market_city'] ?? '') ?: 'Not provided'),
                $startDate?->format('Y-m-d') ?? 'Not provided',
                $serviceLines !== [] ? implode("\n- ", $serviceLines) : 'No services are configured yet.'
            );
        } else {
            $systemText = sprintf(
                "You are %s, the %s assistant. Help customers pick cars based on their needs and budget. Only recommend cars from the fleet list below. If asked about color, say color info is not available. If the customer asks to browse the fleet, tell them you can open the fleet page. Ask for the rental dates if missing. Keep responses brief and helpful.\n\nSelected city: %s\nSelected start date: %s\nSelected end date: %s\nAvailable fleet for the selected dates:\n- %s",
                $this->text($config['assistant_name'] ?? 'Yobo') ?: 'Yobo',
                $this->text($config['company_name'] ?? 'Veep') ?: 'Veep',
                $contextCity !== '' ? $contextCity : 'Not provided',
                $startDate?->format('Y-m-d') ?? 'Not provided',
                $endDate?->format('Y-m-d') ?? 'Not provided',
                $availableLines !== [] ? implode("\n- ", $availableLines) : 'No cars available for those dates.'
            );
        }

        $contents = [];
        foreach (array_slice($history, -6) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $text = trim((string) ($entry['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            $contents[] = [
                'role' => (($entry['role'] ?? '') === 'user') ? 'user' : 'model',
                'parts' => [['text' => $text]],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];
        $payload = [
            'contents' => $contents,
            'system_instruction' => ['parts' => [['text' => $systemText]]],
            'generationConfig' => ['temperature' => 0.6, 'maxOutputTokens' => 400],
        ];
        $headers = [
            'x-goog-api-key: ' . $apiKey,
            'Content-Type: application/json',
        ];
        try {
            $response = $this->httpRequest(
                'POST',
                'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent',
                $headers,
                $payload,
            );
            if ($response['status'] !== 200 || !is_array($response['body'])) {
                $this->json([
                    'ok' => false,
                    'error' => 'Gemini response ' . $response['status'] . ': ' . $response['raw'],
                    'reply' => 'Gemini error. Check API key/model.',
                ]);
                return;
            }
            $candidates = $response['body']['candidates'] ?? [];
            if (!is_array($candidates) || $candidates === []) {
                $this->json(['ok' => false, 'error' => 'No response.', 'reply' => '']);
                return;
            }
            $parts = $candidates[0]['content']['parts'] ?? [];
            $replyParts = [];
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    if (is_array($part) && !empty($part['text'])) {
                        $replyParts[] = trim((string) $part['text']);
                    }
                }
            }
            $reply = trim(implode(' ', $replyParts));
            $lowered = strtolower($message);
            $browsePhrases = $bookingMode === 'service'
                ? ['service', 'services', 'appointment', 'appointments', 'mechanic', 'show services', 'browse services']
                : ['fleet', 'available cars', 'available fleet', 'show cars', 'show fleet', 'browse cars', 'browse fleet'];
            $fleetAction = null;
            foreach ($browsePhrases as $phrase) {
                if (str_contains($lowered, $phrase)) {
                    $href = $this->buildBusinessHref($config, '/fleet');
                    $query = [];
                    if ($bizId !== '') {
                        $query['biz_id'] = $bizId;
                    }
                    if ($startDate !== null) {
                        $query['start_date'] = $startDate->format('Y-m-d');
                    }
                    if ($endDate !== null) {
                        $query['end_date'] = $endDate->format('Y-m-d');
                    }
                    if ($contextCity !== '') {
                        $query['city'] = $contextCity;
                    }
                    if ($query !== []) {
                        $href .= '?' . http_build_query($query);
                    }
                    $fleetAction = [
                        'label' => $bookingMode === 'service'
                            ? 'View Services'
                            : (($startDate !== null && $endDate !== null) ? 'View Available Fleet' : 'Go To Fleet'),
                        'href' => $href,
                    ];
                    break;
                }
            }
            if ($bookingMode !== 'service' && $fleetAction !== null && $startDate !== null && $endDate !== null) {
                if ($availableLines !== []) {
                    $reply = 'Available from ' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d') . ': ' . implode('; ', array_slice($availableLines, 0, 5)) . '. ' . $reply;
                } else {
                    $reply = 'No cars are currently available from ' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d') . '. ' . $reply;
                }
            }
            $this->json([
                'ok' => true,
                'reply' => $reply,
                'action' => $fleetAction,
                'context' => [
                    'start_date' => $startDate?->format('Y-m-d') ?? '',
                    'end_date' => $endDate?->format('Y-m-d') ?? '',
                ],
            ]);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => 'Gemini error: ' . $exception->getMessage(), 'reply' => '']);
        }
    }

    private function apiBookings(): void
    {
        $body = $this->jsonBody();
        if (empty($body['biz_id'])) {
            $body['biz_id'] = $this->getDefaultBizId();
        }
        if (!is_array($body['custom_fields'] ?? null)) {
            $body['custom_fields'] = [];
        }
        if (!is_array($body['custom_field_labels'] ?? null)) {
            $body['custom_field_labels'] = [];
        }

        $config = $this->resolveCompanyConfig(bizId: (string) ($body['biz_id'] ?? ''));
        $bookingMode = $this->normalizeBookingMode($config['booking_mode'] ?? '');

        if ($bookingMode === 'service') {
            $appointmentDate = $this->text($body['appointment_date'] ?? '') ?: $this->text($body['start_date'] ?? '');
            $body['start_date'] = $appointmentDate;
            $body['end_date'] = $this->text($body['end_date'] ?? '') ?: $appointmentDate;
            $body['city'] = $this->text($body['city'] ?? '') ?: ($this->text($config['market_city'] ?? '') ?: 'Service appointment');
            $body['car_id'] = $this->text($body['car_id'] ?? '') ?: ($this->text($body['service_id'] ?? '') ?: 'service-appointment');
            $body['booking_mode'] = 'service';
            $body['booking_type'] = $this->text($body['booking_type'] ?? '') ?: 'mechanic_appointment';
            if (!empty($body['service_name']) && empty($body['car_model'])) {
                $body['car_model'] = $body['service_name'];
            }
            if (!empty($body['current_vehicle_type']) && empty($body['vehicle_model'])) {
                $body['vehicle_model'] = $body['current_vehicle_type'];
            }
            if (!empty($body['contract_company']) && empty($body['notes'])) {
                $body['notes'] = $body['contract_company'];
            }
            $detailBits = array_values(array_filter([
                $this->text($body['appointment_time'] ?? ''),
                $this->text($body['current_vehicle_type'] ?? ''),
                $this->text($body['notes'] ?? ''),
            ]));
            $insuranceFallback = implode(' | ', $detailBits);
            $body['insurance'] = $this->text($body['insurance'] ?? '') ?: ($insuranceFallback !== '' ? $insuranceFallback : 'Appointment request');
            if (empty($body['location'])) {
                $body['location'] = $this->text($body['city'] ?? '') ?: ($this->text($config['market_city'] ?? '') ?: 'Service appointment');
            }
            $required = ['biz_id', 'customer_name', 'phone', 'start_date', 'location', 'service_name'];
        } else {
            if (empty($body['insurance'])) {
                $body['insurance'] = 'No insurance';
            }
            if (empty($body['location'])) {
                $body['location'] = $this->text($body['city'] ?? '') ?: ($this->text($config['market_city'] ?? '') ?: $this->text($config['company_name'] ?? ''));
            }
            $required = ['biz_id', 'customer_name', 'phone', 'city', 'start_date', 'end_date', 'car_id', 'total_price', 'location', 'insurance'];
        }

        $missing = [];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                $missing[] = $field;
            }
        }
        if ($missing !== []) {
            $this->json(['ok' => false, 'error' => 'Missing fields: ' . implode(', ', $missing)], 400);
            return;
        }

        try {
            $body['total_price'] = (int) round((float) ($body['total_price'] ?? 0));
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => 'Invalid total_price format.'], 400);
            return;
        }

        $startDate = $this->parseDate((string) ($body['start_date'] ?? ''));
        $endDate = $this->parseDate((string) ($body['end_date'] ?? ''));
        if ($startDate === null || $endDate === null) {
            $this->json(['ok' => false, 'error' => 'Invalid booking dates.'], 400);
            return;
        }
        $today = new DateTimeImmutable('today');
        if ($startDate < $today) {
            $this->json(['ok' => false, 'error' => 'Start date cannot be in the past.'], 400);
            return;
        }
        if ($endDate < $startDate) {
            $this->json(['ok' => false, 'error' => 'End date cannot be before start date.'], 400);
            return;
        }

        if ($bookingMode !== 'service') {
            $bookedIds = $this->fetchBookedCarIds((string) ($body['biz_id'] ?? ''), $startDate, $endDate);
            if (in_array((string) ($body['car_id'] ?? ''), $bookedIds, true)) {
                $this->json(['ok' => false, 'error' => 'That vehicle is no longer available for the selected dates.'], 409);
                return;
            }
        }

        [$ok, $message] = $this->saveBookingToSupabase($body);
        if ($ok) {
            $this->json(['ok' => true, 'message' => $message]);
            return;
        }
        $this->json(['ok' => false, 'error' => $message], 500);
    }

    private function buildMarketingPageContext(string $activeNav, string $pageTitle): array
    {
        $theme = $this->buildThemeVars('#f59a3c');
        $portalUser = $this->getLoggedInPortalUser();
        $employeeUser = is_array($_SESSION['employee'] ?? null) ? $_SESSION['employee'] : null;
        $integrations = [];
        foreach ($this->integrationGuides() as $item) {
            $entry = $item;
            $entry['href'] = '/integrations/' . rawurlencode((string) ($item['slug'] ?? ''));
            $integrations[] = $entry;
        }

        $siteUserLoggedIn = false;
        $siteUserName = '';
        $siteUserInitials = 'YO';
        $siteProfileHref = '/account/login';
        $siteLogoutHref = '/account/logout';
        $siteDashboardHref = '/dashboard';
        $siteDashboardLabel = 'Dashboard';
        if ($portalUser !== null) {
            $siteUserLoggedIn = true;
            $siteUserName = $this->text($portalUser['full_name'] ?? '');
            $siteUserInitials = $this->buildInitials($siteUserName !== '' ? $siteUserName : $this->text($portalUser['email'] ?? ''));
            $siteDashboardHref = $this->portalDashboardTarget($portalUser);
            $siteDashboardLabel = $this->portalProfileIsComplete($portalUser) ? 'Workspace' : 'Complete Setup';
            $siteProfileHref = $siteDashboardHref;
        } elseif ($employeeUser !== null) {
            $siteUserLoggedIn = true;
            $siteUserName = $this->text($employeeUser['full_name'] ?? '') ?: $this->text($employeeUser['email'] ?? '');
            $siteUserInitials = $this->buildInitials($siteUserName);
            $siteDashboardHref = '/dashboard';
            $siteProfileHref = '/dashboard';
            $siteLogoutHref = '/dashboard/logout';
        }

        return [
            'pageTitle' => $pageTitle,
            'companyName' => 'Yobobot',
            'brandWordmark' => 'YOBOBOT',
            'activeNav' => $activeNav,
            'siteUserLoggedIn' => $siteUserLoggedIn,
            'siteUserName' => $siteUserName,
            'siteUserInitials' => $siteUserInitials,
            'siteProfileHref' => $siteProfileHref,
            'siteLogoutHref' => $siteLogoutHref,
            'siteDashboardHref' => $siteDashboardHref,
            'siteDashboardLabel' => $siteDashboardLabel,
            'siteHomeHref' => '/',
            'sitePricingHref' => '/pricing',
            'siteIntegrationsHref' => '/integrations',
            'siteDocsHref' => '/docs',
            'siteActionHref' => '/demo',
            'siteCreateAccountHref' => '/create-account',
            'siteLoginHref' => '/account/login',
            'portalLoginHref' => '/account/login',
            'siteLogoutPath' => '/account/logout',
            'siteScheduleDemoHref' => '/demo',
            'siteStartFreeHref' => '/create-account',
            'siteOnboardingHref' => '/onboarding',
            'siteWorkspaceHref' => '/workspace',
            'siteWhoCanUseHref' => '/who-can-use',
            'siteProfileName' => $siteUserName,
            'currentSampleHref' => '/demo/assistant',
            'rentalSampleHref' => '/b/veep/assistant',
            'workshopSampleHref' => '/b/mechanicappointments/assistant',
            'garageSampleHref' => '/b/mechanicappointments/fleet',
            'integrations' => $integrations,
            'themeAccent' => $theme['themeAccent'],
            'themeAccentDeep' => $theme['themeAccentDeep'],
            'themeAccentRgb' => $theme['themeAccentRgb'],
            'themeBorder' => $theme['themeBorder'],
            'themeBg' => $theme['themeBg'],
            'themeSoft' => $theme['themeSoft'],
            'headingFontCss' => self::FONT_CHOICES['playfair'],
            'bodyFontCss' => self::FONT_CHOICES['poppins'],
        ];
    }

    private function buildDocsPageContext(): array
    {
        $base = $this->buildMarketingPageContext(
            activeNav: 'docs',
            pageTitle: 'Yobobot Documentation',
        );
        return [
            ...$base,
            'docsHomeHref' => $base['siteHomeHref'],
            'docsPricingHref' => $base['sitePricingHref'],
            'docsIntegrationsHref' => $base['siteIntegrationsHref'],
            'docsHref' => $base['siteDocsHref'],
            'docsActionHref' => $base['siteActionHref'],
            'docsStartFreeHref' => $base['siteStartFreeHref'],
            'docsScheduleDemoHref' => $base['siteScheduleDemoHref'],
            'assistantDemoHref' => $base['currentSampleHref'],
        ];
    }

    private function buildBusinessPageContext(string $slug = '', string $bizId = ''): array
    {
        $config = ($slug !== '' || $bizId !== '')
            ? $this->resolveCompanyConfig(slug: $slug, bizId: $bizId)
            : $this->getCompanyConfig();
        $routing = $this->buildLocationRoutingConfig();
        $resolvedBizId = $this->text($config['biz_id'] ?? '');
        $resolvedSlug = strtolower($this->text($config['slug'] ?? ''));
        $bookingMode = $this->normalizeBookingMode($config['booking_mode'] ?? '');
        $isService = $bookingMode === 'service';
        $theme = $this->buildThemeVars((string) ($config['accent'] ?? '#f59a3c'));
        $useGenericPaths = (bool) ($routing['enabled'] ?? false);
        $browseLabel = $this->text($config['browse_label'] ?? '') ?: ($isService ? 'Services' : 'Our Fleet');
        $assistantCtaLabel = $this->text($config['assistant_cta_label'] ?? '') ?: ($isService ? 'Book Appointment' : 'Start Enquiry');
        $homeCtaLabel = $this->text($config['home_cta_label'] ?? '') ?: $assistantCtaLabel;
        $browseTitle = $this->text($config['browse_title'] ?? '') ?: ($isService ? 'Choose Your Service' : 'Pick Your Dream Car');
        $browseSubtitle = $this->text($config['browse_subtitle'] ?? '');
        if ($browseSubtitle === '') {
            $browseSubtitle = $isService
                ? 'Browse the available services here. To request an appointment, continue with ' . ($this->text($config['assistant_name'] ?? 'Yobo') ?: 'Yobo') . '.'
                : 'Browse the fleet here. To submit an enquiry, continue with Yobo.';
        }
        $flowConfig = is_array($config['flow'] ?? null) ? $config['flow'] : [];
        $businessType = $isService ? 'car_workshop' : 'car_rental';
        [$selectedCustomerFields, $customerFieldLabels] = $this->resolveCustomerFieldConfiguration(
            businessType: $businessType,
            flowConfig: $flowConfig,
            bizId: $resolvedBizId,
        );
        $flowConfig = $this->applyCustomerFieldsToFlowConfig(
            $flowConfig,
            businessType: $businessType,
            selectedKeys: $selectedCustomerFields,
            labelOverrides: $customerFieldLabels,
        );
        if (!isset($flowConfig['booking_mode'])) {
            $flowConfig['booking_mode'] = $bookingMode;
        }
        if (!isset($flowConfig['pricing_enabled'])) {
            $flowConfig['pricing_enabled'] = !$isService;
        }

        return [
            'defaultBizId' => $resolvedBizId,
            'currentBusinessSlug' => $resolvedSlug,
            'bookingMode' => $bookingMode,
            'companyName' => $this->text($config['company_name'] ?? 'Veep'),
            'brandWordmark' => $this->text($config['brand_wordmark'] ?? '') ?: $this->text($config['company_name'] ?? ''),
            'assistantName' => $this->text($config['assistant_name'] ?? 'Yobo'),
            'heroTitle' => $this->text($config['hero_title'] ?? 'Find Your Next Drive With Veep.'),
            'heroSubtitle' => $this->text($config['hero_subtitle'] ?? ''),
            'accent' => $this->text($config['accent'] ?? '#f59a3c'),
            'headingFontCss' => $this->resolveFontCss($this->text($config['heading_font'] ?? ''), $this->text($config['heading_font_css'] ?? '')),
            'bodyFontCss' => $this->resolveFontCss($this->text($config['body_font'] ?? ''), $this->text($config['body_font_css'] ?? '')),
            'currency' => $this->text($config['currency'] ?? 'SGD'),
            'processingFee' => $config['processing_fee'] ?? 50,
            'vatRate' => $config['vat_rate'] ?? 0.09,
            'discountType' => strtolower($this->text($config['discount_type'] ?? 'none')),
            'discountValue' => $config['discount_value'] ?? 0,
            'discountLabel' => $this->text($config['discount_label'] ?? ''),
            'logoUrl' => $this->text($config['logo_url'] ?? ''),
            'marketCity' => $this->text($config['market_city'] ?? ''),
            'browseLabel' => $browseLabel,
            'browseTitle' => $browseTitle,
            'browseSubtitle' => $browseSubtitle,
            'assistantCtaLabel' => $assistantCtaLabel,
            'homeCtaLabel' => $homeCtaLabel,
            'assistantFlowConfig' => $flowConfig,
            'locationRoutingConfig' => $routing,
            'homeHref' => $this->buildBusinessHref($config, '/', $useGenericPaths),
            'detailsHref' => $this->buildBusinessHref($config, '/details', $useGenericPaths),
            'assistantHref' => $this->buildBusinessHref($config, '/assistant', $useGenericPaths),
            'assistantClassicHref' => $this->buildBusinessHref($config, '/assistant-classic', $useGenericPaths),
            'fleetHref' => $this->buildBusinessHref($config, '/fleet', $useGenericPaths),
            'insuranceHref' => $this->buildBusinessHref($config, '/insurance', $useGenericPaths),
            'quoteHref' => $this->buildBusinessHref($config, '/quote', $useGenericPaths),
            'locationHref' => $this->buildBusinessHref($config, '/location', $useGenericPaths),
            'summaryHref' => $this->buildBusinessHref($config, '/summary', $useGenericPaths),
            'themeAccent' => $theme['themeAccent'],
            'themeAccentDeep' => $theme['themeAccentDeep'],
            'themeAccentRgb' => $theme['themeAccentRgb'],
            'themeBorder' => $theme['themeBorder'],
            'themeBg' => $theme['themeBg'],
            'themeSoft' => $theme['themeSoft'],
        ];
    }

    private function buildPortalBookingSiteContext(string $subdomain): ?array
    {
        $user = $this->getPortalUserBySubdomain($subdomain);
        if ($user === null) {
            return null;
        }
        $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : null;
        if ($profile === null) {
            return null;
        }

        $businessType = $this->text($profile['business_type'] ?? 'car_rental') ?: 'car_rental';
        $baseSlug = $businessType === 'car_workshop' ? 'mechanicappointments' : 'veep';
        $config = $this->resolveCompanyConfig(slug: $baseSlug);
        $config['company_name'] = $this->text($profile['business_name'] ?? '') ?: $this->text($config['company_name'] ?? '');
        $config['brand_wordmark'] = $this->text($profile['business_name'] ?? '') ?: $this->text($config['brand_wordmark'] ?? '');
        $config['accent'] = $this->normalizeBrandColor($profile['brand_color'] ?? '');
        $config['slug'] = '';
        $config['biz_id'] = $this->text($profile['biz_id'] ?? '') ?: $this->text($config['biz_id'] ?? '');
        $config = $this->applyDashboardCustomizationToConfig($config);

        $theme = $this->buildThemeVars((string) ($config['accent'] ?? '#f59a3c'));
        $bookingMode = $this->normalizeBookingMode($config['booking_mode'] ?? '');
        $isService = $bookingMode === 'service';
        $browseLabel = $this->text($config['browse_label'] ?? '') ?: ($isService ? 'Services' : 'Our Fleet');
        $assistantCtaLabel = $this->text($config['assistant_cta_label'] ?? '') ?: ($isService ? 'Book Appointment' : 'Book With Yobo');
        $homeCtaLabel = $this->text($config['home_cta_label'] ?? '') ?: $assistantCtaLabel;
        $browseTitle = $this->text($config['browse_title'] ?? '') ?: ($isService ? 'Choose Your Service' : 'Pick Your Dream Car');
        $browseSubtitle = $this->text($config['browse_subtitle'] ?? '');
        if ($browseSubtitle === '') {
            $browseSubtitle = $isService
                ? 'Browse the available services here. To request an appointment, continue with ' . ($this->text($config['assistant_name'] ?? 'Yobo') ?: 'Yobo') . '.'
                : 'Browse the fleet here. To submit an enquiry, continue with Yobo.';
        }
        $flowConfig = is_array($config['flow'] ?? null) ? $config['flow'] : [];
        [$selectedCustomerFields, $customerFieldLabels] = $this->resolveCustomerFieldConfiguration(
            businessType: $businessType,
            profile: $profile,
            flowConfig: $flowConfig,
            bizId: $this->text($config['biz_id'] ?? ''),
        );
        $flowConfig = $this->applyCustomerFieldsToFlowConfig(
            $flowConfig,
            businessType: $businessType,
            selectedKeys: $selectedCustomerFields,
            labelOverrides: $customerFieldLabels,
        );
        if (!isset($flowConfig['booking_mode'])) {
            $flowConfig['booking_mode'] = $bookingMode;
        }
        if (!isset($flowConfig['pricing_enabled'])) {
            $flowConfig['pricing_enabled'] = !$isService;
        }

        return [
            'defaultBizId' => $this->text($config['biz_id'] ?? ''),
            'currentBusinessSlug' => strtolower($this->text($profile['subdomain'] ?? '')),
            'bookingMode' => $bookingMode,
            'companyName' => $this->text($config['company_name'] ?? 'Veep'),
            'brandWordmark' => $this->text($config['brand_wordmark'] ?? '') ?: $this->text($config['company_name'] ?? ''),
            'assistantName' => $this->text($config['assistant_name'] ?? 'Yobo'),
            'heroTitle' => $this->text($config['hero_title'] ?? ''),
            'heroSubtitle' => $this->text($config['hero_subtitle'] ?? ''),
            'accent' => $this->text($config['accent'] ?? '#f59a3c'),
            'headingFontCss' => $this->resolveFontCss($this->text($config['heading_font'] ?? ''), $this->text($config['heading_font_css'] ?? '')),
            'bodyFontCss' => $this->resolveFontCss($this->text($config['body_font'] ?? ''), $this->text($config['body_font_css'] ?? '')),
            'currency' => $this->text($config['currency'] ?? 'SGD'),
            'processingFee' => $config['processing_fee'] ?? 50,
            'vatRate' => $config['vat_rate'] ?? 0.09,
            'discountType' => strtolower($this->text($config['discount_type'] ?? 'none')),
            'discountValue' => $config['discount_value'] ?? 0,
            'discountLabel' => $this->text($config['discount_label'] ?? ''),
            'logoUrl' => $this->text($config['logo_url'] ?? ''),
            'marketCity' => $this->text($config['market_city'] ?? ''),
            'browseLabel' => $browseLabel,
            'browseTitle' => $browseTitle,
            'browseSubtitle' => $browseSubtitle,
            'assistantCtaLabel' => $assistantCtaLabel,
            'homeCtaLabel' => $homeCtaLabel,
            'assistantFlowConfig' => $flowConfig,
            'locationRoutingConfig' => $this->buildLocationRoutingConfig(),
            'homeHref' => '/booking-site/' . rawurlencode($subdomain) . '/',
            'detailsHref' => '/booking-site/' . rawurlencode($subdomain) . '/assistant',
            'assistantHref' => '/booking-site/' . rawurlencode($subdomain) . '/assistant',
            'assistantClassicHref' => '/booking-site/' . rawurlencode($subdomain) . '/assistant',
            'fleetHref' => '/booking-site/' . rawurlencode($subdomain) . '/fleet',
            'insuranceHref' => '/booking-site/' . rawurlencode($subdomain) . '/assistant',
            'quoteHref' => '/booking-site/' . rawurlencode($subdomain) . '/assistant',
            'locationHref' => '/booking-site/' . rawurlencode($subdomain) . '/assistant',
            'summaryHref' => '/booking-site/' . rawurlencode($subdomain) . '/assistant',
            'themeAccent' => $theme['themeAccent'],
            'themeAccentDeep' => $theme['themeAccentDeep'],
            'themeAccentRgb' => $theme['themeAccentRgb'],
            'themeBorder' => $theme['themeBorder'],
            'themeBg' => $theme['themeBg'],
            'themeSoft' => $theme['themeSoft'],
        ];
    }

    private function buildSiteDemoContext(): array
    {
        $context = $this->buildBusinessPageContext(slug: 'secondbrand');
        $context['currentBusinessSlug'] = 'demo';
        $context['homeHref'] = '/demo';
        $context['detailsHref'] = '/demo/assistant';
        $context['assistantHref'] = '/demo/assistant';
        $context['assistantClassicHref'] = '/demo/assistant';
        $context['fleetHref'] = '/demo/fleet';
        $context['insuranceHref'] = '/demo/assistant';
        $context['quoteHref'] = '/demo/assistant';
        $context['locationHref'] = '/demo/assistant';
        $context['summaryHref'] = '/demo/assistant';
        return $context;
    }

    private function getCompanyConfig(): array
    {
        return $this->resolveCompanyConfig(
            slug: $this->requestBusinessSlugFromHost(),
            bizId: $this->text($this->query('biz_id')) ?: $this->getDefaultBizId(),
        );
    }

    private function resolveCompanyConfig(string $slug = '', string $bizId = ''): array
    {
        $platform = $this->loadPlatformData();
        $businesses = is_array($platform['businesses'] ?? null) ? $platform['businesses'] : [];
        $defaultSlug = strtolower($this->text($platform['default_slug'] ?? 'veep')) ?: 'veep';
        $slug = strtolower(trim($slug));
        $bizId = trim($bizId);

        $profile = null;
        if ($slug !== '' && isset($businesses[$slug]) && is_array($businesses[$slug])) {
            $profile = $businesses[$slug];
        }
        if ($profile === null && $bizId !== '') {
            foreach ($businesses as $item) {
                if (is_array($item) && $this->text($item['biz_id'] ?? '') === $bizId) {
                    $profile = $item;
                    break;
                }
            }
        }
        if ($profile === null && ($slug !== '' || $bizId !== '')) {
            $supabaseRow = $this->fetchSupabaseBusinessRow($slug, $bizId);
            if ($supabaseRow !== null) {
                $profile = $this->supabaseBusinessRowToConfig($supabaseRow);
            }
        }
        if ($profile === null) {
            $profile = is_array($businesses[$defaultSlug] ?? null) ? $businesses[$defaultSlug] : $this->defaultCompany();
        }

        $base = $this->defaultCompany();
        foreach ($profile as $key => $value) {
            $base[$key] = $value;
        }
        $base['slug'] = strtolower($this->text($base['slug'] ?? '') ?: $slug ?: $defaultSlug);
        $base['biz_id'] = $this->text($base['biz_id'] ?? '') ?: $bizId ?: $this->getDefaultBizId();
        $base['booking_mode'] = $this->normalizeBookingMode($base['booking_mode'] ?? '');
        $base['service_catalog'] = $this->normalizeServiceCatalog($base['service_catalog'] ?? [], (string) ($base['currency'] ?? 'SGD'));
        return $this->applyDashboardCustomizationToConfig($base);
    }

    private function applyDashboardCustomizationToConfig(array $config): array
    {
        $bizId = $this->text($config['biz_id'] ?? '');
        if ($bizId === '') {
            return $config;
        }
        $branding = $this->loadBrandingStore();
        $businesses = is_array($branding['businesses'] ?? null) ? $branding['businesses'] : [];
        $custom = is_array($businesses[$bizId] ?? null) ? $businesses[$bizId] : [];
        if ($custom === []) {
            return $config;
        }
        if ($this->text($custom['company_name'] ?? '') !== '') {
            $config['company_name'] = $this->text($custom['company_name']);
        }
        if ($this->text($custom['brand_wordmark'] ?? '') !== '') {
            $config['brand_wordmark'] = $this->text($custom['brand_wordmark']);
        }
        if ($this->text($custom['accent'] ?? '') !== '') {
            $config['accent'] = $this->normalizeBrandColor($custom['accent']);
        }
        if ($this->text($custom['logo_url'] ?? '') !== '') {
            $config['logo_url'] = $this->text($custom['logo_url']);
        }
        if ($this->text($custom['heading_font'] ?? '') !== '') {
            $config['heading_font'] = $this->text($custom['heading_font']);
            $config['heading_font_css'] = $this->resolveFontCss($this->text($custom['heading_font']), '');
        }
        if ($this->text($custom['body_font'] ?? '') !== '') {
            $config['body_font'] = $this->text($custom['body_font']);
            $config['body_font_css'] = $this->resolveFontCss($this->text($custom['body_font']), '');
        }
        foreach (['discount_type', 'discount_value', 'discount_label', 'custom_domain_requested', 'demo_completed', 'site_enabled'] as $key) {
            if (array_key_exists($key, $custom)) {
                $config[$key] = $custom[$key];
            }
        }
        return $config;
    }

    private function businessSiteIsLocked(array $config): bool
    {
        return strtolower($this->text($config['config_source'] ?? '')) === 'supabase'
            && !$this->coerceBool($config['site_enabled'] ?? false);
    }

    private function buildBusinessHref(array $config, string $path, bool $useGenericPaths = false): string
    {
        $resolvedSlug = strtolower($this->text($config['slug'] ?? ''));
        $resolvedBizId = $this->text($config['biz_id'] ?? '');
        $hostSlug = $this->requestBusinessSlugFromHost();
        if ($hostSlug !== '' && $resolvedSlug !== '' && $hostSlug === $resolvedSlug) {
            return $path;
        }
        if ($useGenericPaths) {
            return $path;
        }
        if ($resolvedSlug !== '') {
            return $path === '/' ? '/b/' . rawurlencode($resolvedSlug) . '/' : '/b/' . rawurlencode($resolvedSlug) . $path;
        }
        if ($resolvedBizId !== '') {
            return $path . (str_contains($path, '?') ? '&' : '?') . 'biz_id=' . rawurlencode($resolvedBizId);
        }
        return $path;
    }

    private function buildLocationRoutingConfig(): array
    {
        $platform = $this->loadPlatformData();
        $businesses = is_array($platform['businesses'] ?? null) ? $platform['businesses'] : [];
        $explicitSlug = $this->requestBusinessSlugFromHost() !== '';
        $explicitBiz = $this->text($this->query('biz_id')) !== '';
        $forcedDefaultSlug = strtolower(trim((string) (getenv('DEFAULT_BUSINESS_SLUG') ?: '')));
        $enabled = !$explicitSlug && !$explicitBiz && $forcedDefaultSlug === '' && count($businesses) > 1;

        $marketDefaults = [
            'SGD' => ['market_city' => 'Singapore', 'market_country_codes' => ['SG'], 'market_timezones' => ['Asia/Singapore']],
            'AED' => ['market_city' => 'Dubai', 'market_country_codes' => ['AE'], 'market_timezones' => ['Asia/Dubai']],
        ];

        $targets = [];
        foreach ($businesses as $slug => $config) {
            if (!is_array($config)) {
                continue;
            }
            $currency = strtoupper($this->text($config['currency'] ?? ''));
            $fallback = $marketDefaults[$currency] ?? [];
            $countryCodes = is_array($config['market_country_codes'] ?? null) ? $config['market_country_codes'] : ($fallback['market_country_codes'] ?? []);
            $timezones = is_array($config['market_timezones'] ?? null) ? $config['market_timezones'] : ($fallback['market_timezones'] ?? []);
            $targets[] = [
                'slug' => $slug,
                'currency' => $currency,
                'market_city' => $this->text($config['market_city'] ?? '') ?: ($fallback['market_city'] ?? ''),
                'country_codes' => array_values(array_filter(array_map(fn ($value): string => strtoupper($this->text($value)), $countryCodes))),
                'timezones' => array_values(array_filter(array_map(fn ($value): string => $this->text($value), $timezones))),
            ];
        }

        return ['enabled' => $enabled, 'targets' => $targets];
    }

    private function companyProfileExists(string $slug = '', string $bizId = ''): bool
    {
        $platform = $this->loadPlatformData();
        $businesses = is_array($platform['businesses'] ?? null) ? $platform['businesses'] : [];
        $normalizedSlug = strtolower(trim($slug));
        $normalizedBizId = trim($bizId);
        if ($normalizedSlug !== '' && isset($businesses[$normalizedSlug])) {
            return true;
        }
        if ($normalizedBizId !== '') {
            foreach ($businesses as $item) {
                if (is_array($item) && $this->text($item['biz_id'] ?? '') === $normalizedBizId) {
                    return true;
                }
            }
        }
        if ($normalizedSlug !== '' || $normalizedBizId !== '') {
            return $this->fetchSupabaseBusinessRow($normalizedSlug, $normalizedBizId) !== null;
        }
        return false;
    }

    private function buildPricingPlanCards(): array
    {
        $cards = [];
        foreach ($this->marketingPlans() as $plan) {
            $entry = $plan;
            $slug = strtolower($this->text($entry['slug'] ?? ''));
            if ($slug === 'free') {
                $entry['cta_href'] = '/create-account';
                $entry['cta_label'] = 'Choose Free';
            } elseif ($slug === 'enterprise') {
                $entry['cta_href'] = '/checkout/' . rawurlencode($slug);
                $entry['cta_label'] = 'Talk to us';
            } else {
                $entry['cta_href'] = '/checkout/' . rawurlencode($slug);
                $entry['cta_label'] = 'Choose ' . $this->text($entry['name'] ?? 'Plan');
            }
            $cards[] = $entry;
        }
        return $cards;
    }

    private function findMarketingPlan(string $slug): ?array
    {
        $normalized = strtolower(trim($slug));
        foreach ($this->marketingPlans() as $plan) {
            if (strtolower($this->text($plan['slug'] ?? '')) === $normalized) {
                return $plan;
            }
        }
        return null;
    }

    private function findIntegrationGuide(string $slug): ?array
    {
        $normalized = strtolower(trim($slug));
        foreach ($this->integrationGuides() as $guide) {
            if (strtolower($this->text($guide['slug'] ?? '')) === $normalized) {
                return $guide;
            }
        }
        return null;
    }

    private function getPlanPaymentUrl(string $planSlug): string
    {
        $normalized = strtoupper(trim($planSlug));
        if ($normalized === '') {
            return '';
        }
        $secrets = $this->loadSecrets();
        $envKey = 'PAYMENT_LINK_' . $normalized;
        return trim((string) (getenv($envKey) ?: ($secrets[$envKey] ?? '')));
    }

    private function createPortalUser(string $fullName, string $email, string $password): array
    {
        $normalizedEmail = $this->normalizeEmail($email);
        if (trim($fullName) === '') {
            return [false, 'Please enter your full name.'];
        }
        if ($normalizedEmail === '' || !$this->isValidEmail($normalizedEmail)) {
            return [false, 'Please enter a valid email address.'];
        }
        if (strlen($password) < 8) {
            return [false, 'Please choose a password with at least 8 characters.'];
        }
        if ($this->getPortalUserByEmail($normalizedEmail) !== null) {
            return [false, 'That email already has an account. Please log in instead.'];
        }

        $this->savePortalUser([
            'email' => $normalizedEmail,
            'full_name' => trim($fullName),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => gmdate('Y-m-d'),
            'business_profile' => new \stdClass(),
        ]);
        return [true, $normalizedEmail];
    }

    private function verifyPortalPassword(array $user, string $password): array
    {
        $hash = $this->text($user['password_hash'] ?? '');
        if ($hash === '') {
            return [false, 'Incorrect email or password.'];
        }
        if (str_starts_with($hash, '$2') || str_starts_with($hash, '$argon2')) {
            $verified = password_verify($password, $hash);
            if ($verified && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $this->rehashPortalPassword($user, $password);
            }
            return [$verified, 'Incorrect email or password.'];
        }
        if (str_starts_with($hash, 'scrypt:') || str_starts_with($hash, 'pbkdf2:')) {
            $verified = $this->verifyWerkzeugHash($hash, $password);
            if ($verified) {
                $this->rehashPortalPassword($user, $password);
            }
            return [$verified, 'Incorrect email or password.'];
        }
        return [false, 'Incorrect email or password.'];
    }

    private function rehashPortalPassword(array $user, string $password): void
    {
        $email = $this->normalizeEmail($user['email'] ?? '');
        if ($email === '') {
            return;
        }
        $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $this->savePortalUser($user);
    }

    private function verifyWerkzeugHash(string $storedHash, string $password): bool
    {
        $parts = explode('$', $storedHash, 3);
        if (count($parts) !== 3) {
            return false;
        }
        [$methodSpec, $salt, $expectedHex] = $parts;
        $methodSpec = trim($methodSpec);
        $salt = (string) $salt;
        $expectedHex = strtolower(trim($expectedHex));
        if ($methodSpec === '' || $salt === '' || $expectedHex === '') {
            return false;
        }

        if (str_starts_with($methodSpec, 'scrypt:')) {
            return $this->verifyWerkzeugScryptHash($methodSpec, $salt, $expectedHex, $password);
        }
        if (str_starts_with($methodSpec, 'pbkdf2:')) {
            return $this->verifyWerkzeugPbkdf2Hash($methodSpec, $salt, $expectedHex, $password);
        }
        return false;
    }

    private function verifyWerkzeugPbkdf2Hash(
        string $methodSpec,
        string $salt,
        string $expectedHex,
        string $password
    ): bool {
        $parts = explode(':', $methodSpec);
        if (count($parts) < 3 || $parts[0] !== 'pbkdf2') {
            return false;
        }
        $hashName = trim((string) ($parts[1] ?? 'sha256'));
        $iterations = (int) ($parts[2] ?? 0);
        if ($hashName === '' || $iterations < 1) {
            return false;
        }
        $calculatedHex = hash_pbkdf2($hashName, $password, $salt, $iterations);
        return hash_equals($expectedHex, strtolower($calculatedHex));
    }

    private function verifyWerkzeugScryptHash(
        string $methodSpec,
        string $salt,
        string $expectedHex,
        string $password
    ): bool {
        $parts = explode(':', $methodSpec);
        if (count($parts) !== 4 || $parts[0] !== 'scrypt') {
            return false;
        }
        $n = (int) ($parts[1] ?? 0);
        $r = (int) ($parts[2] ?? 0);
        $p = (int) ($parts[3] ?? 0);
        if ($n < 2 || ($n & ($n - 1)) !== 0 || $r < 1 || $p < 1) {
            return false;
        }

        try {
            $calculatedHex = $this->scryptHashHex($password, $salt, $n, $r, $p, strlen($expectedHex) / 2);
        } catch (\Throwable) {
            return false;
        }
        return hash_equals($expectedHex, strtolower($calculatedHex));
    }

    private function scryptHashHex(
        string $password,
        string $salt,
        int $n,
        int $r,
        int $p,
        int $dkLen = 64
    ): string {
        $blockSize = 128 * $r;
        $derived = hash_pbkdf2('sha256', $password, $salt, 1, $blockSize * $p, true);
        $blocks = [];
        for ($index = 0; $index < $p; $index++) {
            $block = substr($derived, $index * $blockSize, $blockSize);
            $blocks[] = $this->scryptRomix($block, $r, $n);
        }
        $mixed = implode('', $blocks);
        return bin2hex(hash_pbkdf2('sha256', $password, $mixed, 1, $dkLen, true));
    }

    private function scryptRomix(string $block, int $r, int $n): string
    {
        $v = [];
        $x = $block;
        for ($index = 0; $index < $n; $index++) {
            $v[$index] = $x;
            $x = $this->scryptBlockMix($x, $r);
        }
        for ($index = 0; $index < $n; $index++) {
            $j = $this->scryptIntegerify($x, $r) & ($n - 1);
            $x = $this->scryptBlockMix($x ^ $v[$j], $r);
        }
        return $x;
    }

    private function scryptIntegerify(string $block, int $r): int
    {
        $offset = (2 * $r - 1) * 64;
        $words = unpack('V2', substr($block, $offset, 8));
        return (int) (($words[1] ?? 0) & 0xffffffff);
    }

    private function scryptBlockMix(string $block, int $r): string
    {
        $chunkCount = 2 * $r;
        $chunks = str_split($block, 64);
        $x = $chunks[$chunkCount - 1];
        $y = [];
        for ($index = 0; $index < $chunkCount; $index++) {
            $x = $this->salsa208($x ^ $chunks[$index]);
            $y[$index] = $x;
        }

        $result = '';
        for ($index = 0; $index < $chunkCount; $index += 2) {
            $result .= $y[$index];
        }
        for ($index = 1; $index < $chunkCount; $index += 2) {
            $result .= $y[$index];
        }
        return $result;
    }

    private function salsa208(string $block): string
    {
        $state = array_values(unpack('V16', $block));
        $working = $state;

        for ($round = 0; $round < 8; $round += 2) {
            $working[4] ^= $this->rotl32($this->add32($working[0], $working[12]), 7);
            $working[8] ^= $this->rotl32($this->add32($working[4], $working[0]), 9);
            $working[12] ^= $this->rotl32($this->add32($working[8], $working[4]), 13);
            $working[0] ^= $this->rotl32($this->add32($working[12], $working[8]), 18);

            $working[9] ^= $this->rotl32($this->add32($working[5], $working[1]), 7);
            $working[13] ^= $this->rotl32($this->add32($working[9], $working[5]), 9);
            $working[1] ^= $this->rotl32($this->add32($working[13], $working[9]), 13);
            $working[5] ^= $this->rotl32($this->add32($working[1], $working[13]), 18);

            $working[14] ^= $this->rotl32($this->add32($working[10], $working[6]), 7);
            $working[2] ^= $this->rotl32($this->add32($working[14], $working[10]), 9);
            $working[6] ^= $this->rotl32($this->add32($working[2], $working[14]), 13);
            $working[10] ^= $this->rotl32($this->add32($working[6], $working[2]), 18);

            $working[3] ^= $this->rotl32($this->add32($working[15], $working[11]), 7);
            $working[7] ^= $this->rotl32($this->add32($working[3], $working[15]), 9);
            $working[11] ^= $this->rotl32($this->add32($working[7], $working[3]), 13);
            $working[15] ^= $this->rotl32($this->add32($working[11], $working[7]), 18);

            $working[1] ^= $this->rotl32($this->add32($working[0], $working[3]), 7);
            $working[2] ^= $this->rotl32($this->add32($working[1], $working[0]), 9);
            $working[3] ^= $this->rotl32($this->add32($working[2], $working[1]), 13);
            $working[0] ^= $this->rotl32($this->add32($working[3], $working[2]), 18);

            $working[6] ^= $this->rotl32($this->add32($working[5], $working[4]), 7);
            $working[7] ^= $this->rotl32($this->add32($working[6], $working[5]), 9);
            $working[4] ^= $this->rotl32($this->add32($working[7], $working[6]), 13);
            $working[5] ^= $this->rotl32($this->add32($working[4], $working[7]), 18);

            $working[11] ^= $this->rotl32($this->add32($working[10], $working[9]), 7);
            $working[8] ^= $this->rotl32($this->add32($working[11], $working[10]), 9);
            $working[9] ^= $this->rotl32($this->add32($working[8], $working[11]), 13);
            $working[10] ^= $this->rotl32($this->add32($working[9], $working[8]), 18);

            $working[12] ^= $this->rotl32($this->add32($working[15], $working[14]), 7);
            $working[13] ^= $this->rotl32($this->add32($working[12], $working[15]), 9);
            $working[14] ^= $this->rotl32($this->add32($working[13], $working[12]), 13);
            $working[15] ^= $this->rotl32($this->add32($working[14], $working[13]), 18);
        }

        foreach ($working as $index => $value) {
            $working[$index] = $this->add32($value, $state[$index]);
        }
        return pack('V*', ...$working);
    }

    private function add32(int $left, int $right): int
    {
        return ($left + $right) & 0xffffffff;
    }

    private function rotl32(int $value, int $shift): int
    {
        $value &= 0xffffffff;
        return (($value << $shift) | ($value >> (32 - $shift))) & 0xffffffff;
    }

    private function updatePortalBusinessProfile(
        string $email,
        string $phoneNumber,
        string $businessName,
        string $businessType,
        string $subdomain,
        string $brandColor,
        array $integrations,
        array $customerFields
    ): array {
        $user = $this->getPortalUserByEmail($email);
        if ($user === null) {
            return [false, "We couldn't find that account."];
        }

        $normalizedSubdomain = strtolower(preg_replace('/[^a-z0-9-]+/', '-', trim($subdomain)) ?? '');
        $normalizedSubdomain = trim(preg_replace('/-{2,}/', '-', $normalizedSubdomain) ?? '', '-');
        $normalizedSubdomain = substr($normalizedSubdomain, 0, 28);
        if (trim($phoneNumber) === '') {
            return [false, 'Please enter a phone number.'];
        }
        if (trim($businessName) === '') {
            return [false, 'Please enter a business name.'];
        }
        if (!in_array($businessType, ['car_rental', 'car_workshop'], true)) {
            return [false, 'Please choose a business type.'];
        }
        if ($normalizedSubdomain === '') {
            return [false, 'Please choose a valid subdomain.'];
        }
        if ($this->subdomainTaken($normalizedSubdomain, $email)) {
            return [false, 'That Yobobot subdomain is already taken.'];
        }

        $resolvedColor = $this->normalizeBrandColor($brandColor);
        $normalizedCustomerFields = $this->normalizeCustomerFieldKeys(
            $customerFields,
            $businessType,
            false,
        );
        $existingProfile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : [];
        $user['business_profile'] = [
            'phone_number' => trim($phoneNumber),
            'business_name' => trim($businessName),
            'business_type' => $businessType,
            'subdomain' => $normalizedSubdomain,
            'brand_color' => $resolvedColor,
            'integrations' => array_values(array_filter(array_map(fn ($value): string => $this->text($value), $integrations))),
            'customer_fields' => $normalizedCustomerFields,
            'biz_id' => $this->text($existingProfile['biz_id'] ?? ''),
        ];
        [$syncOk, $syncMessage] = $this->syncPortalBusinessToSupabase($user);
        [$fieldSyncOk, $fieldSyncMessage] = $this->syncPortalBusinessFieldsToSupabase($user);
        $user['business_profile']['supabase_sync_ok'] = $syncOk;
        $user['business_profile']['supabase_sync_message'] = $syncMessage;
        $user['business_profile']['supabase_fields_sync_ok'] = $fieldSyncOk;
        $user['business_profile']['supabase_fields_sync_message'] = $fieldSyncMessage;
        $this->savePortalUser($user);
        return [true, $normalizedSubdomain];
    }

    private function syncPortalBusinessToSupabase(array &$user): array
    {
        $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : null;
        if ($profile === null) {
            return [false, 'Missing business profile.'];
        }
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            return [false, 'Supabase businesses sync is not configured yet.'];
        }

        $subdomain = strtolower($this->text($profile['subdomain'] ?? ''));
        $businessName = $this->text($profile['business_name'] ?? '');
        $businessType = $this->text($profile['business_type'] ?? 'car_rental') ?: 'car_rental';
        if ($subdomain === '' || $businessName === '') {
            return [false, 'Business name and subdomain are required before syncing.'];
        }

        $existing = $this->fetchSupabaseBusinessRow($subdomain, $this->text($profile['biz_id'] ?? ''));
        $bizId = $this->text($profile['biz_id'] ?? '') ?: $this->text($existing['biz_id'] ?? '') ?: $this->text($existing['id'] ?? '') ?: $this->uuid();
        $user['business_profile']['biz_id'] = $bizId;
        $profile['biz_id'] = $bizId;
        $primaryColor = $this->normalizeBrandColor($profile['brand_color'] ?? '');
        $secondaryColor = $this->mixHexColor($primaryColor, '#ffffff', 0.7);
        $payload = [
            'biz_id' => $bizId,
            'slug' => $subdomain,
            'biz_name' => $businessName,
            'logo_url' => '',
            'brand_color' => $primaryColor,
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'business_type' => $businessType,
            'booking_mode' => $businessType === 'car_workshop' ? 'service' : 'rental',
            'owner_email' => $this->normalizeEmail($user['email'] ?? ''),
            'owner_name' => $this->text($user['full_name'] ?? ''),
            'custom_domain_requested' => false,
            'demo_completed' => false,
            'setup_completed' => false,
            'site_enabled' => false,
        ];

        $method = is_array($existing) ? 'PATCH' : 'POST';
        $workingPayload = $payload;
        $message = 'Unknown error';
        for ($i = 0; $i < max(count($payload), 1); $i++) {
            [$ok, $message] = $this->attemptBusinessWrite($method, $workingPayload, $subdomain, $bizId, $existing);
            if ($ok) {
                return [true, $message];
            }
            $unknownColumn = $this->extractUnknownBusinessColumn($message);
            if ($unknownColumn === null || !array_key_exists($unknownColumn, $workingPayload)) {
                break;
            }
            unset($workingPayload[$unknownColumn]);
        }

        $fallbackKeys = ['biz_id', 'biz_name', 'logo_url', 'brand_color', 'primary_color', 'secondary_color', 'slug'];
        $reducedPayload = array_intersect_key($workingPayload, array_flip($fallbackKeys));
        if ($reducedPayload !== [] && $reducedPayload !== $workingPayload) {
            [$ok, $message] = $this->attemptBusinessWrite($method, $reducedPayload, $subdomain, $bizId, $existing);
            if ($ok) {
                return [true, $message];
            }
        }
        return [false, 'Supabase business sync failed: ' . $message];
    }

    private function attemptBusinessWrite(string $method, array $payload, string $subdomain, string $bizId, ?array $existing): array
    {
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            return [false, 'Supabase is not configured.'];
        }
        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/businesses';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];
        try {
            if ($method === 'PATCH') {
                $filterKey = array_key_exists('slug', (array) $existing) ? 'slug' : 'biz_id';
                $filterValue = $filterKey === 'slug' ? $subdomain : $bizId;
                $response = $this->httpRequest($method, $endpoint . '?' . http_build_query([$filterKey => 'eq.' . $filterValue]), $headers, $payload);
            } else {
                $response = $this->httpRequest($method, $endpoint, $headers, $payload);
            }
            if (in_array($response['status'], [200, 201, 204], true)) {
                return [true, 'Business synced to Supabase.'];
            }
            return [false, $this->extractErrorMessage($response['body'], $response['raw'] ?: 'Unknown error')];
        } catch (RuntimeException $exception) {
            return [false, 'Supabase error: ' . $exception->getMessage()];
        }
    }

    private function syncPortalBusinessFieldsToSupabase(array $user): array
    {
        $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : null;
        if ($profile === null) {
            return [false, 'Missing business profile.'];
        }
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            return [false, 'Supabase booking field sync is not configured yet.'];
        }
        $bizId = $this->text($profile['biz_id'] ?? '');
        $businessType = $this->text($profile['business_type'] ?? 'car_rental') ?: 'car_rental';
        $selectedKeys = $this->normalizeCustomerFieldKeys($profile['customer_fields'] ?? [], $businessType, false);
        if ($bizId === '') {
            return [false, 'Missing biz_id for booking field sync.'];
        }

        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/business_booking_fields';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];
        try {
            $deleteResponse = $this->httpRequest('DELETE', $endpoint . '?' . http_build_query(['biz_id' => 'eq.' . $bizId]), $headers);
            if (!in_array($deleteResponse['status'], [200, 204], true)) {
                return [false, 'Supabase field sync failed: ' . $deleteResponse['raw']];
            }
        } catch (RuntimeException $exception) {
            return [false, 'Supabase field sync error: ' . $exception->getMessage()];
        }

        if ($selectedKeys === []) {
            return [true, 'Bot field setup saved.'];
        }

        $payload = [];
        foreach ($selectedKeys as $index => $fieldKey) {
            $payload[] = [
                'biz_id' => $bizId,
                'field_key' => $fieldKey,
                'field_label' => $this->customerFieldLabel($fieldKey, $businessType),
                'field_order' => $index,
                'is_enabled' => true,
                'is_required' => false,
            ];
        }
        try {
            $response = $this->httpRequest('POST', $endpoint, $headers, $payload);
            if (in_array($response['status'], [200, 201], true)) {
                return [true, 'Bot field setup saved.'];
            }
            return [false, 'Supabase field sync failed: ' . $response['raw']];
        } catch (RuntimeException $exception) {
            return [false, 'Supabase field sync error: ' . $exception->getMessage()];
        }
    }

    private function requestedBizId(): string
    {
        return $this->text($this->query('biz_id')) ?: $this->text($this->getCompanyConfig()['biz_id'] ?? '') ?: $this->getDefaultBizId();
    }

    private function fetchFleetFromSupabase(string $bizId): array
    {
        if ($bizId === '') {
            return [[], false, 'Missing biz_id.'];
        }
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            return [$this->demoFleetItems(), true, 'Supabase not configured.'];
        }

        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/fleet';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
        ];
        $attempt = function (string $selectFields) use ($endpoint, $headers, $bizId): array {
            $params = ['select' => $selectFields, 'available' => 'eq.true', 'biz_id' => 'eq.' . $bizId];
            try {
                $response = $this->httpRequest('GET', $endpoint . '?' . http_build_query($params), $headers);
                if ($response['status'] === 200 && is_array($response['body'])) {
                    return [$response['body'], null];
                }
                return [null, 'Supabase response ' . $response['status'] . ': ' . $response['raw']];
            } catch (RuntimeException $exception) {
                return [null, 'Supabase error: ' . $exception->getMessage()];
            }
        };

        $selects = [
            'id,name,make,model,price_per_day,price_per_week,price_per_month,available,photo_url,color,luxury,category,number_plate,city,branch_city,branch_location,location',
            'id,name,make,model,price_per_day,price_per_week,price_per_month,available,photo_url,color,luxury,category,number_plate',
            'id,name,make,model,price_per_day,price_per_week,price_per_month,available,photo_url,luxury',
            'id,name,make,model,price_per_day,price_per_week,price_per_month,available,photo_url',
        ];
        $error = null;
        foreach ($selects as $select) {
            [$data, $attemptError] = $attempt($select);
            if (is_array($data)) {
                return [$data, false, null];
            }
            $error = $attemptError;
        }
        return [[], false, $error];
    }

    private function fetchInsuranceFromSupabase(string $bizId): array
    {
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            return $this->demoInsuranceItems();
        }

        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/insurance';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
        ];
        $baseParams = ['select' => 'id,name,plan_name,title,code,description,details,price,price_per_day,amount'];
        $attempt = function (array $params) use ($endpoint, $headers): ?array {
            try {
                $response = $this->httpRequest('GET', $endpoint . '?' . http_build_query($params), $headers);
                return ($response['status'] === 200 && is_array($response['body'])) ? $response['body'] : null;
            } catch (RuntimeException) {
                return null;
            }
        };

        $params = $baseParams;
        if ($bizId !== '') {
            $params['biz_id'] = 'eq.' . $bizId;
        }
        $data = $attempt($params);
        if ($data === null && $bizId !== '') {
            $data = $attempt($baseParams);
        }
        return $data ?? $this->demoInsuranceItems();
    }

    private function fetchBookedCarIds(string $bizId, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            return [];
        }
        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/bookings';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
        ];
        $params = [
            'select' => 'car_id,start_date,end_date',
            'start_date' => 'lte.' . $endDate->format('Y-m-d'),
            'end_date' => 'gte.' . $startDate->format('Y-m-d'),
        ];
        if ($bizId !== '') {
            $params['biz_id'] = 'eq.' . $bizId;
        }
        try {
            $response = $this->httpRequest('GET', $endpoint . '?' . http_build_query($params), $headers);
            if ($response['status'] !== 200 || !is_array($response['body'])) {
                return [];
            }
            $ids = [];
            foreach ($response['body'] as $row) {
                if (is_array($row) && isset($row['car_id'])) {
                    $ids[] = (string) $row['car_id'];
                }
            }
            return array_values(array_unique($ids));
        } catch (RuntimeException) {
            return [];
        }
    }

    private function getAvailableFleetForDates(string $bizId, ?DateTimeImmutable $startDate, ?DateTimeImmutable $endDate): array
    {
        if ($bizId === '') {
            return [];
        }
        [$fleet] = $this->fetchFleetFromSupabase($bizId);
        if ($startDate !== null && $endDate !== null) {
            $bookedIds = $this->fetchBookedCarIds($bizId, $startDate, $endDate);
            $fleet = array_values(array_filter($fleet, static fn (array $car): bool => !in_array((string) ($car['id'] ?? ''), $bookedIds, true)));
        }
        return $fleet;
    }

    private function saveBookingToSupabase(array $payload): array
    {
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            return [true, 'Saved in demo mode (no API yet).'];
        }

        $coreFields = ['biz_id', 'customer_name', 'phone', 'total_price', 'start_date', 'end_date', 'city', 'car_id', 'location', 'insurance'];
        $optionalFields = ['email', 'car_make', 'car_model', 'car_name', 'vehicle_model', 'service_id', 'service_name', 'appointment_time', 'booking_mode', 'booking_type', 'current_vehicle_type', 'contract_company', 'contract_expiry', 'citizen_status', 'interest_form_type', 'notes', 'custom_fields', 'custom_field_labels'];
        $allowed = array_flip(array_merge($coreFields, $optionalFields));
        $insertPayload = [];
        foreach ($payload as $key => $value) {
            if (isset($allowed[$key]) && $value !== null && $value !== '') {
                $insertPayload[$key] = $value;
            }
        }

        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/bookings';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];

        $workingPayload = $insertPayload;
        $message = 'Unknown error';
        for ($i = 0; $i < max(count($insertPayload), 1); $i++) {
            try {
                $response = $this->httpRequest('POST', $endpoint, $headers, $workingPayload);
                if (in_array($response['status'], [200, 201], true)) {
                    return [true, 'Booking saved to Supabase.'];
                }
                $message = $this->extractErrorMessage($response['body'], $response['raw'] ?: 'Unknown error');
                $unknownColumn = $this->extractUnknownBookingColumn($message);
                if ($unknownColumn === null || !array_key_exists($unknownColumn, $workingPayload)) {
                    break;
                }
                unset($workingPayload[$unknownColumn]);
            } catch (RuntimeException $exception) {
                $message = 'Supabase error: ' . $exception->getMessage();
                break;
            }
        }
        return [false, $message];
    }

    private function fetchSupabaseBusinessRow(string $slug = '', string $bizId = ''): ?array
    {
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            return null;
        }

        $normalizedSlug = strtolower(trim($slug));
        $normalizedBizId = trim($bizId);
        if ($normalizedSlug === '' && $normalizedBizId === '') {
            return null;
        }

        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/businesses';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
        ];

        $attempts = [];
        if ($normalizedSlug !== '') {
            $attempts[] = ['select' => '*', 'limit' => 1, 'slug' => 'eq.' . $normalizedSlug];
        }
        if ($normalizedBizId !== '') {
            $attempts[] = ['select' => '*', 'limit' => 1, 'biz_id' => 'eq.' . $normalizedBizId];
        }

        foreach ($attempts as $params) {
            try {
                $response = $this->httpRequest('GET', $endpoint . '?' . http_build_query($params), $headers);
                if ($response['status'] === 200 && is_array($response['body']) && $response['body'] !== []) {
                    $first = $response['body'][0] ?? null;
                    if (is_array($first)) {
                        return $first;
                    }
                }
            } catch (RuntimeException) {
                continue;
            }
        }
        return null;
    }

    private function fetchSupabaseBusinessFieldRows(string $bizId, bool $includeDisabled = false): array
    {
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null || $bizId === '') {
            return [];
        }
        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/business_booking_fields';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
        ];
        $params = [
            'select' => 'field_key,field_label,field_order,is_enabled,is_required',
            'biz_id' => 'eq.' . $bizId,
            'order' => 'field_order.asc',
        ];
        try {
            $response = $this->httpRequest('GET', $endpoint . '?' . http_build_query($params), $headers);
            if ($response['status'] !== 200 || !is_array($response['body'])) {
                return [];
            }
            if ($includeDisabled) {
                return $response['body'];
            }
            return array_values(array_filter($response['body'], fn (array $row): bool => $this->coerceBool($row['is_enabled'] ?? true)));
        } catch (RuntimeException) {
            return [];
        }
    }

    private function supabaseBusinessRowToConfig(array $row): array
    {
        $companyName = $this->text($row['biz_name'] ?? '');
        $slug = strtolower($this->text($row['slug'] ?? ''));
        $bookingMode = $this->normalizeBookingMode(
            $row['booking_mode'] ?? (($this->text($row['business_type'] ?? '') === 'car_workshop') ? 'service' : 'rental')
        );

        $profile = $this->defaultCompany();
        $profile['biz_id'] = $this->text($row['biz_id'] ?? '') ?: $this->text($row['id'] ?? '');
        $profile['slug'] = $slug;
        $profile['config_source'] = 'supabase';
        $profile['company_name'] = $companyName !== '' ? $companyName : $profile['company_name'];
        $profile['brand_wordmark'] = $this->text($row['brand_wordmark'] ?? '') ?: ($companyName !== '' ? $companyName : $profile['brand_wordmark']);
        $profile['assistant_name'] = $this->text($row['assistant_name'] ?? '') ?: ($this->text($row['assistant'] ?? '') ?: $profile['assistant_name']);
        $profile['accent'] = $this->text($row['brand_color'] ?? '') ?: ($this->text($row['primary_color'] ?? '') ?: ($this->text($row['secondary_color'] ?? '') ?: ($this->text($row['accent'] ?? '') ?: $profile['accent'])));
        $profile['currency'] = $this->text($row['currency'] ?? '') ?: $profile['currency'];
        $profile['processing_fee'] = $row['processing_fee'] ?? $profile['processing_fee'];
        $profile['vat_rate'] = $row['vat_rate'] ?? $profile['vat_rate'];
        $profile['market_city'] = $this->text($row['market_city'] ?? '');
        $profile['booking_mode'] = $bookingMode;
        $profile['logo_url'] = $this->text($row['logo_url'] ?? '');
        $profile['discount_type'] = strtolower($this->text($row['discount_type'] ?? 'none'));
        $profile['discount_value'] = $row['discount_value'] ?? 0;
        $profile['discount_label'] = $this->text($row['discount_label'] ?? '');
        $profile['site_enabled'] = $this->coerceBool($row['site_enabled'] ?? false);
        $profile['service_catalog'] = $this->normalizeServiceCatalog($row['service_catalog'] ?? [], (string) ($profile['currency'] ?? 'SGD'));
        return $profile;
    }

    private function recordBusinessSiteVisit(string $bizId, string $source): void
    {
        if ($bizId === '') {
            return;
        }
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($supabaseUrl === null || $supabaseKey === null) {
            return;
        }
        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/business_site_visits?on_conflict=biz_id,visitor_token,visit_date';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
            'Prefer: resolution=merge-duplicates,return=representation',
        ];
        try {
            $this->httpRequest('POST', $endpoint, $headers, [
                'biz_id' => $bizId,
                'visitor_token' => $this->getSiteVisitToken(),
                'visit_source' => trim($source) !== '' ? trim($source) : 'site',
                'visit_date' => gmdate('Y-m-d'),
            ]);
        } catch (RuntimeException) {
        }
    }

    private function getSiteVisitToken(): string
    {
        $token = $this->text($_SESSION['site_visit_token'] ?? '');
        if ($token !== '') {
            return $token;
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['site_visit_token'] = $token;
        return $token;
    }

    private function customerFieldCatalog(string $businessType): array
    {
        $mode = $this->businessTypeToBookingMode($businessType);
        $fields = $this->customerDetailFields();
        return array_values(array_filter($fields, static function (array $field) use ($mode): bool {
            $modes = is_array($field['modes'] ?? null) ? $field['modes'] : [];
            return in_array($mode, $modes, true);
        }));
    }

    private function defaultCustomerFieldKeys(string $businessType): array
    {
        $mode = $this->businessTypeToBookingMode($businessType);
        $keys = [];
        foreach ($this->customerFieldCatalog($businessType) as $field) {
            $defaults = is_array($field['default'] ?? null) ? $field['default'] : [];
            if (!empty($defaults[$mode])) {
                $keys[] = $this->text($field['key'] ?? '');
            }
        }
        return $keys;
    }

    private function normalizeCustomerFieldKeys(mixed $values, string $businessType, bool $fallbackToDefault = false): array
    {
        $allowedFields = $this->customerFieldCatalog($businessType);
        $allowedKeys = [];
        foreach ($allowedFields as $field) {
            $allowedKeys[] = $this->text($field['key'] ?? '');
        }
        $allowedKeys = array_values(array_filter($allowedKeys));

        $seen = [];
        $normalized = [];
        $rawValues = is_array($values) ? $values : [];
        foreach ($rawValues as $raw) {
            $key = $this->text($raw);
            if ($key === '' || !in_array($key, $allowedKeys, true) || isset($seen[$key])) {
                continue;
            }
            $normalized[] = $key;
            $seen[$key] = true;
        }

        if ($normalized === [] && $fallbackToDefault) {
            $normalized = $this->defaultCustomerFieldKeys($businessType);
        }
        $normalizedSet = array_flip($normalized);
        $ordered = [];
        foreach ($allowedFields as $field) {
            $key = $this->text($field['key'] ?? '');
            if ($key !== '' && isset($normalizedSet[$key])) {
                $ordered[] = $key;
            }
        }
        return $ordered;
    }

    private function customerFieldLabel(string $fieldKey, string $businessType, array $labelOverrides = []): string
    {
        if (($labelOverrides[$fieldKey] ?? '') !== '') {
            return trim((string) $labelOverrides[$fieldKey]);
        }
        $mode = $this->businessTypeToBookingMode($businessType);
        foreach ($this->customerFieldCatalog($businessType) as $field) {
            if ($this->text($field['key'] ?? '') === $fieldKey) {
                $labels = is_array($field['labels'] ?? null) ? $field['labels'] : [];
                return $this->text($labels[$mode] ?? '') ?: ucwords(str_replace('_', ' ', $fieldKey));
            }
        }
        return ucwords(str_replace('_', ' ', $fieldKey));
    }

    private function customerFieldOptions(string $businessType, mixed $selectedKeys = null): array
    {
        $normalizedSelected = array_flip($this->normalizeCustomerFieldKeys(
            $selectedKeys,
            $businessType,
            $selectedKeys === null,
        ));
        $options = [];
        foreach ($this->customerFieldCatalog($businessType) as $field) {
            $key = $this->text($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $options[] = [
                'key' => $key,
                'label' => $this->customerFieldLabel($key, $businessType),
                'description' => $this->text($field['description'] ?? ''),
                'checked' => isset($normalizedSelected[$key]),
            ];
        }
        return $options;
    }

    private function resolveCustomerFieldConfiguration(string $businessType, ?array $profile = null, ?array $flowConfig = null, string $bizId = ''): array
    {
        if (is_array($profile) && is_array($profile['customer_fields'] ?? null)) {
            return [$this->normalizeCustomerFieldKeys($profile['customer_fields'], $businessType), []];
        }

        $labelOverrides = [];
        if (is_array($flowConfig) && is_array($flowConfig['customer_fields'] ?? null)) {
            $keys = [];
            foreach ($flowConfig['customer_fields'] as $raw) {
                if (is_array($raw)) {
                    $key = $this->text($raw['key'] ?? '');
                    $label = $this->text($raw['label'] ?? '');
                    if ($key !== '' && $label !== '') {
                        $labelOverrides[$key] = $label;
                    }
                    $keys[] = $key;
                } else {
                    $keys[] = $this->text($raw);
                }
            }
            $normalized = $this->normalizeCustomerFieldKeys($keys, $businessType, false);
            if ($normalized !== []) {
                return [$normalized, $labelOverrides];
            }
        }

        if ($bizId !== '') {
            $rows = $this->fetchSupabaseBusinessFieldRows($bizId, true);
            if ($rows !== []) {
                $keys = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $key = $this->text($row['field_key'] ?? '');
                    $label = $this->text($row['field_label'] ?? '');
                    $enabled = $this->coerceBool($row['is_enabled'] ?? true);
                    if ($enabled && $key !== '') {
                        $keys[] = $key;
                    }
                    if ($enabled && $key !== '' && $label !== '') {
                        $labelOverrides[$key] = $label;
                    }
                }
                return [$this->normalizeCustomerFieldKeys($keys, $businessType, false), $labelOverrides];
            }
        }

        if (is_array($flowConfig) && is_array($flowConfig['stage_sequence'] ?? null)) {
            $derived = [];
            $stageMap = [];
            foreach ($this->customerDetailFields() as $field) {
                $stageMap[$this->text($field['stage'] ?? '')] = $this->text($field['key'] ?? '');
            }
            $allowed = array_flip(array_map(fn (array $field): string => $this->text($field['key'] ?? ''), $this->customerFieldCatalog($businessType)));
            foreach ($flowConfig['stage_sequence'] as $rawStage) {
                $stage = strtolower($this->text($rawStage));
                $key = $stageMap[$stage] ?? '';
                if ($key !== '' && isset($allowed[$key]) && !in_array($key, $derived, true)) {
                    $derived[] = $key;
                }
            }
            if ($derived !== []) {
                return [$derived, []];
            }
        }

        return [$this->defaultCustomerFieldKeys($businessType), []];
    }

    private function applyCustomerFieldsToFlowConfig(array $flowConfig, string $businessType, array $selectedKeys, array $labelOverrides = []): array
    {
        $bookingMode = $this->businessTypeToBookingMode($businessType);
        $baseSequenceRaw = is_array($flowConfig['stage_sequence'] ?? null) ? $flowConfig['stage_sequence'] : ($this->baseStageSequences()[$bookingMode] ?? []);
        $baseSequence = [];
        foreach ($baseSequenceRaw as $stage) {
            $stageText = strtolower($this->text($stage));
            if ($stageText !== '') {
                $baseSequence[] = $stageText;
            }
        }
        $selectedSet = array_flip($selectedKeys);
        $stageMap = [];
        foreach ($this->customerDetailFields() as $field) {
            $stageMap[$this->text($field['stage'] ?? '')] = $this->text($field['key'] ?? '');
        }

        $filteredSequence = [];
        foreach ($baseSequence as $stage) {
            $fieldKey = $stageMap[$stage] ?? '';
            if ($fieldKey !== '' && !isset($selectedSet[$fieldKey])) {
                continue;
            }
            if (!in_array($stage, $filteredSequence, true)) {
                $filteredSequence[] = $stage;
            }
        }

        $nextFlow = $flowConfig;
        $nextFlow['stage_sequence'] = $filteredSequence;
        $customerFields = [];
        foreach ($selectedKeys as $fieldKey) {
            $stage = '';
            foreach ($this->customerFieldCatalog($businessType) as $field) {
                if ($this->text($field['key'] ?? '') === $fieldKey) {
                    $stage = $this->text($field['stage'] ?? '');
                    break;
                }
            }
            $customerFields[] = [
                'key' => $fieldKey,
                'stage' => $stage,
                'label' => $this->customerFieldLabel($fieldKey, $businessType, $labelOverrides),
            ];
        }
        $nextFlow['customer_fields'] = $customerFields;

        $labelKeys = $this->customerFieldLabelKeys();
        foreach ($labelKeys as $stage => $labelKey) {
            $fieldKey = $stageMap[$stage] ?? '';
            if ($fieldKey !== '' && isset($selectedSet[$fieldKey]) && empty($nextFlow[$labelKey])) {
                $nextFlow[$labelKey] = $this->customerFieldLabel($fieldKey, $businessType, $labelOverrides);
            }
        }
        return $nextFlow;
    }

    private function portalBookingSiteHref(?array $profile): string
    {
        $subdomain = strtolower($this->text($profile['subdomain'] ?? ''));
        if ($subdomain === '') {
            return '/demo';
        }
        if ($this->coerceBool($profile['supabase_sync_ok'] ?? false) || $this->fetchSupabaseBusinessRow($subdomain, $this->text($profile['biz_id'] ?? '')) !== null) {
            return '/b/' . rawurlencode($subdomain) . '/assistant';
        }
        return '/booking-site/' . rawurlencode($subdomain) . '/assistant';
    }

    private function portalRequestedDomainHref(?array $profile): string
    {
        $subdomain = strtolower($this->text($profile['subdomain'] ?? ''));
        return $subdomain === '' ? 'https://yourbrand.yobobot.in' : 'https://' . $subdomain . '.yobobot.in';
    }

    private function dashboardAccessHrefForPortalUser(?array $user): string
    {
        if (!is_array($user)) {
            return '/dashboard/login';
        }
        $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : [];
        $ok = $this->ensureDashboardOwnerInviteForUser($user);
        $params = ['email' => $this->normalizeEmail($user['email'] ?? '')];
        if ($ok && !empty($profile['biz_id'])) {
            return '/dashboard/register?' . http_build_query($params);
        }
        return '/dashboard/login?' . http_build_query($params);
    }

    private function ensureDashboardOwnerInviteForUser(array $user): bool
    {
        $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : null;
        if ($profile === null) {
            return false;
        }
        $bizId = $this->text($profile['biz_id'] ?? '');
        $businessName = $this->text($profile['business_name'] ?? '');
        $ownerEmail = $this->normalizeEmail($user['email'] ?? '');
        $ownerName = $this->text($user['full_name'] ?? '');
        $supabaseUrl = $this->getSupabaseUrl();
        $supabaseKey = $this->getSupabaseServiceKey();
        if ($bizId === '' || $businessName === '' || $ownerEmail === '' || $supabaseUrl === null || $supabaseKey === null) {
            return false;
        }
        $endpoint = rtrim($supabaseUrl, '/') . '/rest/v1/employee_invites?on_conflict=email';
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: application/json',
            'Prefer: resolution=merge-duplicates,return=representation',
        ];
        try {
            $this->httpRequest('POST', $endpoint, $headers, [
                'email' => $ownerEmail,
                'biz_id' => $bizId,
                'company_name' => $businessName,
                'full_name' => $ownerName !== '' ? $ownerName : $businessName,
                'role' => 'owner',
                'is_active' => true,
                'invited_by' => $ownerEmail,
                'invite_token' => bin2hex(random_bytes(16)),
            ]);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function portalDashboardTarget(?array $user): string
    {
        return $this->portalProfileIsComplete($user) ? '/workspace' : '/onboarding';
    }

    private function portalProfileIsComplete(?array $user): bool
    {
        if (!is_array($user)) {
            return false;
        }
        $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : null;
        if ($profile === null) {
            return false;
        }
        foreach (['phone_number', 'business_name', 'business_type', 'subdomain'] as $field) {
            if ($this->text($profile[$field] ?? '') === '') {
                return false;
            }
        }
        return true;
    }

    private function getLoggedInPortalUser(): ?array
    {
        $email = $this->normalizeEmail($_SESSION['portal_user_email'] ?? '');
        return $email === '' ? null : $this->getPortalUserByEmail($email);
    }

    private function requirePortalUser(): ?array
    {
        $user = $this->getLoggedInPortalUser();
        if ($user === null) {
            $this->redirect('/account/login?next=' . rawurlencode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
            return null;
        }
        return $user;
    }

    private function clearAuthSessions(): void
    {
        unset($_SESSION['portal_user_email'], $_SESSION['employee']);
    }

    private function buildInitials(mixed $value): string
    {
        $text = $this->text($value);
        if ($text === '') {
            return 'YO';
        }
        $parts = preg_split('/\s+/', str_replace('@', ' ', $text)) ?: [];
        $initials = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $initials[] = strtoupper(substr($part, 0, 1));
            }
        }
        if ($initials === []) {
            return 'YO';
        }
        if (count($initials) === 1) {
            return substr(($initials[0] . strtoupper(substr($text, 1, 1))), 0, 2);
        }
        return implode('', array_slice($initials, 0, 2));
    }

    private function businessTypeToBookingMode(mixed $value): string
    {
        return $this->text($value) === 'car_workshop' ? 'service' : 'rental';
    }

    private function normalizeBookingMode(mixed $value): string
    {
        $lowered = strtolower($this->text($value));
        return in_array($lowered, ['service', 'services', 'appointment', 'appointments', 'mechanic', 'mechanics'], true) ? 'service' : 'rental';
    }

    private function normalizeServiceCatalog(mixed $items, string $currency = 'SGD'): array
    {
        if (!is_array($items)) {
            return [];
        }
        $normalized = [];
        foreach ($items as $index => $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }
            $name = $this->text($rawItem['name'] ?? '') ?: $this->text($rawItem['title'] ?? '');
            if ($name === '') {
                continue;
            }
            $itemId = $this->text($rawItem['id'] ?? '') ?: ($this->text($rawItem['code'] ?? '') ?: 'service-' . ($index + 1));
            $description = $this->text($rawItem['description'] ?? '') ?: $this->text($rawItem['details'] ?? '');
            $photoUrl = $this->text($rawItem['photo_url'] ?? '') ?: $this->text($rawItem['image'] ?? '');
            $price = 0;
            if (($rawItem['price'] ?? null) !== null && $rawItem['price'] !== '') {
                $price = (int) round((float) $rawItem['price']);
            }
            $durationLabel = $this->text($rawItem['duration_label'] ?? '') ?: $this->text($rawItem['duration'] ?? '');
            if ($durationLabel === '' && ($rawItem['duration_minutes'] ?? null) !== null && $rawItem['duration_minutes'] !== '') {
                $durationLabel = $rawItem['duration_minutes'] . ' min';
            }
            $priceLabel = $this->text($rawItem['price_label'] ?? '');
            if ($priceLabel === '' && $price > 0) {
                $priceLabel = $currency . ' ' . $price;
            }
            $normalized[] = [
                'id' => $itemId,
                'name' => $name,
                'description' => $description,
                'photo_url' => $photoUrl,
                'price' => $price,
                'price_label' => $priceLabel,
                'duration_label' => $durationLabel,
            ];
        }
        return $normalized;
    }

    private function buildThemeVars(string $accent): array
    {
        $resolved = $this->normalizeHexColor($accent, '#f59a3c');
        $rgb = [];
        foreach ([1, 3, 5] as $index) {
            $rgb[] = (string) hexdec(substr($resolved, $index, 2));
        }
        return [
            'themeAccent' => $this->mixHexColor($resolved, '#ffffff', 0.72),
            'themeAccentDeep' => $resolved,
            'themeAccentRgb' => implode(', ', $rgb),
            'themeBorder' => $this->mixHexColor($resolved, '#ffffff', 0.82),
            'themeBg' => $this->mixHexColor($resolved, '#ffffff', 0.95),
            'themeSoft' => $this->mixHexColor($resolved, '#ffffff', 0.88),
        ];
    }

    private function normalizeBrandColor(mixed $value): string
    {
        return $this->normalizeHexColor((string) $value, '#f59a3c');
    }

    private function normalizeHexColor(string $value, string $fallback): string
    {
        $candidate = ltrim(trim($value), '#');
        if (strlen($candidate) === 3 && preg_match('/^[0-9a-fA-F]{3}$/', $candidate)) {
            $candidate = $candidate[0] . $candidate[0] . $candidate[1] . $candidate[1] . $candidate[2] . $candidate[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $candidate)) {
            $candidate = ltrim($fallback, '#');
        }
        return '#' . strtolower($candidate);
    }

    private function mixHexColor(string $color, string $target, float $ratio): string
    {
        $ratio = max(0.0, min(1.0, $ratio));
        $source = ltrim($this->normalizeHexColor($color, '#f59a3c'), '#');
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

    private function getSupabaseUrl(): ?string
    {
        return $this->envValue('SUPABASE_URL');
    }

    private function getSupabaseServiceKey(): ?string
    {
        $candidates = [
            $this->envValue('SUPABASE_SERVICE_KEY'),
            $this->envValue('SUPABASE_KEY'),
            $this->envValue('SUPABASE_ANON_KEY'),
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }
        return null;
    }

    private function getGeminiConfig(): array
    {
        $apiKey = $this->envValue('GEMINI_API_KEY');
        $model = $this->envValue('GEMINI_MODEL') ?? 'gemini-2.5-flash';
        $model = trim((string) $model);
        if (str_starts_with($model, 'models/')) {
            $model = substr($model, strlen('models/'));
        }
        return [is_string($apiKey) ? trim($apiKey) : null, $model];
    }

    private function getDefaultBizId(): string
    {
        $candidates = [
            $this->envValue('SUPABASE_BIZ_ID'),
            $this->envValue('BIZ_ID'),
            $this->envValue('biz_id'),
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }
        return '';
    }

    private function normalizedRequestHost(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $host = explode(':', $host, 2)[0] ?? $host;
        return rtrim($host, '.');
    }

    private function requestBusinessSlugFromHost(): string
    {
        $host = $this->normalizedRequestHost();
        $primarySiteDomain = strtolower(trim((string) (getenv('PRIMARY_SITE_DOMAIN') ?: 'yobobot.in')));
        if ($host === '' || $primarySiteDomain === '') {
            return '';
        }
        if (in_array($host, [$primarySiteDomain, 'www.' . $primarySiteDomain, 'localhost', '127.0.0.1'], true)) {
            return '';
        }
        $suffix = '.' . $primarySiteDomain;
        return str_ends_with($host, $suffix) ? trim(substr($host, 0, -strlen($suffix))) : '';
    }

    private function subdomainTaken(mixed $subdomain, string $excludingEmail = ''): bool
    {
        $normalizedSubdomain = strtolower(trim((string) $subdomain));
        $excluded = $this->normalizeEmail($excludingEmail);
        if ($normalizedSubdomain === '') {
            return false;
        }
        $store = $this->loadPortalStore();
        foreach (($store['users'] ?? []) as $email => $user) {
            if ($this->normalizeEmail($email) === $excluded || !is_array($user)) {
                continue;
            }
            $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : null;
            if ($profile !== null && strtolower($this->text($profile['subdomain'] ?? '')) === $normalizedSubdomain) {
                return true;
            }
        }
        return false;
    }

    private function loadPortalStore(): array
    {
        if (!is_file($this->portalUsersPath)) {
            return ['users' => []];
        }
        $raw = json_decode((string) file_get_contents($this->portalUsersPath), true);
        if (!is_array($raw) || !is_array($raw['users'] ?? null)) {
            return ['users' => []];
        }
        return ['users' => $raw['users']];
    }

    private function savePortalStore(array $store): void
    {
        $payload = ['users' => is_array($store['users'] ?? null) ? $store['users'] : []];
        $this->ensureParentDirectory($this->portalUsersPath);
        file_put_contents($this->portalUsersPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    private function getPortalUserByEmail(mixed $email): ?array
    {
        $normalized = $this->normalizeEmail($email);
        if ($normalized === '') {
            return null;
        }
        $store = $this->loadPortalStore();
        $user = $store['users'][$normalized] ?? null;
        return is_array($user) ? $user : null;
    }

    private function savePortalUser(array $user): void
    {
        $email = $this->normalizeEmail($user['email'] ?? '');
        if ($email === '') {
            throw new RuntimeException('Portal user email is required.');
        }
        $store = $this->loadPortalStore();
        $user['email'] = $email;
        $store['users'][$email] = $user;
        $this->savePortalStore($store);
    }

    private function getPortalUserBySubdomain(mixed $subdomain): ?array
    {
        $normalizedSubdomain = strtolower(trim((string) $subdomain));
        if ($normalizedSubdomain === '') {
            return null;
        }
        $store = $this->loadPortalStore();
        foreach (($store['users'] ?? []) as $user) {
            if (!is_array($user)) {
                continue;
            }
            $profile = is_array($user['business_profile'] ?? null) ? $user['business_profile'] : null;
            if ($profile !== null && strtolower($this->text($profile['subdomain'] ?? '')) === $normalizedSubdomain) {
                return $user;
            }
        }
        return null;
    }

    private function loadPlatformData(): array
    {
        if ($this->platformData !== null) {
            return $this->platformData;
        }
        if (!is_file($this->platformDataPath)) {
            throw new RuntimeException('Missing php_yobobot/config/platform_data.json.');
        }
        $decoded = json_decode((string) file_get_contents($this->platformDataPath), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('platform_data.json is invalid.');
        }
        $this->platformData = $decoded;
        return $decoded;
    }

    private function loadBrandingStore(): array
    {
        if ($this->brandingStore !== null) {
            return $this->brandingStore;
        }
        if (!is_file($this->brandingPath)) {
            $this->brandingStore = ['businesses' => []];
            return $this->brandingStore;
        }
        $decoded = json_decode((string) file_get_contents($this->brandingPath), true);
        $this->brandingStore = is_array($decoded) ? $decoded : ['businesses' => []];
        return $this->brandingStore;
    }

    private function loadSecrets(): array
    {
        if ($this->secrets !== null) {
            return $this->secrets;
        }
        $data = [];
        if (is_file($this->secretsPath)) {
            $lines = file($this->secretsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $trimmed, 2));
                $data[$key] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
        $this->secrets = $data;
        return $data;
    }

    private function envValue(string $key): ?string
    {
        $runtimeValue = $this->runtimeEnvValue($key);
        if ($runtimeValue !== null) {
            return $runtimeValue;
        }
        $secrets = $this->loadSecrets();
        return isset($secrets[$key]) && trim((string) $secrets[$key]) !== '' ? trim((string) $secrets[$key]) : null;
    }

    private function resolveDataDir(): string
    {
        $override = $this->runtimeEnvValue('YOBOBOT_DATA_DIR') ?? $this->runtimeEnvValue('APP_DATA_DIR');
        if ($override !== null) {
            return rtrim($override, DIRECTORY_SEPARATOR);
        }

        $legacyProjectRoot = dirname($this->rootDir);
        $legacyCandidates = [
            $legacyProjectRoot . DIRECTORY_SEPARATOR . 'portal_users.json',
            $legacyProjectRoot . DIRECTORY_SEPARATOR . 'dashboard_branding.json',
            $legacyProjectRoot . DIRECTORY_SEPARATOR . 'secrets.toml',
        ];
        foreach ($legacyCandidates as $candidate) {
            if (is_file($candidate)) {
                return $legacyProjectRoot;
            }
        }

        return $this->rootDir . DIRECTORY_SEPARATOR . 'storage';
    }

    private function resolveDataFilePath(array $envKeys, string $filename): string
    {
        foreach ($envKeys as $envKey) {
            $value = $this->runtimeEnvValue($envKey);
            if ($value !== null) {
                return $value;
            }
        }
        return $this->dataDir . DIRECTORY_SEPARATOR . $filename;
    }

    private function resolveSecretsPath(): string
    {
        $override = $this->runtimeEnvValue('YOBOBOT_SECRETS_PATH') ?? $this->runtimeEnvValue('SECRETS_TOML_PATH');
        if ($override !== null) {
            return $override;
        }

        $legacyPath = dirname($this->rootDir) . DIRECTORY_SEPARATOR . 'secrets.toml';
        if (is_file($legacyPath)) {
            return $legacyPath;
        }

        $localPath = $this->rootDir . DIRECTORY_SEPARATOR . 'secrets.toml';
        if (is_file($localPath)) {
            return $localPath;
        }

        return $this->dataDir . DIRECTORY_SEPARATOR . 'secrets.toml';
    }

    private function ensureParentDirectory(string $path): void
    {
        $directory = dirname($path);
        if ($directory === '' || $directory === '.' || is_dir($directory)) {
            return;
        }
        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create data directory: ' . $directory);
        }
    }

    private function runtimeEnvValue(string $key): ?string
    {
        $value = getenv($key);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
        $serverValue = $_SERVER[$key] ?? null;
        if (is_string($serverValue) && trim($serverValue) !== '') {
            return trim($serverValue);
        }
        $envValue = $_ENV[$key] ?? null;
        if (is_string($envValue) && trim($envValue) !== '') {
            return trim($envValue);
        }
        return null;
    }

    private function marketingPlans(): array
    {
        return is_array($this->loadPlatformData()['marketing_plans'] ?? null) ? $this->loadPlatformData()['marketing_plans'] : [];
    }

    private function integrationGuides(): array
    {
        return is_array($this->loadPlatformData()['integration_guides'] ?? null) ? $this->loadPlatformData()['integration_guides'] : [];
    }

    private function defaultCompany(): array
    {
        return is_array($this->loadPlatformData()['default_company'] ?? null) ? $this->loadPlatformData()['default_company'] : [];
    }

    private function demoFleetItems(): array
    {
        return is_array($this->loadPlatformData()['demo_fleet'] ?? null) ? $this->loadPlatformData()['demo_fleet'] : [];
    }

    private function demoInsuranceItems(): array
    {
        return is_array($this->loadPlatformData()['demo_insurance'] ?? null) ? $this->loadPlatformData()['demo_insurance'] : [];
    }

    private function customerDetailFields(): array
    {
        return is_array($this->loadPlatformData()['customer_detail_fields'] ?? null) ? $this->loadPlatformData()['customer_detail_fields'] : [];
    }

    private function customerFieldLabelKeys(): array
    {
        return is_array($this->loadPlatformData()['customer_field_label_keys'] ?? null) ? $this->loadPlatformData()['customer_field_label_keys'] : [];
    }

    private function baseStageSequences(): array
    {
        return is_array($this->loadPlatformData()['base_stage_sequences'] ?? null) ? $this->loadPlatformData()['base_stage_sequences'] : [];
    }

    private function httpRequest(string $method, string $url, array $headers, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required.');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $caBundle = $this->resolveCaBundlePath();
        if ($caBundle !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        if ($raw === false) {
            throw new RuntimeException($error !== '' ? $error : 'Unknown cURL error');
        }
        $trimmed = trim((string) $raw);
        $decoded = null;
        if ($trimmed !== '') {
            $parsed = json_decode($trimmed, true);
            $decoded = json_last_error() === JSON_ERROR_NONE ? $parsed : $trimmed;
        }
        return ['status' => $status, 'body' => $decoded, 'raw' => (string) $raw];
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
            if (is_string($candidate)) {
                $path = trim($candidate);
                if ($path !== '' && is_file($path) && is_readable($path)) {
                    return $path;
                }
            }
        }
        return null;
    }

    private function parseChatDates(string $text, string $fallbackStart = '', string $fallbackEnd = ''): array
    {
        preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', $text, $matches);
        $found = $matches[0] ?? [];
        $startRaw = $found[0] ?? $fallbackStart;
        $endRaw = $found[1] ?? $fallbackEnd;
        return [$this->parseDate($startRaw), $this->parseDate($endRaw)];
    }

    private function formatFleetLines(array $fleet, int $limit = 8): array
    {
        $lines = [];
        foreach (array_slice($fleet, 0, $limit) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $make = $this->text($item['make'] ?? '-') ?: '-';
            $model = $this->text($item['model'] ?? '-') ?: '-';
            $price = $item['price_per_day'] ?? '-';
            $lines[] = $make . ' ' . $model . ' - SGD ' . $price . '/day';
        }
        return $lines;
    }

    private function formatServiceLines(array $catalog, string $currency = 'SGD', int $limit = 8): array
    {
        $lines = [];
        foreach (array_slice($catalog, 0, $limit) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = $this->text($item['name'] ?? '-') ?: '-';
            $description = $this->text($item['description'] ?? '');
            $duration = $this->text($item['duration_label'] ?? '');
            $priceLabel = $this->text($item['price_label'] ?? '');
            if ($priceLabel === '' && ($item['price'] ?? null) !== null && $item['price'] !== '') {
                $priceLabel = $currency . ' ' . $item['price'];
            }
            $bits = array_values(array_filter([$duration, $priceLabel, $description]));
            $lines[] = $bits !== [] ? $name . ' - ' . implode('; ', $bits) : $name;
        }
        return $lines;
    }

    private function extractUnknownBusinessColumn(string $errorText): ?string
    {
        foreach ([
            "/Could not find the '([^']+)' column of 'businesses'/",
            '/column "([^"]+)" of relation "businesses" does not exist/',
        ] as $pattern) {
            if (preg_match($pattern, $errorText, $matches) === 1) {
                return $matches[1];
            }
        }
        return null;
    }

    private function extractUnknownBookingColumn(string $errorText): ?string
    {
        foreach ([
            "/Could not find the '([^']+)' column of 'bookings'/",
            '/column "([^"]+)" of relation "bookings" does not exist/',
        ] as $pattern) {
            if (preg_match($pattern, $errorText, $matches) === 1) {
                return $matches[1];
            }
        }
        return null;
    }

    private function extractErrorMessage(mixed $payload, string $fallback): string
    {
        if (is_array($payload)) {
            foreach (['msg', 'message', 'error_description', 'error', 'hint'] as $key) {
                if (!empty($payload[$key])) {
                    return trim((string) $payload[$key]);
                }
            }
        }
        if (is_string($payload) && trim($payload) !== '') {
            return trim($payload);
        }
        return $fallback;
    }

    private function render(string $view, array $vars = []): void
    {
        $file = $this->viewsDir . DIRECTORY_SEPARATOR . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Missing view: {$view}.php");
        }

        $h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $json = static fn (mixed $value): string => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        extract($vars, EXTR_SKIP);
        header('Content-Type: text/html; charset=utf-8');
        require $file;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function redirect(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }

    private function abort404(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not Found';
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function post(string $key): mixed
    {
        return $_POST[$key] ?? null;
    }

    private function postList(string $key): array
    {
        $value = $_POST[$key] ?? [];
        if (!is_array($value)) {
            return $value === null ? [] : [$value];
        }
        return $value;
    }

    private function query(string $key): mixed
    {
        return $_GET[$key] ?? null;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone('UTC'));
        return $date instanceof DateTimeImmutable ? $date : null;
    }

    private function normalizeEmail(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function isValidEmail(mixed $value): bool
    {
        $normalized = $this->normalizeEmail($value);
        return $normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function resolveFontCss(string $fontKey, string $fallbackCss): string
    {
        if ($fontKey !== '' && isset(self::FONT_CHOICES[$fontKey])) {
            return self::FONT_CHOICES[$fontKey];
        }
        return $fallbackCss !== '' ? $fallbackCss : self::FONT_CHOICES['playfair'];
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }

    private function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
