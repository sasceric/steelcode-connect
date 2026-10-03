<?php

namespace App\Controller\Api;

use App\Billing\MonthlyInvoiceGenerator;
use App\Billing\PlanCatalog;
use App\Entity\Address;
use App\Entity\Invoice;
use App\Entity\Locale;
use App\Entity\PaymentMethod;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/tenant')]
final class TenantController extends AbstractController
{
    public function __construct(
        private ParameterBagInterface $parameters,
        private TranslatorInterface $translator,
        private MonthlyInvoiceGenerator $invoiceGenerator,
    ) {
    }
    #[Route('', name: 'api_v1_tenant_show', methods: ['GET'])]
    public function show(EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant, $membership] = $this->tenantContext($entityManager);

        return $this->json(['tenant' => $this->tenantPayload($tenant, $membership)]);
    }

    #[Route('/locales', name: 'api_v1_tenant_locales', methods: ['GET'])]
    public function locales(EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $enabled = array_flip($tenant->getEnabledSnippetLocales());
        $locales = $entityManager->getRepository(Locale::class)->findBy(
            ['active' => true],
            ['code' => 'ASC'],
        );

        return $this->json([
            'locales' => array_map(
                static fn (Locale $locale): array => [
                    'code' => $locale->getCode(),
                    'label' => \Locale::getDisplayRegion(
                        str_replace('-', '_', $locale->getCode()),
                        strtolower(explode('-', $locale->getCode())[0]),
                    ).' - '.$locale->getCode(),
                    'enabled' => isset($enabled[$locale->getCode()]),
                ],
                $locales,
            ),
        ]);
    }

    #[Route('', name: 'api_v1_tenant_update', methods: ['PATCH'])]
    public function update(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant, $membership] = $this->tenantContext($entityManager, true);
        $payload = $this->payload($request);

        $name = $this->requiredText($payload['name'] ?? $tenant->getName(), 'Company name', 255);
        $email = $this->nullableText($payload['email'] ?? null, 'Email', 180);

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'A valid company email address is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $website = $this->nullableText($payload['website'] ?? null, 'Website', 255);
        if ($website !== null && !filter_var($website, FILTER_VALIDATE_URL)) {
            return $this->json(['message' => 'Website must be a valid URL, including https://.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $defaultSnippetLocale = (string) (
            $payload['defaultSnippetLocale'] ?? $tenant->getDefaultSnippetLocale()
        );
        $locale = $entityManager->getRepository(Locale::class)->findOneBy([
            'code' => $defaultSnippetLocale,
            'active' => true,
        ]);
        if (!$locale instanceof Locale) {
            return $this->json(
                ['message' => 'A valid default snippets language is required.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $enabledSnippetLocales = $this->enabledSnippetLocales(
            $payload['enabledSnippetLocales'] ?? $tenant->getEnabledSnippetLocales(),
            $entityManager,
        );
        if ($enabledSnippetLocales === null) {
            return $this->json(
                ['message' => 'Select at least one valid snippets language.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
        if (!in_array($defaultSnippetLocale, $enabledSnippetLocales, true)) {
            return $this->json(
                ['message' => 'The default snippets language must remain enabled.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $tenant->setName($name);
        $tenant->setOib($this->nullableText($payload['oib'] ?? null, 'OIB', 32));
        $tenant->setPdv($this->nullableText($payload['pdv'] ?? null, 'PDV', 32));
        $tenant->setPhone($this->nullableText($payload['phone'] ?? null, 'Phone', 32));
        $tenant->setEmail($email);
        $tenant->setWebsite($website);
        $tenant->setDefaultSnippetLocale($defaultSnippetLocale);
        $tenant->setEnabledSnippetLocales($enabledSnippetLocales);
        $entityManager->flush();

        return $this->json(['tenant' => $this->tenantPayload($tenant, $membership)]);
    }

    #[Route('/addresses', name: 'api_v1_tenant_addresses', methods: ['GET'])]
    public function addresses(EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager);
        $addresses = $entityManager->getRepository(Address::class)->findBy(['tenant' => $tenant], ['createdAt' => 'DESC']);

        return $this->json(['addresses' => array_map($this->addressPayload(...), $addresses)]);
    }

    #[Route('/addresses', name: 'api_v1_tenant_address_create', methods: ['POST'])]
    public function createAddress(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $address = new Address($tenant, $this->addressData($this->payload($request)));
        $entityManager->persist($address);
        $entityManager->flush();

        return $this->json(['address' => $this->addressPayload($address)], Response::HTTP_CREATED);
    }

    #[Route('/addresses/{id}', name: 'api_v1_tenant_address_update', methods: ['PATCH'])]
    public function updateAddress(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $address = $this->addressForTenant($id, $tenant, $entityManager);
        $address->update($this->addressData($this->payload($request)));
        $entityManager->flush();

        return $this->json(['address' => $this->addressPayload($address)]);
    }

    #[Route('/addresses/{id}', name: 'api_v1_tenant_address_delete', methods: ['DELETE'])]
    public function deleteAddress(string $id, EntityManagerInterface $entityManager): Response
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $entityManager->remove($this->addressForTenant($id, $tenant, $entityManager));
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/addresses/{id}/default', methods: ['POST'])]
    public function defaultAddress(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $address = $this->addressForTenant($id, $tenant, $entityManager);
        foreach ($entityManager->getRepository(Address::class)->findBy(['tenant' => $tenant]) as $item) {
            $item->setDefault($item === $address);
        }
        $entityManager->flush();

        return $this->json(['address' => $this->addressPayload($address)]);
    }

    #[Route('/payment-methods', methods: ['GET'])]
    public function paymentMethods(EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager);
        $items = $entityManager->getRepository(PaymentMethod::class)->findBy(['tenant' => $tenant]);

        return $this->json(['paymentMethods' => array_map($this->paymentPayload(...), $items)]);
    }
    #[Route('/payment-methods', methods: ['POST'])]
    public function createPaymentMethod(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $method = new PaymentMethod($tenant, $this->paymentData($this->payload($request)));
        $entityManager->persist($method);
        $entityManager->flush();

        return $this->json(['paymentMethod' => $this->paymentPayload($method)], Response::HTTP_CREATED);
    }
    #[Route('/payment-methods/{id}', methods: ['PATCH'])]
    public function updatePaymentMethod(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $method = $this->paymentForTenant($id, $tenant, $entityManager);
        $method->update($this->paymentData($this->payload($request)));
        $entityManager->flush();

        return $this->json(['paymentMethod' => $this->paymentPayload($method)]);
    }
    #[Route('/payment-methods/{id}', methods: ['DELETE'])]
    public function deletePaymentMethod(string $id, EntityManagerInterface $entityManager): Response
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $entityManager->remove($this->paymentForTenant($id, $tenant, $entityManager));
        $entityManager->flush();

        return new Response(status:204);
    }
    #[Route('/payment-methods/{id}/default', methods: ['POST'])]
    public function defaultPaymentMethod(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $method = $this->paymentForTenant($id, $tenant, $entityManager);
        foreach ($entityManager->getRepository(PaymentMethod::class)->findBy(['tenant' => $tenant]) as $item) {
            $item->setDefault($item === $method);
        }$entityManager->flush();

        return $this->json(['paymentMethod' => $this->paymentPayload($method)]);
    }

    #[Route('/subscription', methods: ['GET'])]
    public function subscription(EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager);

        return $this->json(['plans' => PlanCatalog::all(), 'subscription' => $this->subscriptionPayload($tenant)]);
    }

    #[Route('/subscription', methods: ['PUT'])]
    public function selectSubscription(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager, true);
        $payload = $this->payload($request);
        $plan = PlanCatalog::find((string) ($payload['plan'] ?? ''));
        if ($plan === null) {
            return $this->json(['message' => 'The selected plan is not available.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $interval = $payload['interval'] ?? 'monthly';
        if (!in_array($interval, ['monthly', 'annual'], true)) {
            return $this->json(['message' => 'Billing interval must be monthly or annual.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $price = $interval === 'annual' ? (int) ($plan['price'] * 12 * 0.9) : $plan['price'];
        $tenant->setSubscription($plan['code'], $price, $interval);
        $entityManager->flush();
        $this->invoiceGenerator->createInitialAnnualInvoice($tenant);

        return $this->json(['subscription' => $this->subscriptionPayload($tenant)]);
    }

    #[Route('/invoices', methods: ['GET'])]
    public function invoices(EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager);
        $items = $entityManager->getRepository(Invoice::class)->findBy(['tenant' => $tenant], ['billingPeriod' => 'DESC']);

        return $this->json(['invoices' => array_map($this->invoicePayload(...), $items)]);
    }

    #[Route('/invoices/{id}', methods: ['GET'])]
    public function invoice(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        [$tenant] = $this->tenantContext($entityManager);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $invoice = $entityManager->getRepository(Invoice::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]);
        if (!$invoice instanceof Invoice) {
            throw $this->createNotFoundException();
        }

        $subtotal = $invoice->getAmount();
        $pdv = (int) round($subtotal * 0.17);
        $address = $entityManager->getRepository(Address::class)->findOneBy(['tenant' => $tenant, 'isDefault' => true]);

        return $this->json([
            'invoice' => [...$this->invoicePayload($invoice), 'subtotal' => $subtotal, 'pdvRate' => 17, 'pdv' => $pdv, 'total' => $subtotal + $pdv],
            'issuer' => $this->parameters->get('invoice_issuer'),
            'customer' => ['name' => $tenant->getName(), 'oib' => $tenant->getOib(), 'pdv' => $tenant->getPdv(), 'email' => $tenant->getEmail(), 'phone' => $tenant->getPhone(), 'address' => $address instanceof Address ? $this->addressPayload($address) : null],
        ]);
    }

    #[Route('/invoices/{id}/pdf', methods: ['GET'])]
    public function pdfInvoice(string $id, EntityManagerInterface $entityManager): Response
    {
        [$tenant] = $this->tenantContext($entityManager);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $invoice = $entityManager->getRepository(Invoice::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]);
        if (!$invoice instanceof Invoice) {
            throw $this->createNotFoundException();
        }

        $address = $entityManager->getRepository(Address::class)->findOneBy(['tenant' => $tenant, 'isDefault' => true]);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $user = $this->getUser();
        $locale = $user instanceof User ? $user->getLocale() : 'bs';
        $pdf->loadHtml($this->invoicePdfHtml($invoice, $tenant, $address instanceof Address ? $address : null, $locale));
        $pdf->setPaper('A4');
        $pdf->render();

        $response = new Response($pdf->output(), Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s.pdf"', $invoice->getNumber()));

        return $response;
    }

    /** @return array{Tenant, TenantMembership} */
    private function tenantContext(EntityManagerInterface $entityManager, bool $ownerRequired = false): array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $entityManager->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership || ($ownerRequired && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }

        return [$membership->getTenant(), $membership];
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            throw $this->createNotFoundException('A JSON request body is required.');
        }
    }

    /** @return array<string, ?string> */
    private function addressData(array $payload): array
    {
        $country = strtoupper($this->requiredText($payload['country'] ?? null, 'Country', 2));
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            throw $this->createNotFoundException('Country must be a two-letter ISO code.');
        }

        return [
            'country' => $country,
            'company' => $this->nullableText($payload['company'] ?? null, 'Company', 255),
            'department' => $this->nullableText($payload['department'] ?? null, 'Department', 255),
            'street' => $this->requiredText($payload['street'] ?? null, 'Street', 255),
            'zipcode' => $this->requiredText($payload['zipcode'] ?? null, 'ZIP code', 32),
            'city' => $this->requiredText($payload['city'] ?? null, 'City', 255),
            'countryState' => $this->nullableText($payload['countryState'] ?? null, 'Country state', 255),
            'phone' => $this->nullableText($payload['phone'] ?? null, 'Phone', 32),
            'additionalAddressLine1' => $this->nullableText($payload['additionalAddressLine1'] ?? null, 'Additional address line 1', 255),
            'additionalAddressLine2' => $this->nullableText($payload['additionalAddressLine2'] ?? null, 'Additional address line 2', 255),
        ];
    }

    private function addressForTenant(string $id, Tenant $tenant, EntityManagerInterface $entityManager): Address
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $address = $entityManager->getRepository(Address::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]);
        if (!$address instanceof Address) {
            throw $this->createNotFoundException();
        }

        return $address;
    }
    private function paymentForTenant(string $id, Tenant $tenant, EntityManagerInterface $em): PaymentMethod
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }$item = $em->getRepository(PaymentMethod::class)->findOneBy(['id' => Uuid::fromString($id),'tenant' => $tenant]);
        if (!$item instanceof PaymentMethod) {
            throw $this->createNotFoundException();
        }

return $item;
    }
    private function paymentData(array $p): array
    {
        $type = $this->requiredText($p['type'] ?? null, 'Type', 64);
        $provider = $this->requiredText($p['provider'] ?? null, 'Provider', 100);
        $label = $this->requiredText($p['label'] ?? null, 'Label', 255);

        return ['type' => $type,'provider' => $provider,'label' => $label,'details' => is_array($p['details'] ?? null) ? $p['details'] : [],'active' => (bool)($p['active'] ?? true)];
    }
    private function paymentPayload(PaymentMethod $p): array
    {
        return ['id' => $p->getId()->toRfc4122(),'type' => $p->getType(),'provider' => $p->getProvider(),'label' => $p->getLabel(),'details' => $p->getDetails(),'active' => $p->isActive(),'isDefault' => $p->isDefault()];
    }
    private function subscriptionPayload(Tenant $tenant): ?array
    {
        $plan = $tenant->getSubscriptionPlan() === null ? null : PlanCatalog::find($tenant->getSubscriptionPlan());
        if ($plan === null || $tenant->getSubscriptionPrice() === null) {
            return null;
        }

return [...$plan, 'price' => $tenant->getSubscriptionPrice(), 'interval' => $tenant->getSubscriptionInterval() ?? 'monthly', 'currency' => 'BAM', 'startedAt' => $tenant->getSubscriptionStartedAt()?->format(DATE_ATOM)];
    }
    private function invoicePayload(Invoice $invoice): array
    {
        return ['id' => $invoice->getId()->toRfc4122(), 'number' => $invoice->getNumber(), 'plan' => $invoice->getPlan(), 'amount' => $invoice->getAmount(), 'currency' => $invoice->getCurrency(), 'billingPeriod' => $invoice->getBillingPeriod()->format('Y-m-d'), 'billingInterval' => $invoice->getBillingInterval(), 'status' => $invoice->getStatus(), 'issuedAt' => $invoice->getIssuedAt()->format(DATE_ATOM)];
    }
    private function invoicePdfHtml(Invoice $invoice, Tenant $tenant, ?Address $address, string $locale): string
    {
        $issuer = $this->parameters->get('invoice_issuer');
        $subtotal = $invoice->getAmount();
        $pdv = (int) round($subtotal * 0.17);
        $money = static fn (int $value): string => number_format($value / 100, 2, ',', '.') . ' KM';
        $customerAddress = $address ? sprintf('%s, %s %s, %s', $address->getStreet(), $address->getZipcode(), $address->getCity(), $address->getCountry()) : '';
        $logoPath = realpath($this->getParameter('kernel.project_dir').'/public/media/sc-connect-logo.png');
        $logo = $logoPath === false ? '' : 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        $translate = fn (string $key): string => $this->translator->trans($key, locale: $locale);

        return $this->renderView('documents/invoice.html.twig', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'issuer' => $issuer,
            'logo' => $logo,
            'customer_address' => $customerAddress,
            'subtotal' => $money($subtotal),
            'vat' => $money($pdv),
            'total' => $money($subtotal + $pdv),
            'billing_period' => $this->invoiceBillingPeriod($invoice),
            'labels' => [
                'title' => $translate('invoice.title'), 'billed_to' => $translate('invoice.billed_to'),
                'date' => $translate('invoice.date'), 'billing_period' => $translate('invoice.billing_period'), 'status' => $translate('invoice.status'), 'open' => $translate('invoice.open'),
                'description' => $translate('invoice.description'), 'amount' => $translate('invoice.amount'), 'subscription' => $translate('invoice.subscription'),
                'subscription_billing' => $translate('invoice.subscription_billing'), 'subtotal' => $translate('invoice.subtotal'), 'vat' => $translate('invoice.vat'), 'total' => $translate('invoice.total'),
            ],
        ]);
    }

    private function invoiceBillingPeriod(Invoice $invoice): string
    {
        $start = $invoice->getBillingPeriod();
        if ($invoice->getBillingInterval() !== 'annual') {
            return $start->format('d.m.Y');
        }

        return sprintf('%s - %s', $start->format('d.m.Y'), $start->modify('+1 year -1 day')->format('d.m.Y'));
    }

    /** @return array<string, mixed> */
    private function tenantPayload(Tenant $tenant, TenantMembership $membership): array
    {
        return [
            'id' => $tenant->getId()->toRfc4122(), 'name' => $tenant->getName(), 'role' => $membership->getRole(),
            'oib' => $tenant->getOib(), 'pdv' => $tenant->getPdv(), 'phone' => $tenant->getPhone(),
            'email' => $tenant->getEmail(), 'website' => $tenant->getWebsite(),
            'defaultSnippetLocale' => $tenant->getDefaultSnippetLocale(),
            'enabledSnippetLocales' => $tenant->getEnabledSnippetLocales(),
        ];
    }

    /** @return list<string>|null */
    private function enabledSnippetLocales(
        mixed $value,
        EntityManagerInterface $entityManager,
    ): ?array {
        if (!is_array($value)) {
            return null;
        }

        $codes = array_values(array_unique(array_filter(
            $value,
            static fn (mixed $code): bool => is_string($code) && $code !== '',
        )));
        if ($codes === []) {
            return null;
        }

        $locales = $entityManager->getRepository(Locale::class)->findBy([
            'code' => $codes,
            'active' => true,
        ]);
        if (count($locales) !== count($codes)) {
            return null;
        }

        return $codes;
    }

    /** @return array<string, string|null> */
    private function addressPayload(Address $address): array
    {
        return [
            'id' => $address->getId()->toRfc4122(), 'tenantId' => $address->getTenant()?->getId()->toRfc4122(),
            'isDefault' => $address->isDefault(), 'country' => $address->getCountry(), 'company' => $address->getCompany(), 'department' => $address->getDepartment(),
            'street' => $address->getStreet(), 'zipcode' => $address->getZipcode(), 'city' => $address->getCity(),
            'countryState' => $address->getCountryState(), 'phone' => $address->getPhone(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(), 'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
        ];
    }

    private function requiredText(mixed $value, string $field, int $maxLength): string
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw $this->createNotFoundException(sprintf('%s is required and must not exceed %d characters.', $field, $maxLength));
        }

        return $value;
    }

    private function nullableText(mixed $value, string $field, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if (mb_strlen($value) > $maxLength) {
            throw $this->createNotFoundException(sprintf('%s must not exceed %d characters.', $field, $maxLength));
        }

        return $value === '' ? null : $value;
    }
}
