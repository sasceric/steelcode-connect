<?php

namespace App\Controller\Api;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/auth/password-reset')]
final class PasswordResetController extends AbstractController
{
    #[Route('/request', methods: ['POST'])]
    public function request(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        ParameterBagInterface $parameters,
        TranslatorInterface $translator,
        RateLimiterFactory $passwordResetLimiter,
    ): JsonResponse {
        $limiter = $passwordResetLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['message' => 'Too many reset requests. Please try again later.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => 'A JSON request body is required.'], Response::HTTP_BAD_REQUEST);
        }

        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $em->getRepository(User::class)->findOneBy(['email' => $email]) : null;
        if ($user instanceof User && $user->isActive()) {
            $rawToken = bin2hex(random_bytes(32));
            $em->createQuery('DELETE FROM App\\Entity\\PasswordResetToken t WHERE t.user = :user')->setParameter('user', $user)->execute();
            $em->persist(new PasswordResetToken($user, hash('sha256', $rawToken)));
            $em->flush();
            $url = rtrim((string) ($_ENV['FRONTEND_URL'] ?? 'http://localhost:3000'), '/').'/reset-password?token='.$rawToken;
            $issuer = $parameters->get('invoice_issuer');
            $brand = $parameters->get('email_brand');
            $locale = $user->getLocale();
            $mailer->send((new TemplatedEmail())
                ->from((string) $_ENV['BREVO_FROM'])
                ->to($user->getUserIdentifier())
                ->subject($translator->trans('email.password_reset.subject', locale: $locale))
                ->htmlTemplate('email/password_recovery.html.twig')
                ->context([
                    'first_name' => $user->getFirstName() ?? '',
                    'last_name' => $user->getLastName() ?? '',
                    'reset_url' => $url,
                    'logo_url' => rtrim((string) ($_ENV['FRONTEND_URL'] ?? 'http://localhost:3000'), '/').'/sc-connect-logo.svg',
                    'locale' => $locale,
                    'issuer' => $issuer,
                    'brand' => $brand,
                ])
                ->locale($locale));
        }

        return $this->json(['message' => 'If that email belongs to an active account, reset instructions have been sent.']);
    }

    #[Route('/reset', methods: ['POST'])]
    public function reset(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => 'A JSON request body is required.'], Response::HTTP_BAD_REQUEST);
        }

        $rawToken = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if ($rawToken === '' || mb_strlen($password) < 8) {
            return $this->json(['message' => 'A valid token and a password of at least 8 characters are required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $reset = $em->getRepository(PasswordResetToken::class)->findOneBy(['tokenHash' => hash('sha256', $rawToken)]);
        if (!$reset instanceof PasswordResetToken || !$reset->isUsable()) {
            return $this->json(['message' => 'This password reset link is invalid or has expired.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user = $reset->getUser();
        $user->setPassword($hasher->hashPassword($user, $password));
        $reset->use();
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
