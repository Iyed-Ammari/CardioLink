<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/mon-profil', name: 'app_mon_profil')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', [
            'user' => $this->getUser()
        ]);
    }

    #[Route('/mon-profil/edit', name: 'app_mon_profil_edit')]
    public function edit(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $user->setNom($request->request->get('nom'));
            $user->setPrenom($request->request->get('prenom'));
            $user->setTel($request->request->get('tel'));
            $user->setAdresse($request->request->get('adresse'));

            $password = $request->request->get('password');
            if ($password) {
                $user->setPassword($hasher->hashPassword($user, $password));
            }

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
            $this->addFlash('success', 'Profil mis à jour avec succès.');
            return $this->redirectToRoute('app_mon_profil');
        }

        return $this->render('profile/edit.html.twig', [
            'user' => $user
        ]);
    }
    #[Route('/mon-profil/delete-photo', name: 'app_delete_photo')]
    public function deletePhoto(EntityManagerInterface $em): Response
   {
      $user = $this->getUser();
      $user->setImageUrl(null);
      $em->flush();

      $this->addFlash('success', 'Photo supprimée avec succès.');
      return $this->redirectToRoute('app_mon_profil');
    }
}