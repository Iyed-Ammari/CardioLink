<?php

namespace App\Controller;

use App\Entity\DossierMedical;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier) {}

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $user->setIsVerified(false);  // Mark as unverified initially

            // Create DossierMedical with information from the form
            $dossier = new DossierMedical();
            $dossier->setGroupeSanguin($form->get('groupeSanguin')->getData());
            
            // Set optional medical information if provided
            if ($form->get('antecedents')->getData()) {
                $dossier->setAntecedents($form->get('antecedents')->getData());
            }
            if ($form->get('allergies')->getData()) {
                $dossier->setAllergies($form->get('allergies')->getData());
            }
            
            $dossier->setUser($user);

            $entityManager->persist($user);
            $entityManager->persist($dossier);
            $entityManager->flush();

            // Store email in session for verification
            $session->set('pending_verification_email', $user->getEmail());

            // Now send the verification email with the user in database
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('cardiolinkpidev@gmail.com', 'CardioLink Mail Bot'))
                    ->to((string) $user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            $this->addFlash('success', 'Check your email to verify your account before logging in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        // Get the email from session that was stored during registration
        $email = $session->get('pending_verification_email');
        if (!$email) {
            $this->addFlash('error', 'No pending email verification found. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        // Load the unverified user from database by email
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);
        
        if (!$user) {
            $this->addFlash('error', 'User not found. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('app_register');
        }

        // The handleEmailConfirmation sets isVerified=true, so just flush
        $entityManager->flush();
        
        // Clear session
        $session->remove('pending_verification_email');

        $this->addFlash('success', 'Your email address has been verified. You can now log in.');
        return $this->redirectToRoute('app_login');
    }
}
