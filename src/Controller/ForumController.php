<?php

namespace App\Controller;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ForumController extends AbstractController
{
    // Page d'accueil
    #[Route('/forum', name: 'forum_index')]
    public function index(): Response
    {
        return $this->render('forum/index.html.twig');
    }

    // =============================
    // FRONT-OFFICE (affichage + création)
    // =============================
    #[Route('/forum/frontoffice', name: 'forum_frontoffice')]
    public function frontoffice(
        Request $request,
        PostRepository $postRepository,
        EntityManagerInterface $em
    ): Response {
        // Création d'un post
        if ($request->isMethod('POST') && !$request->query->get('search') && !$request->query->get('sort')) {
            $user = $this->getUser();

            if (!$user) {
                $this->addFlash('error', 'Vous devez être connecté pour créer un post');
                return $this->redirectToRoute('app_login');
            }

            $post = new Post();
            $post->setUser($user);
            $post->setTitle($request->request->get('title'));
            $post->setContent($request->request->get('content'));
            $post->setCreatedAt(new \DateTimeImmutable());

            $em->persist($post);
            $em->flush();

            $this->addFlash('success', 'Post créé avec succès');
            return $this->redirectToRoute('forum_frontoffice');
        }

        // Récupérer tous les posts
        $allPosts = $postRepository->findAll();

        // Récupérer les paramètres de recherche et tri
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'recent');

        // Filtrer par recherche (titre)
        if ($search) {
            $allPosts = array_filter($allPosts, function($post) use ($search) {
                return stripos($post->getTitle(), $search) !== false;
            });
        }

        // Trier les posts
        usort($allPosts, function($a, $b) use ($sort) {
            if ($sort === 'titre-asc') {
                return strcasecmp($a->getTitle(), $b->getTitle());
            } elseif ($sort === 'titre-desc') {
                return strcasecmp($b->getTitle(), $a->getTitle());
            } elseif ($sort === 'ancien') {
                return $a->getCreatedAt() <=> $b->getCreatedAt();
            } else { // 'recent' ou défaut
                return $b->getCreatedAt() <=> $a->getCreatedAt();
            }
        });

        // Affichage
        return $this->render('forum/frontoffice.html.twig', [
            'posts' => $allPosts,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    // =============================
    // MODIFIER UN POST (FRONT-OFFICE)
    // =============================
    #[Route('/forum/{id}/edit', name: 'forum_edit', methods: ['GET', 'POST'])]
    public function edit(
        Post $post,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté');
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si l'utilisateur est le propriétaire du post
        if ($post->getUser()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Vous n\'avez pas le droit de modifier ce post');
            return $this->redirectToRoute('forum_frontoffice');
        }

        // Si formulaire soumis
        if ($request->isMethod('POST')) {
            $post->setTitle($request->request->get('title'));
            $post->setContent($request->request->get('content'));

            $em->flush();

            $this->addFlash('success', 'Post modifié avec succès');
            return $this->redirectToRoute('forum_frontoffice');
        }

        // Sinon afficher le formulaire pré-rempli
        return $this->render('forum/frontoffice.html.twig', [
            'post' => $post,
        ]);
    }

    // =============================
    // BACK-OFFICE (posts + commentaires)
    // =============================
    #[Route('/forum/backoffice', name: 'forum_backoffice')]
    public function backoffice(
        PostRepository $postRepository,
        CommentRepository $commentRepository
    ): Response {
        $user = $this->getUser();

        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('error', 'Vous devez être administrateur pour accéder au back-office');
            return $this->redirectToRoute('forum_frontoffice');
        }

        return $this->render('forum/backoffice.html.twig', [
            'posts' => $postRepository->findAll(),
            'comments' => $commentRepository->findAll(),
        ]);
    }

    // =============================
    // SUPPRIMER POST
    // =============================
    #[Route('/forum/{id}/delete', name: 'forum_delete', methods: ['POST'])]
    public function delete(Post $post, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté');
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si l'utilisateur est propriétaire du post ou admin
        if ($post->getUser()->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles())) {
            $this->addFlash('error', 'Vous n\'avez pas le droit de supprimer ce post');
            return $this->redirectToRoute('forum_frontoffice');
        }

        // Supprimer d'abord tous les commentaires liés
        foreach ($post->getComments() as $comment) {
            $em->remove($comment);
        }

        // Puis supprimer le post
        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Post supprimé avec succès');
        
        // Redirection selon le rôle
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->redirectToRoute('forum_backoffice');
        }
        return $this->redirectToRoute('forum_frontoffice');
    }

    // =============================
    // VOIR UN POST
    // =============================
    #[Route('/forum/{id}', name: 'forum_show')]
    public function show(Post $post): Response
    {
        return $this->render('forum/show.html.twig', [
            'post' => $post,
        ]);
    }
#[Route('/post/{id}/like', name:'post_like', methods:['POST'])]
public function like(Post $post, EntityManagerInterface $em): JsonResponse
{
    $post->setLikes($post->getLikes() + 1);
    $em->flush();

    return new JsonResponse([
        'likes' => $post->getLikes()
    ]);
}
}
