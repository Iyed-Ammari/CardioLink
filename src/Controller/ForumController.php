<?php

namespace App\Controller;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ForumController extends AbstractController
{
    // Page d’accueil
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
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        // Création d’un post
        if ($request->isMethod('POST')) {
            $userId = $request->request->get('userId');
            $user = $userRepository->find($userId);

            if (!$user) {
                $this->addFlash('error', 'Utilisateur introuvable');
                return $this->redirectToRoute('forum_frontoffice');
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

        // Affichage
        return $this->render('forum/frontoffice.html.twig', [
            'posts' => $postRepository->findAll(),
        ]);
    }
    // =============================
// MODIFIER UN POST (FRONT-OFFICE)
// =============================
// =============================
// MODIFIER POST (FRONT-OFFICE)
// =============================
#[Route('/forum/{id}/edit', name: 'forum_edit', methods: ['GET', 'POST'])]
public function edit(
    Post $post,
    Request $request,
    UserRepository $userRepository,
    EntityManagerInterface $em
): Response {
    // Récupérer l'ID de l'utilisateur qui veut modifier
    $userId = $request->request->get('userId') ?? $request->query->get('userId');
    $user = $userRepository->find($userId);

    if (!$user) {
        $this->addFlash('error', 'Utilisateur introuvable');
        return $this->redirectToRoute('forum_frontoffice');
    }

    // Vérifier si l'utilisateur est le propriétaire du post
    if ($post->getUser()->getId() !== $user->getId()) {
        $this->addFlash('error', 'Vous n’avez pas le droit de modifier ce post');
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
        'userId' => $user->getId(),
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
        return $this->render('forum/backoffice.html.twig', [
            'posts' => $postRepository->findAll(),
            'comments' => $commentRepository->findAll(),
        ]);
    }

    // =============================
    // SUPPRIMER POST (ADMIN)
    // =============================
    #[Route('/forum/{id}/delete', name: 'forum_delete', methods: ['POST'])]
public function delete(Post $post, EntityManagerInterface $em): Response
{
    // Supprimer d'abord tous les commentaires liés
    foreach ($post->getComments() as $comment) {
        $em->remove($comment);
    }

    // Puis supprimer le post
    $em->remove($post);
    $em->flush();

    return $this->redirectToRoute('forum_backoffice');
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
}