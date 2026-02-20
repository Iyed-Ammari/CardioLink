<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\DossierMedical;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    #[Route('/', name: 'admin_user_index')]
    public function index(UserRepository $repo, Request $request): Response
    {
        $search = $request->query->get('search');
        $role = $request->query->get('role');
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'ASC');

        $qb = $repo->createQueryBuilder('u');

        if ($search) {
            $qb->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
               ->setParameter('search', '%'.$search.'%');
        }

        if ($role) {
            $qb->andWhere('u.roles LIKE :role')
               ->setParameter('role', '%'.$role.'%');
        }

        $qb->orderBy('u.'.$sort, $order);
        $users = $qb->getQuery()->getResult();

        return $this->render('admin_user/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'sort' => $sort,
            'order' => $order
        ]);
    }

    #[Route('/create', name: 'admin_user_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($request->isMethod('POST')) {
            $user = new User();
            $user->setNom($request->request->get('nom'));
            $user->setPrenom($request->request->get('prenom'));
            $user->setEmail($request->request->get('email'));
            $user->setTel($request->request->get('tel'));
            $user->setAdresse($request->request->get('adresse'));
            $user->setRoles([$request->request->get('role')]);
            $user->setIsVerified(true);
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTime());

            $hashed = $hasher->hashPassword($user, $request->request->get('password'));
            $user->setPassword($hashed);

            // Upload image Cloudinary
            $imageFile = $request->files->get('imageFile');
            if ($imageFile) {
                $cloudinary = new \Cloudinary\Cloudinary([
                    'cloud' => [
                        'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
                        'api_key'    => $_ENV['CLOUDINARY_API_KEY'],
                        'api_secret' => $_ENV['CLOUDINARY_API_SECRET'],
                    ]
                ]);

                $result = $cloudinary->uploadApi()->upload(
                    $imageFile->getPathname(),
                    ['folder' => 'cardiolink/users']
                );

                $user->setImageUrl($result['secure_url']);
            }

            $em->persist($user);

            // Créer dossier médical si ROLE_PATIENT
            if ($request->request->get('role') === 'ROLE_PATIENT') {
                $dossier = new DossierMedical();
                $dossier->setUser($user);
                $dossier->setGroupeSanguin($request->request->get('groupeSanguin') ?? 'O+');
                $dossier->setAntecedents($request->request->get('antecedents'));
                $dossier->setAllergies($request->request->get('allergies'));
                $dossier->setPoids($request->request->get('poids') ? (float)$request->request->get('poids') : null);
                $dossier->setTaille($request->request->get('taille') ? (float)$request->request->get('taille') : null);
                $dossier->setTensionSystolique($request->request->get('tensionSystolique') ? (int)$request->request->get('tensionSystolique') : null);
                $dossier->setTensionDiastolique($request->request->get('tensionDiastolique') ? (int)$request->request->get('tensionDiastolique') : null);
                $dossier->setFrequenceCardiaque($request->request->get('frequenceCardiaque') ? (int)$request->request->get('frequenceCardiaque') : null);

                $em->persist($dossier);
            }

            $em->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin_user/create.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_user_edit')]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($request->isMethod('POST')) {
            $user->setNom($request->request->get('nom'));
            $user->setPrenom($request->request->get('prenom'));
            $user->setEmail($request->request->get('email'));
            $user->setTel($request->request->get('tel'));
            $user->setAdresse($request->request->get('adresse'));
            $user->setRoles([$request->request->get('role')]);

            $password = $request->request->get('password');
            if ($password) {
                $hashed = $hasher->hashPassword($user, $password);
                $user->setPassword($hashed);
            }

            // Upload image Cloudinary
            $imageFile = $request->files->get('imageFile');
            if ($imageFile) {
                $cloudinary = new \Cloudinary\Cloudinary([
                    'cloud' => [
                        'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
                        'api_key'    => $_ENV['CLOUDINARY_API_KEY'],
                        'api_secret' => $_ENV['CLOUDINARY_API_SECRET'],
                    ]
                ]);

                $result = $cloudinary->uploadApi()->upload(
                    $imageFile->getPathname(),
                    ['folder' => 'cardiolink/users']
                );

                $user->setImageUrl($result['secure_url']);
            }

            $em->flush();

            $this->addFlash('success', 'Utilisateur modifié avec succès.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin_user/edit.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_user_toggle')]
    public function toggle(User $user, EntityManagerInterface $em): Response
    {
        $user->setIsActive(!$user->isActive());
        $em->flush();

        $status = $user->isActive() ? 'activé' : 'bloqué';
        $this->addFlash('success', 'Utilisateur '.$status.' avec succès.');

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(User $user, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}', name: 'admin_user_show')]
    public function show(User $user): Response
    {
        return $this->render('admin_user/show.html.twig', [
            'user' => $user
        ]);
    }
}