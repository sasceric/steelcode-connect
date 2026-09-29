<?php

namespace App\Controller\Api;

use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/auth')]
final class AuthController extends AbstractController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    #[Route('/register', name: 'api_v1_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        InventoryService $inventory,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => 'A JSON request body is required.'], Response::HTTP_BAD_REQUEST);
        }

        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $tenantName = trim((string) ($payload['tenantName'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'A valid email address is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (mb_strlen($password) < 8) {
            return $this->json(['message' => 'Password must contain at least 8 characters.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($tenantName === '' || mb_strlen($tenantName) > 255) {
            return $this->json(['message' => 'Company name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($entityManager->getRepository(User::class)->findOneBy(['email' => $email]) instanceof User) {
            return $this->json(['message' => 'An account with this email already exists.'], Response::HTTP_CONFLICT);
        }

        $tenant = new Tenant($tenantName);
        $user = new User($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $membership = new TenantMembership($tenant, $user, 'owner');

        $entityManager->persist($tenant);
        $entityManager->persist($user);
        $entityManager->persist($membership);
        $inventory->defaultWarehouse($tenant, $entityManager);
        $entityManager->flush();

        return $this->json([
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($tenant, 'owner'),
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_v1_auth_login', methods: ['POST'])]
    public function login(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->authenticatedUser();
        $membership = $entityManager->getRepository(TenantMembership::class)->findOneBy(['user' => $user]);

        if (!$membership instanceof TenantMembership) {
            return $this->json(['message' => 'No tenant membership was found.'], Response::HTTP_FORBIDDEN);
        }

        $tenant = $membership->getTenant();

        return $this->json([
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($tenant, $membership->getRole()),
        ]);
    }

    #[Route('/logout', name: 'api_v1_auth_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie('steelcode_session', '/', null, false, true, false, 'lax');

        return $response;
    }

    #[Route('/me', name: 'api_v1_auth_me', methods: ['GET'])]
    public function me(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->authenticatedUser();
        $membership = $entityManager->getRepository(TenantMembership::class)->findOneBy(['user' => $user]);

        if (!$membership instanceof TenantMembership) {
            return $this->json(['message' => 'No tenant membership was found.'], Response::HTTP_FORBIDDEN);
        }

        $tenant = $membership->getTenant();

        return $this->json([
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($tenant, $membership->getRole()),
        ]);
    }

    #[Route('/profile', name: 'api_v1_auth_profile_update', methods: ['PATCH'])]
    public function updateProfile(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => 'A JSON request body is required.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->authenticatedUser();

        foreach ([
            'firstName' => ['label' => 'First name', 'maxLength' => 100],
            'lastName' => ['label' => 'Last name', 'maxLength' => 100],
            'phone' => ['label' => 'Phone', 'maxLength' => 32],
            'title' => ['label' => 'Title', 'maxLength' => 100],
        ] as $field => $rule) {
            if (isset($payload[$field]) && mb_strlen(trim((string) $payload[$field])) > $rule['maxLength']) {
                return $this->json([
                    'message' => sprintf('%s must not exceed %d characters.', $rule['label'], $rule['maxLength']),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $firstName = $this->nullableText($payload['firstName'] ?? null);
        $lastName = $this->nullableText($payload['lastName'] ?? null);
        $phone = $this->nullableText($payload['phone'] ?? null);
        $title = $this->nullableText($payload['title'] ?? null);
        $locale = mb_strtolower(trim((string) ($payload['locale'] ?? $user->getLocale())));

        if (!preg_match('/^[a-z]{2,3}(?:-[a-z]{2})?$/', $locale)) {
            return $this->json(['message' => 'Locale must use a valid language code, such as en or bs.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPhone($phone);
        $user->setTitle($title);
        $user->setLocale($locale);
        $entityManager->flush();

        return $this->json(['user' => $this->userPayload($user)]);
    }

    #[Route('/password', name: 'api_v1_auth_password_update', methods: ['PATCH'])]
    public function updatePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => 'A JSON request body is required.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->authenticatedUser();
        $currentPassword = (string) ($payload['currentPassword'] ?? '');
        $newPassword = (string) ($payload['newPassword'] ?? '');

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['message' => $this->translated('auth.password.current_invalid', $user)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (mb_strlen($newPassword) < 8) {
            return $this->json(['message' => $this->translated('auth.password.too_short', $user)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (mb_strlen($newPassword) > 4096) {
            return $this->json(['message' => $this->translated('auth.password.too_long', $user)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($passwordHasher->isPasswordValid($user, $newPassword)) {
            return $this->json(['message' => $this->translated('auth.password.same_as_current', $user)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $entityManager->flush();

        return $this->json(['message' => $this->translated('auth.password.updated', $user)]);
    }

    /** @return array<string, bool|list<string>|string|null> */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
            'title' => $user->getTitle(),
            'active' => $user->isActive(),
            'avatarId' => $user->getAvatarId()?->toRfc4122(),
            'locale' => $user->getLocale(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function translated(string $message, User $user): string
    {
        return $this->translator->trans($message, locale: $user->getLocale());
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /** @return array<string, string|null> */
    private function tenantPayload(Tenant $tenant, string $role): array
    {
        return [
            'id' => $tenant->getId()->toRfc4122(),
            'name' => $tenant->getName(),
            'role' => $role,
            'oib' => $tenant->getOib(),
            'pdv' => $tenant->getPdv(),
            'phone' => $tenant->getPhone(),
            'email' => $tenant->getEmail(),
            'website' => $tenant->getWebsite(),
            'defaultSnippetLocale' => $tenant->getDefaultSnippetLocale(),
            'enabledSnippetLocales' => $tenant->getEnabledSnippetLocales(),
        ];
    }
}
