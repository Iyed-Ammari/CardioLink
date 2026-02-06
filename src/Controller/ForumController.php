<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ForumController extends AbstractController
{
    // Page d'accueil avec choix Front/Back
    #[Route('/forum', name: 'forum_index')]
    public function index(): Response
    {
        return $this->render('forum/index.html.twig');
    }

    // Front-office : voir tous les posts et créer un post
    #[Route('/forum/frontoffice', name: 'forum_frontoffice')]
    public function frontoffice(PostRepository $postRepository): Response
    {
        $posts = $postRepository->findAll();

        return $this->render('forum/frontoffice.html.twig', [
            'posts' => $posts,
        ]);
    }

    // Back-office : voir tous les posts et gérer
    #[Route('/forum/backoffice', name: 'forum_backoffice')]
    public function backoffice(PostRepository $postRepository): Response
    {
        $posts = $postRepository->findAll();

        return $this->render('forum/backoffice.html.twig', [
            'posts' => $posts,
        ]);
    }

    // Créer un post depuis le front-office sans login
#[Route('/forum/frontoffice', name: 'forum_frontoffice')]
public function create(Request $request, EntityManagerInterface $em, PostRepository $postRepository, UserRepository $userRepository): Response
{
    // Création du post si formulaire soumis
    if ($request->isMethod('POST')) {
        $userId = $request->request->get('userId');
        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable avec cet ID !');
            return $this->redirectToRoute('forum_frontoffice');
        }

        $post = new Post();
        $post->setUser($user);
        $post->setTitle($request->request->get('title'));
        $post->setContent($request->request->get('content'));
        $post->setCreatedAT(new \DateTimeImmutable());

        $em->persist($post);
        $em->flush();

        $this->addFlash('success', 'Post créé avec succès !');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // Affichage de tous les posts
    $posts = $postRepository->findAll();

    return $this->render('forum/frontoffice.html.twig', [
        'posts' => $posts,
    ]);
}

    // Modifier un post (Back-office)
    #[Route('/forum/{id}/edit', name: 'forum_edit')]
    public function edit(Post $post, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $post->setTitle($request->request->get('title'));
            $post->setContent($request->request->get('content'));

            $em->flush();

            return $this->redirectToRoute('forum_backoffice');
        }

        return $this->render('forum/edit.html.twig', [
            'post' => $post,
        ]);
    }

    // Supprimer un post (Back-office)
    #[Route('/forum/{id}/delete', name: 'forum_delete')]
    public function delete(Post $post, EntityManagerInterface $em): Response
    {
        $em->remove($post);
        $em->flush();

        return $this->redirectToRoute('forum_backoffice');
    }

    // Voir un post en détail (Front-office ou Back-office)
    #[Route('/forum/{id}', name: 'forum_show')]
    public function show(Post $post): Response
    {
        return $this->render('forum/show.html.twig', [
            'post' => $post,
        ]);
    }
}